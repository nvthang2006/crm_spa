<?php

namespace App\Livewire\Admin;

use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Offering;
use App\Models\ServiceCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ServicesPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = 'all';

    public string $categoryFilter = 'all';

    public string $activeTab = 'offerings';

    public bool $showForm = false;

    public bool $showCommissionForm = false;

    public ?int $editingId = null;

    public ?int $editingCommissionId = null;

    public string $code = '';

    public string $name = '';

    public string $categoryId = '';

    public string $type = 'service';

    public int $durationMinutes = 60;

    public int $price = 0;

    public int $sessionsTotal = 1;

    public string $description = '';

    public bool $offeringCommissionEnabled = true;

    public string $offeringCommissionType = 'percentage';

    public string $offeringCommissionValue = '10';

    public string $commissionOfferingId = '';

    public string $beneficiaryType = 'primary_technician';

    public string $beneficiaryEmployeeId = '';

    public string $basis = 'payment';

    public string $calculationType = 'percentage';

    public string $commissionValue = '10';

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $commissionActive = true;

    public string $commissionNotes = '';

    public function mount(): void
    {
        $this->startsOn = today()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['offerings', 'commissions'], true) ? $tab : 'offerings';
    }

    public function filterByType(string $type): void
    {
        $this->typeFilter = $type;
        $this->activeTab = 'offerings';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = 'all';
        $this->categoryFilter = 'all';
        $this->resetPage();
    }

    public function newOffering(string $type = 'service'): void
    {
        $this->reset(['editingId', 'code', 'name', 'categoryId', 'price', 'description']);
        $this->type = $type;
        $this->durationMinutes = 60;
        $this->sessionsTotal = $type === 'package' ? 12 : 1;
        $this->offeringCommissionEnabled = true;
        $this->offeringCommissionType = $type === 'package' ? 'fixed' : 'percentage';
        $this->offeringCommissionValue = $type === 'package' ? '50000' : '10';
        $this->showForm = true;
    }

    public function editOffering(int $id): void
    {
        $offering = Offering::query()->with('commissionRules')->findOrFail($id);
        $this->editingId = $offering->id;
        $this->code = $offering->code;
        $this->name = $offering->name;
        $this->categoryId = (string) $offering->service_category_id;
        $this->type = $offering->type;
        $this->durationMinutes = (int) $offering->duration_minutes;
        $this->price = (int) ($offering->price ?? 0);
        $this->sessionsTotal = (int) $offering->sessions_total;
        $this->description = (string) $offering->description;

        $primaryRule = $offering->commissionRules
            ->first(fn ($rule) => $rule->beneficiary_type === 'primary_technician' && $rule->is_active);

        if ($primaryRule !== null) {
            $this->offeringCommissionEnabled = true;
            $this->offeringCommissionType = $primaryRule->calculation_type;
            $this->offeringCommissionValue = (string) $primaryRule->value;
        } else {
            $this->offeringCommissionEnabled = false;
            $this->offeringCommissionType = $offering->type === 'package' ? 'fixed' : 'percentage';
            $this->offeringCommissionValue = $offering->type === 'package' ? '50000' : '10';
        }

        $this->showForm = true;
    }

    public function saveOffering(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('offerings', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:160'],
            'categoryId' => ['nullable', 'integer', 'exists:service_categories,id'],
            'type' => ['required', Rule::in(['service', 'package'])],
            'durationMinutes' => ['required', 'integer', 'min:15', 'max:600'],
            'price' => ['required', 'integer', 'min:0'],
            'sessionsTotal' => ['required', 'integer', 'min:1', 'max:1000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'offeringCommissionEnabled' => ['boolean'],
            'offeringCommissionType' => ['required', Rule::in(['percentage', 'fixed'])],
            'offeringCommissionValue' => ['required', 'numeric', 'min:0'],
        ]);

        if ($this->offeringCommissionEnabled && $validated['offeringCommissionType'] === 'percentage' && (float) $validated['offeringCommissionValue'] > 100) {
            throw ValidationException::withMessages(['offeringCommissionValue' => 'Tỷ lệ phần trăm hoa hồng không được vượt quá 100%.']);
        }

        if ($this->editingId !== null) {
            $existingOffering = Offering::query()->findOrFail($this->editingId);
            if ($existingOffering->type !== $validated['type'] && ($existingOffering->customerPackages()->exists() || $existingOffering->invoiceItems()->exists() || $existingOffering->appointments()->exists())) {
                throw ValidationException::withMessages(['type' => 'Không thể đổi loại dịch vụ đã phát sinh giao dịch, thẻ hoặc lịch hẹn.']);
            }
        }
        $offering = $this->editingId !== null
            ? Offering::query()->findOrFail($this->editingId)
            : new Offering(['status' => 'active']);
        $offering->fill([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'service_category_id' => $validated['categoryId'] ?: null,
            'type' => $validated['type'],
            'duration_minutes' => $validated['durationMinutes'],
            'price' => $validated['price'],
            'sessions_total' => $validated['type'] === 'package' ? $validated['sessionsTotal'] : 1,
            'description' => $validated['description'],
        ]);
        $offering->save();

        if ($this->offeringCommissionEnabled && (float) $this->offeringCommissionValue > 0) {
            CommissionRule::query()->updateOrCreate(
                [
                    'offering_id' => $offering->id,
                    'beneficiary_type' => 'primary_technician',
                ],
                [
                    'beneficiary_employee_id' => null,
                    'basis' => 'completion',
                    'calculation_type' => $this->offeringCommissionType,
                    'value' => $this->offeringCommissionValue,
                    'starts_on' => today(),
                    'ends_on' => null,
                    'is_active' => true,
                    'notes' => 'Cấu hình hoa hồng KTV thực hiện trực tiếp',
                ]
            );
        } else {
            $offering->commissionRules()->where('beneficiary_type', 'primary_technician')->update(['is_active' => false]);
        }

        session()->flash('success', $this->editingId ? 'Đã cập nhật dịch vụ và mức hoa hồng.' : 'Đã thêm dịch vụ và cấu hình hoa hồng.');
        $this->showForm = false;
    }

    public function toggleStatus(int $id): void
    {
        $offering = Offering::query()->findOrFail($id);
        $offering->update(['status' => $offering->status === 'active' ? 'inactive' : 'active']);
        session()->flash('success', 'Đã cập nhật trạng thái dịch vụ.');
    }

    public function newCommission(?int $offeringId = null): void
    {
        $this->reset(['editingCommissionId', 'beneficiaryEmployeeId', 'commissionNotes', 'endsOn']);
        $this->commissionOfferingId = $offeringId ? (string) $offeringId : '';
        $this->beneficiaryType = 'primary_technician';
        $this->basis = 'payment';
        $this->calculationType = 'percentage';
        $this->commissionValue = '10';
        $this->startsOn = today()->toDateString();
        $this->commissionActive = true;
        $this->showCommissionForm = true;
    }

    public function editCommission(int $id): void
    {
        $rule = CommissionRule::query()->findOrFail($id);
        $this->editingCommissionId = $rule->id;
        $this->commissionOfferingId = (string) $rule->offering_id;
        $this->beneficiaryType = $rule->beneficiary_type;
        $this->beneficiaryEmployeeId = (string) ($rule->beneficiary_employee_id ?? '');
        $this->basis = $rule->basis;
        $this->calculationType = $rule->calculation_type;
        $this->commissionValue = (string) $rule->value;
        $this->startsOn = $rule->starts_on?->toDateString() ?? today()->toDateString();
        $this->endsOn = $rule->ends_on?->toDateString() ?? '';
        $this->commissionActive = (bool) $rule->is_active;
        $this->commissionNotes = (string) $rule->notes;
        $this->showCommissionForm = true;
    }

    public function saveCommission(): void
    {
        $validated = $this->validate([
            'commissionOfferingId' => ['required', 'integer', 'exists:offerings,id'],
            'beneficiaryType' => ['required', Rule::in(['primary_technician', 'support_technician', 'specific_employee', 'performer_employee'])],
            'beneficiaryEmployeeId' => ['nullable', 'integer', Rule::exists('employees', 'id')->when($this->beneficiaryType === 'performer_employee', fn ($rule) => $rule->where('status', 'active')->whereNull('deleted_at'))],
            'basis' => ['required', Rule::in(['payment', 'completion'])],
            'calculationType' => ['required', Rule::in(['percentage', 'fixed'])],
            'commissionValue' => ['required', 'numeric', 'min:0'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'commissionNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        if (in_array($validated['beneficiaryType'], ['specific_employee', 'performer_employee'], true) && blank($validated['beneficiaryEmployeeId'])) {
            throw ValidationException::withMessages(['beneficiaryEmployeeId' => 'Hãy chọn nhân sự nhận hoa hồng.']);
        }
        if ($validated['calculationType'] === 'percentage' && (float) $validated['commissionValue'] > 100) {
            throw ValidationException::withMessages(['commissionValue' => 'Tỷ lệ phần trăm không được vượt quá 100%.']);
        }

        if ($validated['beneficiaryType'] === 'performer_employee' && $this->commissionActive) {
            $this->ensureNoOverlappingPerformerRule((int) $validated['commissionOfferingId'], (int) $validated['beneficiaryEmployeeId'], $validated['startsOn'], $validated['endsOn'] ?: null, $this->editingCommissionId);
        }

        CommissionRule::query()->updateOrCreate(['id' => $this->editingCommissionId], [
            'offering_id' => $validated['commissionOfferingId'],
            'beneficiary_employee_id' => in_array($validated['beneficiaryType'], ['specific_employee', 'performer_employee'], true) ? $validated['beneficiaryEmployeeId'] : null,
            'beneficiary_type' => $validated['beneficiaryType'],
            'basis' => $validated['basis'],
            'calculation_type' => $validated['calculationType'],
            'value' => $validated['commissionValue'],
            'starts_on' => $validated['startsOn'],
            'ends_on' => $validated['endsOn'] ?: null,
            'is_active' => $this->commissionActive,
            'notes' => $validated['commissionNotes'],
        ]);
        $this->showCommissionForm = false;
        session()->flash('success', $this->editingCommissionId ? 'Đã cập nhật quy tắc hoa hồng.' : 'Đã thêm quy tắc hoa hồng.');
    }

    public function toggleCommission(int $id): void
    {
        $rule = CommissionRule::query()->findOrFail($id);
        if (! $rule->is_active && $rule->beneficiary_type === 'performer_employee') {
            $this->ensureNoOverlappingPerformerRule($rule->offering_id, (int) $rule->beneficiary_employee_id, $rule->starts_on->toDateString(), $rule->ends_on?->toDateString(), $rule->id);
        }
        $rule->update(['is_active' => ! $rule->is_active]);
        session()->flash('success', 'Đã cập nhật trạng thái quy tắc hoa hồng.');
    }

    private function ensureNoOverlappingPerformerRule(int $offeringId, int $employeeId, string $startsOn, ?string $endsOn, ?int $ignoreId): void
    {
        $overlap = CommissionRule::query()->where('offering_id', $offeringId)
            ->where('beneficiary_type', 'performer_employee')->where('beneficiary_employee_id', $employeeId)
            ->where('is_active', true)->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->when($endsOn !== null, fn ($query) => $query->whereDate('starts_on', '<=', $endsOn))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['commission' => 'Nhân viên này đã có mức hoa hồng riêng cho dịch vụ trong khoảng ngày đã chọn. Hãy điều chỉnh ngày hiệu lực hoặc tắt quy tắc cũ.']);
        }
    }

    public function render()
    {
        $categories = ServiceCategory::query()->where('is_active', true)->orderBy('name')->get();
        $offerings = Offering::query()
            ->with(['category', 'commissionRules'])
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->categoryFilter !== 'all', fn ($query) => $query->where('service_category_id', $this->categoryFilter))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')))
            ->orderBy('type')->orderBy('name')->paginate(10);
        $commissionRules = CommissionRule::query()->with(['offering', 'beneficiaryEmployee'])->latest()->paginate(10, ['*'], 'commissionPage');
        $commissionOfferings = Offering::query()->where('status', 'active')->orderBy('name')->get();
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $stats = [
            'all' => Offering::query()->count(),
            'service' => Offering::query()->where('type', 'service')->count(),
            'package' => Offering::query()->where('type', 'package')->count(),
            'active_commissions' => CommissionRule::query()->where('is_active', true)->count(),
            'categories' => $categories->count(),
        ];

        return view('livewire.admin.services-page', compact('offerings', 'categories', 'commissionRules', 'commissionOfferings', 'employees', 'stats'))
            ->layout('layouts.admin', ['title' => 'Dịch vụ & Cấu hình hoa hồng']);
    }
}
