<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\PackageUsageService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $tierFilter = 'all';

    public bool $showForm = false;

    public bool $showDetail = false;

    public ?int $editingId = null;

    public ?int $selectedId = null;

    public string $code = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $membershipTier = 'Thành viên';

    public string $address = '';

    public string $notes = '';

    public string $allergyNotes = '';

    public string $currentCondition = '';

    public string $contraindications = '';

    public string $careNotes = '';

    public string $preferredEmployeeId = '';

    public string $usageNote = '';

    public ?int $restoringPackageId = null;

    public string $restoringSessionNumber = '';

    public string $restoreReason = '';

    public function mount(): void
    {
        $requestedSearch = request()->query('search');
        $this->search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 120) : '';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTierFilter(): void
    {
        $this->resetPage();
    }

    public function newCustomer(): void
    {
        $this->reset(['editingId', 'selectedId', 'code', 'name', 'phone', 'email', 'address', 'notes', 'allergyNotes', 'currentCondition', 'contraindications', 'careNotes', 'preferredEmployeeId']);
        $this->membershipTier = 'Thành viên';
        $this->showForm = true;
        $this->showDetail = false;
    }

    public function editCustomer(int $id): void
    {
        $customer = Customer::query()->with('healthProfile')->findOrFail($id);
        $this->fillCustomer($customer);
        $this->editingId = $id;
        $this->showForm = true;
        $this->showDetail = false;
    }

    public function showCustomer(int $id): void
    {
        $customer = Customer::query()->with('healthProfile')->findOrFail($id);
        $this->fillCustomer($customer);
        $this->selectedId = $id;
        $this->showDetail = true;
        $this->showForm = false;
        app(AuditService::class)->record('customer.health_viewed', $customer);
    }

    private function fillCustomer(Customer $customer): void
    {
        $this->selectedId = $customer->id;
        $this->editingId = $customer->id;
        $this->code = $customer->code;
        $this->name = $customer->name;
        $this->phone = $customer->phone;
        $this->email = (string) $customer->email;
        $this->membershipTier = $customer->membership_tier;
        $this->address = (string) $customer->address;
        $this->notes = (string) $customer->notes;
        $this->allergyNotes = (string) $customer->healthProfile?->allergy_notes;
        $this->currentCondition = (string) $customer->healthProfile?->current_condition;
        $this->contraindications = (string) $customer->healthProfile?->contraindications;
        $this->careNotes = (string) $customer->healthProfile?->care_notes;
        $this->preferredEmployeeId = (string) $customer->healthProfile?->preferred_employee_id;
    }

    public function saveCustomer(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('customers', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'membershipTier' => ['required', 'string', 'max:24'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'preferredEmployeeId' => ['nullable', 'integer', 'exists:employees,id'],
            'allergyNotes' => ['nullable', 'string', 'max:5000'],
            'currentCondition' => ['nullable', 'string', 'max:5000'],
            'contraindications' => ['nullable', 'string', 'max:5000'],
            'careNotes' => ['nullable', 'string', 'max:5000'],
        ]);
        $customer = $this->editingId
            ? Customer::query()->findOrFail($this->editingId)
            : new Customer(['status' => 'active']);
        $customer->fill([
            'code' => $validated['code'], 'name' => $validated['name'], 'phone' => $validated['phone'], 'email' => $validated['email'],
            'membership_tier' => $validated['membershipTier'], 'address' => $validated['address'], 'notes' => $validated['notes'],
        ]);
        $customer->save();
        $customer->healthProfile()->updateOrCreate([], [
            'preferred_employee_id' => $validated['preferredEmployeeId'] ?: null,
            'last_updated_by' => auth()->id(),
            'allergy_notes' => $validated['allergyNotes'],
            'current_condition' => $validated['currentCondition'],
            'contraindications' => $validated['contraindications'],
            'care_notes' => $validated['careNotes'],
        ]);
        app(AuditService::class)->record('customer.saved', $customer, null, ['id' => $customer->id, 'code' => $customer->code]);
        session()->flash('success', $this->editingId ? 'Đã cập nhật hồ sơ khách hàng.' : 'Đã thêm khách hàng.');
        $this->showForm = false;
        $this->showDetail = false;
    }

    public function consumeSession(int $packageId): void
    {
        if ($this->selectedId === null) {
            throw ValidationException::withMessages(['package' => 'Hãy mở hồ sơ khách hàng trước khi trừ buổi.']);
        }
        $package = CustomerPackage::query()->where('customer_id', $this->selectedId)->find($packageId);
        if ($package === null) {
            throw ValidationException::withMessages(['package' => 'Thẻ liệu trình không thuộc hồ sơ khách đang mở.']);
        }
        app(PackageUsageService::class)->consume($package, null, $this->preferredEmployeeId !== '' ? (int) $this->preferredEmployeeId : null, $this->usageNote ?: null);
        $this->usageNote = '';
        session()->flash('success', 'Đã trừ một buổi và lưu nhật ký kiểm toán.');
    }

    public function openRestore(int $packageId, int $sessionNumber): void
    {
        $this->restoringPackageId = $packageId;
        $this->restoringSessionNumber = (string) $sessionNumber;
        $this->restoreReason = '';
    }

    public function restoreSession(): void
    {
        $validated = $this->validate([
            'restoringPackageId' => ['required', 'integer', 'exists:customer_packages,id'],
            'restoringSessionNumber' => ['required', 'integer', 'min:1'],
            'restoreReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        if ($this->selectedId === null) {
            throw ValidationException::withMessages(['restoringPackageId' => 'Hãy mở hồ sơ khách hàng trước khi hoàn lại buổi.']);
        }
        $package = CustomerPackage::query()
            ->where('customer_id', $this->selectedId)
            ->find($validated['restoringPackageId']);
        if ($package === null) {
            throw ValidationException::withMessages(['restoringPackageId' => 'Thẻ liệu trình không thuộc hồ sơ khách đang mở.']);
        }
        app(PackageUsageService::class)->restore($package, (int) $validated['restoringSessionNumber'], $validated['restoreReason']);
        $this->restoringPackageId = null;
        $this->restoringSessionNumber = '';
        $this->restoreReason = '';
        session()->flash('success', 'Đã hoàn lại buổi và lưu lý do kiểm toán.');
    }

    public function exportCustomers()
    {
        $customers = Customer::query()
            ->when($this->tierFilter !== 'all', fn ($query) => $query->where('membership_tier', $this->tierFilter))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
            ->withCount(['packages as active_packages' => fn ($query) => $query->where('status', 'active')->whereColumn('sessions_used', '<', 'sessions_total')->where(function ($nested): void {
                $nested->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
            })])
            ->orderBy('name')->get();

        return response()->streamDownload(function () use ($customers): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã khách hàng', 'Họ và tên', 'Số điện thoại', 'Email', 'Hạng thẻ', 'Thẻ đang chạy', 'Tổng chi tiêu', 'Trạng thái']);
            foreach ($customers as $customer) {
                fputcsv($handle, [$customer->code, $customer->name, $customer->phone, $customer->email, $customer->membership_tier, $customer->active_packages, $customer->total_spend, $customer->status]);
            }
            fclose($handle);
        }, 'khach-hang-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->tierFilter !== 'all', fn ($query) => $query->where('membership_tier', $this->tierFilter))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
            ->withCount(['packages as active_packages' => fn ($query) => $query->where('status', 'active')->whereColumn('sessions_used', '<', 'sessions_total')->where(function ($nested): void {
                $nested->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
            })])
            ->with(['packages' => fn ($query) => $query->where('status', 'active')->whereColumn('sessions_used', '<', 'sessions_total')->where(function ($nested): void {
                $nested->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
            })->with('offering')->latest('purchased_on')])
            ->orderBy('name')->paginate(10);
        $selectedCustomer = $this->selectedId ? Customer::query()->with(['healthProfile.preferredEmployee', 'packages.offering', 'packages.usages.performer', 'packages.usages.recorder'])->find($this->selectedId) : null;
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $tiers = Customer::query()->select('membership_tier')->distinct()->orderBy('membership_tier')->pluck('membership_tier');
        $stats = ['total' => Customer::query()->count(), 'vip' => Customer::query()->whereIn('membership_tier', ['Kim Cương', 'Vàng'])->count(), 'packages' => CustomerPackage::query()->where('status', 'active')->whereColumn('sessions_used', '<', 'sessions_total')->where(function ($query): void {
            $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
        })->count()];

        return view('livewire.admin.customers-page', compact('customers', 'selectedCustomer', 'employees', 'tiers', 'stats'))
            ->layout('layouts.admin', ['title' => 'Khách hàng & CRM']);
    }
}
