<?php

namespace App\Livewire\Admin;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Offering;
use App\Services\CheckoutService;
use App\Services\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicesPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $methodFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showForm = false;

    public string $customerId = '';

    public string $offeringId = '';

    public string $appointmentId = '';

    public string $employeeId = '';

    public string $discountAmount = '0';

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public ?int $selectedId = null;

    public ?int $voidingId = null;

    public string $voidReason = '';

    public ?int $refundingId = null;

    public string $refundMethod = 'cash';

    public string $refundReference = '';

    public string $refundReason = '';

    public function mount(): void
    {
        $this->dateFrom = today()->toDateString();
        $this->dateTo = today()->toDateString();
        $requestedSearch = request()->query('search');
        $this->search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 120) : '';
        $requestedStatus = request()->query('statusFilter');
        if (is_string($requestedStatus) && in_array($requestedStatus, ['all', 'pending', 'paid', 'void', 'refunded'], true)) {
            $this->statusFilter = $requestedStatus;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedMethodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function newInvoice(): void
    {
        $this->reset(['customerId', 'offeringId', 'appointmentId', 'employeeId', 'discountAmount', 'paymentReference', 'selectedId', 'voidingId', 'voidReason', 'refundingId', 'refundReference', 'refundReason']);
        $this->discountAmount = '0';
        $this->paymentMethod = 'cash';
        $this->showForm = true;
    }

    public function createInvoice(): void
    {
        $validated = $this->validate([
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'offeringId' => ['required', 'integer', 'exists:offerings,id'],
            'appointmentId' => ['nullable', 'integer', 'exists:appointments,id'],
            'employeeId' => ['nullable', 'integer', 'exists:employees,id'],
            'discountAmount' => ['required', 'integer', 'min:0'],
        ]);
        $offering = Offering::query()->findOrFail($validated['offeringId']);
        if ($offering->status !== 'active') {
            throw ValidationException::withMessages(['offeringId' => 'Chỉ dịch vụ hoặc gói đang hoạt động mới được lập hóa đơn.']);
        }
        if ($validated['employeeId'] && ! Employee::query()->whereKey($validated['employeeId'])->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['employeeId' => 'Nhân sự được chọn không còn hoạt động.']);
        }
        if ($validated['appointmentId']) {
            $appointment = Appointment::query()->findOrFail($validated['appointmentId']);
            if ((int) $appointment->customer_id !== (int) $validated['customerId']) {
                throw ValidationException::withMessages(['appointmentId' => 'Lịch hẹn không thuộc khách hàng đã chọn.']);
            }
            if ($appointment->status !== 'completed_pending_payment' || $appointment->payment_type !== 'service') {
                throw ValidationException::withMessages(['appointmentId' => 'Chỉ lịch dịch vụ đã hoàn thành và đang chờ thanh toán mới được liên kết.']);
            }
            if ($offering->type !== 'service') {
                throw ValidationException::withMessages(['offeringId' => 'Lịch dịch vụ chỉ được lập phiếu thu cho dịch vụ lẻ.']);
            }
            if ((int) $appointment->offering_id !== (int) $offering->id) {
                throw ValidationException::withMessages(['appointmentId' => 'Dịch vụ trên hóa đơn không khớp với lịch hẹn.']);
            }
            if ($appointment->invoice()->exists()) {
                throw ValidationException::withMessages(['appointmentId' => 'Lịch hẹn đã có hóa đơn.']);
            }
        }
        if ((int) $validated['discountAmount'] > (int) ($offering->price ?? 0)) {
            throw ValidationException::withMessages(['discountAmount' => 'Mức giảm không được lớn hơn giá niêm yết.']);
        }
        $total = max(0, (int) ($offering->price ?? 0) - (int) $validated['discountAmount']);
        $invoice = DB::transaction(function () use ($validated, $offering): Invoice {
            $invoice = Invoice::query()->create([
                'customer_id' => $validated['customerId'],
                'appointment_id' => $validated['appointmentId'] ?: null,
                'code' => 'HD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'type' => $offering->type,
                'status' => 'pending',
                'subtotal' => $offering->price ?? 0,
                'discount_amount' => $validated['discountAmount'],
                'total_received' => 0,
                'created_by' => auth()->id(),
                'issued_at' => now(),
            ]);
            $invoice->items()->create([
                'offering_id' => $offering->id,
                'employee_id' => $validated['employeeId'] ?: null,
                'description' => $offering->name,
                'quantity' => 1,
                'unit_price' => $offering->price ?? 0,
                'line_total' => $offering->price ?? 0,
                'package_sessions' => $offering->type === 'package' ? $offering->sessions_total : 0,
            ]);

            return $invoice;
        });
        $this->showForm = false;
        session()->flash('success', 'Đã lập phiếu thu '.$invoice->code.' với tổng '.number_format($total, 0, ',', '.').' ₫.');
    }

    public function payInvoice(int $id): void
    {
        $this->validate(['paymentMethod' => ['required', 'in:cash,vietqr,pos'], 'paymentReference' => ['nullable', 'string', 'max:120']]);
        app(CheckoutService::class)->pay(Invoice::query()->findOrFail($id), $this->paymentMethod, $this->paymentReference ?: null);
        $this->paymentReference = '';
        session()->flash('success', 'Đã xác nhận thu tiền và cập nhật hóa đơn.');
    }

    public function voidInvoice(int $id): void
    {
        $this->validate(['voidReason' => ['required', 'string', 'min:5', 'max:500']]);
        app(CheckoutService::class)->void(Invoice::query()->findOrFail($id), $this->voidReason);
        $this->voidReason = '';
        $this->voidingId = null;
        session()->flash('success', 'Đã hủy hóa đơn và lưu lý do.');
    }

    public function openRefund(int $id): void
    {
        $this->refundingId = $id;
        $this->refundMethod = 'cash';
        $this->refundReference = '';
        $this->refundReason = '';
        $this->voidingId = null;
    }

    public function refundInvoice(int $id): void
    {
        $this->validate([
            'refundMethod' => ['required', 'in:cash,vietqr,pos'],
            'refundReference' => ['nullable', 'string', 'max:120'],
            'refundReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        app(RefundService::class)->refund(Invoice::query()->findOrFail($id), $this->refundMethod, $this->refundReason, $this->refundReference ?: null);
        $this->refundingId = null;
        $this->refundReference = '';
        $this->refundReason = '';
        session()->flash('success', 'Đã hoàn tiền toàn bộ và lưu phiếu hoàn.');
    }

    public function openVoid(int $id): void
    {
        $this->voidingId = $id;
        $this->voidReason = '';
    }

    public function exportInvoices()
    {
        $invoices = Invoice::query()
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->methodFilter !== 'all', fn ($query) => $query->where('payment_method', $this->methodFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('issued_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('issued_at', '<=', $this->dateTo))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('code', 'like', '%'.$this->search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'))))
            ->with('customer')->latest()->get();

        return response()->streamDownload(function () use ($invoices): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã hóa đơn', 'Ngày lập', 'Khách hàng', 'Loại', 'Trạng thái', 'Phương thức', 'Tiền thực thu']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, [$invoice->code, $invoice->issued_at?->format('Y-m-d H:i:s'), $invoice->customer->name, $invoice->type, $invoice->status, $invoice->payment_method, $invoice->total_received]);
            }
            fclose($handle);
        }, 'hoa-don-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with(['customer', 'items', 'payments'])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->methodFilter !== 'all', fn ($query) => $query->where('payment_method', $this->methodFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('issued_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('issued_at', '<=', $this->dateTo))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('code', 'like', '%'.$this->search.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'))))
            ->latest()->paginate(10);
        $customers = Customer::query()->orderBy('name')->get();
        $offerings = Offering::query()->where('status', 'active')->orderBy('name')->get();
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $appointments = Appointment::query()->where('status', 'completed_pending_payment')->whereDoesntHave('invoice')->with('customer')->latest('appointment_date')->limit(30)->get();
        $stats = ['today' => Invoice::query()->where('status', 'paid')->whereDate('paid_at', today())->sum('total_received'), 'pending' => Invoice::query()->where('status', 'pending')->count(), 'package' => Invoice::query()->where('status', 'paid')->where('type', 'package')->whereDate('paid_at', today())->count()];

        return view('livewire.admin.invoices-page', compact('invoices', 'customers', 'offerings', 'employees', 'appointments', 'stats'))
            ->layout('layouts.admin', ['title' => 'Hóa đơn & Doanh thu']);
    }
}
