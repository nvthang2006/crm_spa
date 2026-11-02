<?php

namespace App\Livewire\Admin;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Offering;
use App\Models\ServiceResource;
use App\Services\AppointmentService;
use App\Services\CheckoutService;
use App\Services\PackageUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AppointmentsPage extends Component
{
    public string $date;

    public string $statusFilter = 'all';

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $customerId = '';

    public string $offeringId = '';

    public string $primaryEmployeeId = '';

    public string $supportEmployeeId = '';

    public string $resourceId = '';

    public string $appointmentDate = '';

    public string $startTime = '08:30';

    public string $endTime = '09:30';

    public string $paymentType = 'service';

    public string $packageId = '';

    public string $source = 'booked';

    public string $notes = '';

    public string $overrideReason = '';

    public function mount(): void
    {
        $requestedDate = request()->query('date');
        $this->date = is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) === 1 ? $requestedDate : today()->toDateString();
        $this->appointmentDate = $this->date;
        $requestedSearch = request()->query('search');
        $this->search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 120) : '';

        $requestedResource = request()->query('resource_id');
        if (config('spa.resource_management_enabled') && is_string($requestedResource) && is_numeric($requestedResource)) {
            $this->resourceId = $requestedResource;
            $this->showForm = true;
        }
    }

    public function newAppointment(): void
    {
        $this->reset(['editingId', 'customerId', 'offeringId', 'primaryEmployeeId', 'supportEmployeeId', 'resourceId', 'packageId', 'notes', 'overrideReason']);
        $this->appointmentDate = $this->date;
        $this->startTime = '08:30';
        $this->endTime = '09:30';
        $this->paymentType = 'service';
        $this->source = 'booked';
        $this->showForm = true;
    }

    public function updatedCustomerId(): void
    {
        $this->packageId = '';
    }

    public function updatedOfferingId(): void
    {
        $this->packageId = '';
    }

    public function editAppointment(int $id): void
    {
        $appointment = Appointment::query()->findOrFail($id);
        if (! in_array($appointment->status, ['scheduled', 'arrived'], true)) {
            throw ValidationException::withMessages(['appointment' => 'Chỉ có thể sửa lịch đã đặt hoặc đã đến.']);
        }
        $this->editingId = $appointment->id;
        $this->customerId = (string) $appointment->customer_id;
        $this->offeringId = (string) $appointment->offering_id;
        $this->primaryEmployeeId = (string) ($appointment->primary_employee_id ?? '');
        $this->supportEmployeeId = (string) ($appointment->support_employee_id ?? '');
        $this->resourceId = (string) ($appointment->service_resource_id ?? '');
        $this->packageId = (string) ($appointment->customer_package_id ?? '');
        $this->appointmentDate = $appointment->appointment_date->toDateString();
        $this->startTime = substr((string) $appointment->start_time, 0, 5);
        $this->endTime = substr((string) $appointment->end_time, 0, 5);
        $this->paymentType = $appointment->payment_type;
        $this->source = $appointment->source;
        $this->notes = (string) $appointment->notes;
        $this->overrideReason = '';
        $this->showForm = true;
    }

    public function saveAppointment(): void
    {
        $validated = $this->validate([
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'offeringId' => ['required', 'integer', 'exists:offerings,id'],
            'primaryEmployeeId' => ['nullable', 'integer', 'exists:employees,id'],
            'supportEmployeeId' => ['nullable', 'integer', 'exists:employees,id', 'different:primaryEmployeeId'],
            'resourceId' => config('spa.resource_management_enabled') ? ['nullable', 'integer', 'exists:service_resources,id'] : ['exclude'],
            'appointmentDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'paymentType' => ['required', Rule::in(['service', 'package'])],
            'packageId' => ['nullable', 'integer', 'exists:customer_packages,id'],
            'source' => ['required', Rule::in(['booked', 'walkin'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'overrideReason' => ['nullable', 'string', 'max:500'],
        ]);
        $offering = Offering::query()->findOrFail($validated['offeringId']);
        if ($validated['paymentType'] === 'service' && $offering->type !== 'service') {
            throw ValidationException::withMessages(['offeringId' => 'Lịch thanh toán tại quầy chỉ áp dụng cho dịch vụ lẻ.']);
        }
        if ($validated['paymentType'] === 'package') {
            $this->validate(['packageId' => ['required', 'integer', 'exists:customer_packages,id']]);
            $packageBelongsToCustomer = CustomerPackage::query()
                ->whereKey($validated['packageId'])
                ->where('customer_id', $validated['customerId'])
                ->where('offering_id', $validated['offeringId'])
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
                })
                ->whereColumn('sessions_used', '<', 'sessions_total')
                ->exists();
            if (! $packageBelongsToCustomer) {
                throw ValidationException::withMessages(['packageId' => 'Thẻ liệu trình không thuộc khách hoặc đã hết buổi.']);
            }
        }
        $appointment = $this->editingId ? Appointment::query()->findOrFail($this->editingId) : new Appointment(['code' => 'LH-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)), 'status' => 'scheduled', 'created_by' => auth()->id()]);
        $appointment->fill([
            'customer_id' => $validated['customerId'],
            'offering_id' => $validated['offeringId'],
            'primary_employee_id' => $validated['primaryEmployeeId'] ?: null,
            'support_employee_id' => $validated['supportEmployeeId'] ?: null,
            'service_resource_id' => config('spa.resource_management_enabled') ? ($validated['resourceId'] ?: null) : $appointment->service_resource_id,
            'appointment_date' => $validated['appointmentDate'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'source' => $validated['source'],
            'payment_type' => $validated['paymentType'],
            'customer_package_id' => $validated['paymentType'] === 'package' ? $validated['packageId'] : null,
            'notes' => $validated['notes'],
        ]);
        app(AppointmentService::class)->save($appointment, $validated['overrideReason'] ?: null);
        $this->showForm = false;
        $this->date = $validated['appointmentDate'];
        session()->flash('success', $this->editingId ? 'Đã cập nhật lịch hẹn.' : 'Đã tạo lịch hẹn.');
    }

    public function arrive(int $id): void
    {
        app(AppointmentService::class)->transition(Appointment::query()->findOrFail($id), 'arrived');
        session()->flash('success', 'Đã xác nhận khách đến sảnh.');
    }

    public function startService(int $id): void
    {
        app(AppointmentService::class)->transition(Appointment::query()->with('resource')->findOrFail($id), 'in_service');
        session()->flash('success', 'Đã đưa khách vào phục vụ.');
    }

    public function completeService(int $id): void
    {
        $message = DB::transaction(function () use ($id): string {
            $appointment = Appointment::query()->lockForUpdate()->with(['resource', 'customerPackage'])->findOrFail($id);
            if ($appointment->payment_type === 'package' && $appointment->customerPackage === null) {
                throw ValidationException::withMessages(['appointment' => 'Lịch dùng thẻ chưa được gắn thẻ liệu trình hợp lệ.']);
            }
            app(AppointmentService::class)->transition($appointment, 'completed_pending_payment');
            if ($appointment->payment_type === 'package') {
                app(PackageUsageService::class)->consume($appointment->customerPackage, $appointment, $appointment->primary_employee_id, 'Xác nhận trừ buổi khi hoàn thành dịch vụ.');
                app(AppointmentService::class)->transition($appointment->fresh(), 'package_used');

                return 'Đã hoàn thành và trừ một buổi liệu trình.';
            }
            $invoice = $appointment->invoice()->first() ?? app(CheckoutService::class)->createForAppointment($appointment);

            return 'Đã hoàn thành dịch vụ, tạo phiếu thu '.$invoice->code.'.';
        });
        session()->flash('success', $message);
    }

    public function usePackage(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $appointment = Appointment::query()->lockForUpdate()->with('customerPackage')->findOrFail($id);
            if ($appointment->status !== 'completed_pending_payment' || $appointment->payment_type !== 'package' || $appointment->customerPackage === null) {
                throw ValidationException::withMessages(['appointment' => 'Lịch hẹn chưa sẵn sàng để trừ thẻ.']);
            }
            app(PackageUsageService::class)->consume($appointment->customerPackage, $appointment, $appointment->primary_employee_id, 'Xác nhận trừ buổi bởi quản trị viên.');
            app(AppointmentService::class)->transition($appointment->fresh(), 'package_used');
        });
        session()->flash('success', 'Đã xác nhận trừ buổi và khóa lịch hẹn.');
    }

    public function cancelAppointment(int $id): void
    {
        app(AppointmentService::class)->transition(Appointment::query()->with('resource')->findOrFail($id), 'cancelled', 'Hủy bởi quản trị viên');
        session()->flash('success', 'Đã hủy lịch hẹn.');
    }

    public function markNoShow(int $id): void
    {
        app(AppointmentService::class)->transition(Appointment::query()->with('resource')->findOrFail($id), 'no_show', 'Khách không đến theo giờ hẹn');
        session()->flash('success', 'Đã đánh dấu khách không đến.');
    }

    public function render()
    {
        $appointments = Appointment::query()->with(['customer', 'offering', 'primaryEmployee', 'supportEmployee', 'resource'])->whereDate('appointment_date', $this->date)
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('code', 'like', '%'.$this->search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'))))
            ->orderBy('start_time')->get();
        $customers = Customer::query()->orderBy('name')->get();
        $offerings = Offering::query()->where('status', 'active')->orderBy('name')->get();
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $resources = config('spa.resource_management_enabled') ? ServiceResource::query()->where('status', 'ready')->orderBy('name')->get() : collect();
        $customerPackages = $this->customerId !== ''
            ? CustomerPackage::query()->with('offering')->where('customer_id', $this->customerId)->when($this->offeringId !== '', fn ($query) => $query->where('offering_id', $this->offeringId))->where('status', 'active')->where(function ($query): void {
                $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
            })->whereColumn('sessions_used', '<', 'sessions_total')->orderBy('expires_on')->get()
            : collect();
        $stats = ['total' => Appointment::query()->whereDate('appointment_date', $this->date)->count(), 'waiting' => Appointment::query()->whereDate('appointment_date', $this->date)->whereIn('status', ['scheduled', 'arrived'])->count(), 'serving' => Appointment::query()->whereDate('appointment_date', $this->date)->where('status', 'in_service')->count(), 'pending' => Appointment::query()->whereDate('appointment_date', $this->date)->where('status', 'completed_pending_payment')->count()];
        $timeSlots = collect(range(16, 41))->map(fn (int $halfHour): string => sprintf('%02d:%02d', intdiv($halfHour, 2), ($halfHour % 2) * 30));

        return view('livewire.admin.appointments-page', compact('appointments', 'customers', 'offerings', 'employees', 'resources', 'customerPackages', 'stats', 'timeSlots'))
            ->layout('layouts.admin', ['title' => 'Lịch hẹn & Tiếp đón']);
    }
}
