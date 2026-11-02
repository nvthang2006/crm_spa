<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function refund(Invoice $invoice, string $method, string $reason, ?string $reference = null): InvoiceRefund
    {
        if (! in_array($method, ['cash', 'vietqr', 'pos'], true)) {
            throw ValidationException::withMessages(['refundMethod' => 'Phương thức hoàn tiền không hợp lệ.']);
        }
        if (in_array($method, ['vietqr', 'pos'], true) && blank($reference)) {
            throw ValidationException::withMessages(['refundReference' => 'Vui lòng nhập mã giao dịch hoàn cho VietQR hoặc POS.']);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['refundReason' => 'Lý do hoàn tiền phải có ít nhất 5 ký tự.']);
        }

        return DB::transaction(function () use ($invoice, $method, $reason, $reference): InvoiceRefund {
            $lockedInvoice = Invoice::query()
                ->with(['payments', 'customerPackage.usages', 'appointment'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($lockedInvoice->status !== 'paid') {
                throw ValidationException::withMessages(['invoice' => 'Chỉ hóa đơn đã thanh toán mới được hoàn tiền.']);
            }
            if ($lockedInvoice->refunds()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Hóa đơn đã được hoàn tiền trước đó.']);
            }
            if (CommissionEntry::query()->where('invoice_id', $lockedInvoice->id)->where('status', 'locked')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Không thể hoàn tiền vì hoa hồng đã thuộc kỳ lương khóa.']);
            }
            if ($lockedInvoice->type === 'package' && $lockedInvoice->customerPackage === null) {
                throw ValidationException::withMessages(['invoice' => 'Hóa đơn gói chưa có thẻ liệu trình được tạo để hoàn.']);
            }
            if ($lockedInvoice->type === 'package' && $lockedInvoice->customerPackage?->usages()->where('direction', 'use')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Không thể hoàn toàn bộ gói đã phát sinh trừ buổi.']);
            }

            $amount = (int) $lockedInvoice->payments()->sum('amount');
            if ($amount <= 0) {
                throw ValidationException::withMessages(['invoice' => 'Hóa đơn không có số tiền thực thu để hoàn.']);
            }

            $before = $lockedInvoice->getOriginal();
            $refund = InvoiceRefund::query()->create([
                'invoice_id' => $lockedInvoice->id,
                'processed_by' => auth()->id(),
                'code' => 'HT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'method' => $method,
                'amount' => $amount,
                'reference' => filled($reference) ? trim($reference) : null,
                'reason' => trim($reason),
                'refunded_at' => now(),
            ]);

            $lockedInvoice->update(['status' => 'refunded']);
            $customer = Customer::query()->lockForUpdate()->findOrFail($lockedInvoice->customer_id);
            $customer->update(['total_spend' => max(0, (int) $customer->total_spend - $amount)]);
            CommissionEntry::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', 'pending')
                ->update(['status' => 'reversed']);

            if ($lockedInvoice->customerPackage !== null) {
                $lockedInvoice->customerPackage->update(['status' => 'refunded']);
            }

            if ($lockedInvoice->appointment?->status === 'paid') {
                $appointmentBefore = $lockedInvoice->appointment->getOriginal();
                $lockedInvoice->appointment->update(['status' => 'refunded']);
                app(AuditService::class)->record('appointment.status_changed', $lockedInvoice->appointment, $appointmentBefore, $lockedInvoice->appointment->fresh()->toArray(), 'Hoàn tiền hóa đơn '.$lockedInvoice->code);
            }

            app(AuditService::class)->record('invoice.refunded', $lockedInvoice, $before, $lockedInvoice->fresh()->toArray(), trim($reason));

            return $refund->fresh();
        });
    }
}
