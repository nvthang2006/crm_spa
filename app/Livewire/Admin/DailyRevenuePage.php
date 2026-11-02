<?php

namespace App\Livewire\Admin;

use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Offering;
use App\Services\CheckoutService;
use App\Services\CommissionRuleService;
use App\Services\DailyRevenueService;
use App\Services\RevenueReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class DailyRevenuePage extends Component
{
    use WithPagination;

    public string $date = '';

    public string $period = 'day';

    public string $month = '';

    public string $search = '';

    public string $employeeFilter = '';

    public bool $showForm = true;

    /** @var array<int, array<string, mixed>> */
    public array $customers = [];

    #[Locked]
    public string $submissionKey = '';

    #[Locked]
    public string $savedMessage = '';

    public function mount(): void
    {
        $this->date = today()->toDateString();
        $this->month = today()->format('Y-m');
        $this->newEntry();
        $this->showForm = false;
    }

    public function updatedDate(): void
    {
        $this->resetPage();
        $this->resetValidation();
        $this->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']], [
            'date.required' => 'Vui lòng chọn ngày ghi nhận.',
            'date.date_format' => 'Ngày ghi nhận không hợp lệ.',
            'date.before_or_equal' => 'Không thể ghi nhận doanh thu cho ngày trong tương lai.',
        ]);
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
        $this->validate(['month' => ['required', 'date_format:Y-m']], ['month.required' => 'Vui lòng chọn tháng báo cáo.', 'month.date_format' => 'Tháng báo cáo không hợp lệ.']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEmployeeFilter(): void
    {
        $this->resetPage();
    }

    public function switchPeriod(string $period): void
    {
        $this->period = $period === 'month' ? 'month' : 'day';
        $this->search = '';
        $this->employeeFilter = '';
        $this->resetPage();
    }

    public function viewDay(string $date): void
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        abort_unless(validator(['date' => $date], ['date' => ['date_format:Y-m-d', 'before_or_equal:today']])->passes()
            && str_starts_with($date, $this->month.'-'), 422);
        $this->date = $date;
        $this->switchPeriod('day');
        $this->showForm = false;
    }

    public function newEntry(): void
    {
        $this->period = 'day';
        $this->savedMessage = '';
        $this->submissionKey = (string) Str::uuid();
        $this->customers = [];
        $this->addCustomer();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function addCustomer(): void
    {
        if (count($this->customers) >= 50) {
            $this->addError('customers', 'Mỗi lần nhập tối đa 50 khách. Hãy lưu rồi tạo lượt nhập mới.');

            return;
        }
        $this->customers[] = [
            'key' => (string) Str::uuid(),
            'customer_type' => 'retail',
            'customer_id' => '',
            'name' => '',
            'phone' => '',
            'method' => 'cash',
            'reference' => '',
            'services' => [$this->blankService()],
        ];
    }

    public function updatedCustomers(mixed $value, ?string $nestedKey = null): void
    {
        if ($nestedKey === null) {
            return;
        }
        if (preg_match('/^(\d+)\.customer_type$/', $nestedKey, $matches)) {
            $customerIndex = (int) $matches[1];
            if ($value === 'package') {
                foreach ($this->customers[$customerIndex]['services'] as $sIndex => $s) {
                    $this->customers[$customerIndex]['services'][$sIndex]['amount'] = '0';
                }
            } else {
                foreach ($this->customers[$customerIndex]['services'] as $sIndex => $s) {
                    if (! empty($s['offering_id'])) {
                        $offering = Offering::find($s['offering_id']);
                        if ($offering && (int) ($s['amount'] ?? 0) === 0) {
                            $this->customers[$customerIndex]['services'][$sIndex]['amount'] = (string) ($offering->price ?? 0);
                        }
                    }
                }
            }
        }

        if (preg_match('/^(\d+)\.services\.(\d+)\.offering_id$/', $nestedKey, $matches)) {
            $customerIndex = (int) $matches[1];
            $serviceIndex = (int) $matches[2];
            $offeringId = (int) $value;
            $customerType = $this->customers[$customerIndex]['customer_type'] ?? 'retail';

            if ($customerType === 'package') {
                $this->customers[$customerIndex]['services'][$serviceIndex]['amount'] = '0';
            } elseif ($offeringId > 0) {
                $offering = Offering::find($offeringId);
                if ($offering) {
                    $this->customers[$customerIndex]['services'][$serviceIndex]['amount'] = (string) ($offering->price ?? 0);
                }
            }
        }
    }

    public function removeCustomer(int $index): void
    {
        if (count($this->customers) > 1 && isset($this->customers[$index])) {
            unset($this->customers[$index]);
            $this->customers = array_values($this->customers);
            $this->resetValidation();
        }
    }

    public function addService(int $customerIndex): void
    {
        if (isset($this->customers[$customerIndex]) && count($this->customers[$customerIndex]['services']) < 10) {
            $this->customers[$customerIndex]['services'][] = $this->blankService();
        }
    }

    public function removeService(int $customerIndex, int $serviceIndex): void
    {
        if (isset($this->customers[$customerIndex]['services'][$serviceIndex]) && count($this->customers[$customerIndex]['services']) > 1) {
            unset($this->customers[$customerIndex]['services'][$serviceIndex]);
            $this->customers[$customerIndex]['services'] = array_values($this->customers[$customerIndex]['services']);
            $this->resetValidation();
        }
    }

    public function save(): void
    {
        $this->showForm = true;
        $invoices = app(DailyRevenueService::class)->record($this->date, $this->customers, $this->submissionKey);
        $this->showForm = false;
        $this->resetPage();
        $this->savedMessage = 'Đã ghi nhận '.$invoices->pluck('customer_id')->unique()->count().' khách, '.number_format($invoices->sum('total_received'), 0, ',', '.').' ₫. Phiếu thu và hoa hồng đã được lưu.';
    }

    /** @return array{key: string, offering_id: string, employee_id: string, amount: string} */
    private function blankService(): array
    {
        return ['key' => (string) Str::uuid(), 'offering_id' => '', 'employee_id' => '', 'amount' => ''];
    }

    public function render(): View
    {
        $validDate = validator(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->passes();
        $customerOptions = Customer::query()->where('status', 'active')->orderBy('name')->get();
        $offerings = Offering::query()->where('status', 'active')->orderBy('type')->orderBy('name')->get();
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $rules = CommissionRule::query()->with('beneficiaryEmployee')
            ->where('is_active', true)->whereIn('basis', ['payment', 'completion'])
            ->whereIn('beneficiary_type', ['primary_technician', 'specific_employee', 'performer_employee'])
            ->whereDate('starts_on', '<=', $this->date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $this->date))
            ->when(! $validDate, fn ($query) => $query->whereRaw('1 = 0'))
            ->get()->groupBy('offering_id');
        $quotes = [];
        $draftTotal = 0;
        $draftCommission = 0;
        $draftCustomers = [];
        foreach ($this->customers as $customerIndex => $row) {
            $customerId = $row['customer_id'] ?? '';
            if ($customerId !== '') {
                $draftCustomers[] = $customerId === 'new' ? 'new-'.($row['phone'] ?: $row['key']) : 'existing-'.$customerId;
            }
            $isPackageCustomer = ($row['customer_type'] ?? 'retail') === 'package';
            foreach ($row['services'] ?? [] as $serviceIndex => $service) {
                $amount = filter_var($service['amount'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000000]]);
                $draftTotal += $amount === false ? 0 : $amount;
                $quote = [];
                $offering = $offerings->firstWhere('id', $service['offering_id'] ?? '');
                $nominalPrice = (int) ($offering?->price ?? 0);
                if ($offering && $offering->type === 'package' && $offering->sessions_total > 1) {
                    $nominalPrice = (int) round($offering->price / $offering->sessions_total);
                }

                if ($amount !== false && $employees->contains('id', $service['employee_id'] ?? '')) {
                    foreach (app(CommissionRuleService::class)->forPerformer($rules->get($service['offering_id'] ?? '', collect()), (int) $service['employee_id']) as $rule) {
                        $beneficiary = $rule->beneficiary_type === 'specific_employee' ? $rule->beneficiaryEmployee : $employees->firstWhere('id', $service['employee_id']);
                        if ($beneficiary === null) {
                            continue;
                        }
                        $commission = app(CheckoutService::class)->commissionAmount($rule, $amount, 1, $nominalPrice);
                        $draftCommission += $commission;
                        $quote[] = [
                            'employee' => $beneficiary->name,
                            'rate' => $rule->calculation_type === 'fixed' ? number_format((float) $rule->value, 0, ',', '.').' ₫/ca' : number_format((float) $rule->value, 2, ',', '.').'%',
                            'amount' => $commission,
                            'is_package' => $isPackageCustomer,
                        ];
                    }
                }
                $quotes[$customerIndex][$serviceIndex] = $quote;
            }
        }
        $draft = ['customers' => count(array_unique($draftCustomers)), 'revenue' => $draftTotal, 'commission' => $draftCommission];
        $validMonth = validator(['month' => $this->month], ['month' => ['required', 'date_format:Y-m']])->passes();
        $monthly = $this->period === 'month';
        $validPeriod = $monthly ? $validMonth : $validDate;
        $startsOn = $monthly && $validMonth ? $this->month.'-01' : ($validDate ? $this->date : today()->toDateString());
        $endsOn = $monthly && $validMonth ? CarbonImmutable::parse($startsOn)->endOfMonth()->toDateString() : $startsOn;
        $reportService = app(RevenueReportService::class);
        $report = $validPeriod ? $reportService->summarize($startsOn, $endsOn) : ['summary' => ['customers' => 0, 'revenue' => 0, 'commission' => 0], 'staff' => [], 'days' => []];
        $summary = $report['summary'];
        $staff = $report['staff'];
        $days = $report['days'];
        $periodLabel = $monthly ? 'tháng '.($validMonth ? CarbonImmutable::parse($startsOn)->format('m/Y') : 'chưa hợp lệ') : 'ngày '.($validDate ? CarbonImmutable::parse($startsOn)->format('d/m/Y') : 'chưa hợp lệ');
        $search = mb_substr(trim($this->search), 0, 120);
        $entries = Invoice::query()->where('status', 'paid')
            ->whereBetween('paid_at', [$startsOn.' 00:00:00', $endsOn.' 23:59:59.999999'])
            ->when(! $validPeriod, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->withTrashed()->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%'))))
            ->when($this->employeeFilter !== '', fn ($query) => $query->where(fn ($nested) => $nested
                ->whereHas('items', fn ($item) => $item->where('employee_id', $this->employeeFilter))
                ->orWhereHas('commissionEntries', fn ($commission) => $commission->where('employee_id', $this->employeeFilter)->whereIn('status', ['pending', 'locked']))))
            ->with(['customer' => fn ($query) => $query->withTrashed(), 'items.employee' => fn ($query) => $query->withTrashed(),
                'commissionEntries' => fn ($query) => $query->whereIn('status', ['pending', 'locked']),
                'commissionEntries.employee' => fn ($query) => $query->withTrashed()])
            ->latest('paid_at')->latest('id')->paginate(15);
        $details = $reportService->details($entries->getCollection(), $this->employeeFilter);
        $reportEmployees = Employee::withTrashed()->orderBy('name')->get();

        return view('livewire.admin.daily-revenue-page', compact('customerOptions', 'offerings', 'employees', 'quotes', 'draft', 'summary', 'staff', 'entries', 'details', 'days', 'periodLabel', 'reportEmployees'))
            ->layout('layouts.admin', ['title' => 'Nhập cuối ngày']);
    }
}
