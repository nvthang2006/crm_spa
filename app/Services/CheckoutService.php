<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function createForAppointment(Appointment $appointment): Invoice
    {
        $appointment->loadMissing('offering');
        $offering = $appointment->offering;
        if ($appointment->status !== 'completed_pending_payment' || $appointment->payment_type !== 'service' || $offering?->type !== 'service') {
            throw ValidationException::withMessages(['appointment' => 'Chỉ lịch dịch vụ đã hoàn thành mới được tạo phiếu thu.']);
        }
        $total = (int) ($offering->price ?? 0);

        return DB::transaction(function () use ($appointment, $offering, $total): Invoice {
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($lockedAppointment->status !== 'completed_pending_payment' || $lockedAppointment->payment_type !== 'service') {
                throw ValidationException::withMessages(['appointment' => 'Chỉ lịch dịch vụ đã hoàn thành mới được tạo phiếu thu.']);
            }
            $existingInvoice = Invoice::query()
                ->where('appointment_id', $lockedAppointment->id)
                ->lockForUpdate()
                ->first();
            if ($existingInvoice !== null) {
                return $existingInvoice->fresh();
            }
            $invoice = Invoice::query()->create([
                'customer_id' => $lockedAppointment->customer_id,
                'appointment_id' => $lockedAppointment->id,
                'code' => 'HD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'type' => $offering->type === 'package' ? 'package' : 'service',
                'status' => 'pending',
                'subtotal' => $total,
                'total_received' => 0,
                'created_by' => auth()->id(),
                'issued_at' => now(),
            ]);
            $invoice->items()->create([
                'offering_id' => $offering->id,
                'employee_id' => $lockedAppointment->primary_employee_id,
                'description' => $offering->name,
                'quantity' => 1,
                'unit_price' => $total,
                'line_total' => $total,
                'package_sessions' => $offering->type === 'package' ? $offering->sessions_total : 0,
            ]);
            app(AuditService::class)->record('invoice.created', $invoice, null, $invoice->fresh()->toArray());

            return $invoice->fresh();
        });
    }

    public function pay(Invoice $invoice, string $method, ?string $reference = null, ?CarbonImmutable $paidAt = null): Payment
    {
        if (! in_array($method, ['cash', 'vietqr', 'pos'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Phương thức thu tiền không hợp lệ.']);
        }
        if (in_array($method, ['vietqr', 'pos'], true) && blank($reference)) {
            throw ValidationException::withMessages(['payment_reference' => 'Vui lòng nhập mã giao dịch cho VietQR hoặc POS.']);
        }

        $paidAt ??= CarbonImmutable::now();

        return DB::transaction(function () use ($invoice, $method, $reference, $paidAt): Payment {
            $lockedInvoice = Invoice::query()->lockForUpdate()->with('customer', 'appointment', 'items.offering')->findOrFail($invoice->id);
            if ($lockedInvoice->status !== 'pending') {
                throw ValidationException::withMessages(['invoice' => 'Chỉ hóa đơn đang chờ thanh toán mới được thu tiền.']);
            }
            if ($lockedInvoice->items->isEmpty()) {
                throw ValidationException::withMessages(['invoice' => 'Hóa đơn phải có ít nhất một dòng hàng trước khi thu tiền.']);
            }
            $offeringTypes = $lockedInvoice->items
                ->map(fn ($item): ?string => $item->offering?->type)
                ->filter()
                ->unique()
                ->values();
            if ($lockedInvoice->daily_entry_key === null && ($offeringTypes->count() !== 1 || $offeringTypes->first() !== $lockedInvoice->type)) {
                throw ValidationException::withMessages(['invoice' => 'Loại hóa đơn không khớp với dịch vụ/gói trong các dòng hàng.']);
            }
            if ($lockedInvoice->appointment !== null) {
                if ($lockedInvoice->type === 'package') {
                    throw ValidationException::withMessages(['invoice' => 'Phiếu bán gói liệu trình phải được lập độc lập, không liên kết lịch dịch vụ.']);
                }
                if ($lockedInvoice->appointment->status !== 'completed_pending_payment') {
                    throw ValidationException::withMessages(['invoice' => 'Lịch hẹn phải hoàn thành dịch vụ trước khi thu tiền.']);
                }
                if ($lockedInvoice->appointment->payment_type !== 'service') {
                    throw ValidationException::withMessages(['invoice' => 'Lịch dùng thẻ liệu trình không tạo phiếu thu dịch vụ.']);
                }
                if ($lockedInvoice->items->count() !== 1 || (int) $lockedInvoice->appointment->customer_id !== (int) $lockedInvoice->customer_id || (int) $lockedInvoice->items->first()?->offering_id !== (int) $lockedInvoice->appointment->offering_id) {
                    throw ValidationException::withMessages(['invoice' => 'Hóa đơn không khớp với lịch hẹn liên kết.']);
                }
            }
            $payment = $lockedInvoice->payments()->create([
                'received_by' => auth()->id(),
                'receipt_code' => 'PT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'method' => $method,
                'amount' => max(0, $lockedInvoice->subtotal - $lockedInvoice->discount_amount),
                'reference' => filled($reference) ? trim($reference) : null,
                'paid_at' => $paidAt,
            ]);
            $lockedInvoice->update([
                'status' => 'paid',
                'total_received' => $payment->amount,
                'payment_method' => $method,
                'paid_at' => $paidAt,
            ]);
            $customer = Customer::query()->lockForUpdate()->findOrFail($lockedInvoice->customer_id);
            $customer->increment('total_spend', $payment->amount);
            if ($customer->last_visit_at === null || $customer->last_visit_at->lt($paidAt)) {
                $customer->update(['last_visit_at' => $paidAt]);
            }
            if ($lockedInvoice->type === 'package' && $lockedInvoice->daily_entry_key === null) {
                $package = $this->createCustomerPackage($lockedInvoice);
                $lockedInvoice->update(['customer_package_id' => $package->id]);
            }
            if ($lockedInvoice->appointment?->status === 'completed_pending_payment') {
                app(AppointmentService::class)->transition($lockedInvoice->appointment, 'paid');
            } else {
                $lockedInvoice->appointment?->update(['status' => 'paid']);
            }
            $this->createCommissionEntries($lockedInvoice, $payment->amount, $paidAt);
            app(AuditService::class)->record('invoice.paid', $lockedInvoice, null, $lockedInvoice->fresh()->toArray());

            return $payment;
        });
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['invoice' => 'Cần nhập lý do khi hủy hóa đơn.']);
        }
        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages(['invoice' => 'Không thể hủy hóa đơn đã thanh toán; hãy lập phiếu hoàn/điều chỉnh.']);
        }
        if ($invoice->status === 'void') {
            throw ValidationException::withMessages(['invoice' => 'Hóa đơn đã được hủy trước đó.']);
        }
        $before = $invoice->getOriginal();
        $invoice->update(['status' => 'void', 'voided_at' => now(), 'notes' => trim($reason)]);
        app(AuditService::class)->record('invoice.voided', $invoice, $before, $invoice->fresh()->toArray(), $reason);

        return $invoice->fresh();
    }

    private function createCustomerPackage(Invoice $invoice): CustomerPackage
    {
        $invoice->loadMissing('items.offering');
        $item = $invoice->items->first(fn ($invoiceItem) => $invoiceItem->offering?->type === 'package');

        if ($item === null || $item->offering === null) {
            throw ValidationException::withMessages(['invoice' => 'Hóa đơn gói phải có ít nhất một dịch vụ loại gói.']);
        }

        $offering = $item->offering;
        $purchasedOn = CarbonImmutable::today();

        return CustomerPackage::query()->create([
            'customer_id' => $invoice->customer_id,
            'offering_id' => $offering->id,
            'code' => 'THE-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'purchased_on' => $purchasedOn,
            'starts_on' => $purchasedOn,
            'expires_on' => $purchasedOn->addYear(),
            'sessions_total' => $item->package_sessions ?: $offering->sessions_total,
            'sessions_used' => 0,
            'unit_price' => (int) $item->unit_price,
            'status' => 'active',
            'notes' => 'Tạo tự động từ '.$invoice->code,
        ]);
    }

    private function createCommissionEntries(Invoice $invoice, int $amount, CarbonImmutable $occurredAt): void
    {
        $invoice->loadMissing(['items.offering', 'items.employee', 'appointment.primaryEmployee', 'appointment.supportEmployee']);

        foreach ($invoice->items as $item) {
            if ($item->offering === null) {
                continue;
            }

            $rules = CommissionRule::query()
                ->where('offering_id', $item->offering_id)
                ->where('is_active', true)
                ->whereDate('starts_on', '<=', $occurredAt->toDateString())
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $occurredAt->toDateString()))
                ->whereIn('basis', ['payment', 'completion'])
                ->get();

            $performerId = $invoice->appointment?->primary_employee_id ?? $item->employee_id;
            $rules = app(CommissionRuleService::class)->forPerformer($rules, $performerId === null ? null : (int) $performerId);

            foreach ($rules as $rule) {
                $employee = match ($rule->beneficiary_type) {
                    'support_technician' => $invoice->appointment?->supportEmployee,
                    'specific_employee' => $rule->beneficiaryEmployee,
                    default => $invoice->appointment?->primaryEmployee ?? $item->employee,
                };

                if ($employee === null) {
                    continue;
                }

                $exists = CommissionEntry::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('employee_id', $employee->id)
                    ->where('commission_rule_id', $rule->id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $baseAmount = (int) round($amount * ((int) $item->quantity / max(1, $invoice->items->sum('quantity'))));
                $nominalPrice = (int) ($item->offering?->price ?? 0);
                if ($item->offering && $item->offering->type === 'package' && $item->offering->sessions_total > 1) {
                    $nominalPrice = (int) round($item->offering->price / $item->offering->sessions_total);
                }
                $commissionAmount = $this->commissionAmount($rule, $baseAmount, (int) $item->quantity, $nominalPrice);

                CommissionEntry::query()->create([
                    'employee_id' => $employee->id,
                    'invoice_id' => $invoice->id,
                    'appointment_id' => $invoice->appointment_id,
                    'offering_id' => $item->offering_id,
                    'commission_rule_id' => $rule->id,
                    'basis' => $rule->basis,
                    'calculation_type' => $rule->calculation_type,
                    'rate' => $rule->value,
                    'amount' => max(0, $commissionAmount),
                    'status' => 'pending',
                    'occurred_at' => $occurredAt,
                ]);
            }
        }
    }

    public function commissionAmount(CommissionRule $rule, int $baseAmount, int $quantity = 1, ?int $nominalPrice = null): int
    {
        if ($rule->calculation_type === 'fixed') {
            return max(0, (int) round((float) $rule->value * $quantity));
        }

        $effectiveAmount = ($baseAmount === 0 && $nominalPrice !== null && $nominalPrice > 0)
            ? $nominalPrice
            : $baseAmount;

        return max(0, (int) round($effectiveAmount * ((float) $rule->value / 100)));
    }
}
