<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceRefund;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        $invoice->load(['customer', 'appointment.offering', 'items.offering', 'items.employee', 'payments', 'refunds.processedBy']);

        return view('admin.print.invoice', compact('invoice'));
    }

    public function shiftReport(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->endOfDay();
        $payments = Invoice::query()
            ->whereHas('payments', fn ($query) => $query->where('status', 'completed')->whereBetween('paid_at', [$from, $to]))
            ->with(['customer', 'payments' => fn ($query) => $query->where('status', 'completed')->whereBetween('paid_at', [$from, $to])])
            ->get();
        $refunds = InvoiceRefund::query()
            ->whereBetween('refunded_at', [$from, $to])
            ->with('invoice.customer')
            ->latest('refunded_at')
            ->get();
        $invoices = Invoice::query()
            ->whereBetween('issued_at', [$from, $to])
            ->with('customer')
            ->latest('issued_at')
            ->get();
        $gross = (int) $payments->sum(fn (Invoice $invoice): int => (int) $invoice->payments->sum('amount'));
        $refundTotal = (int) $refunds->sum('amount');
        $stats = [
            'gross' => $gross,
            'refund' => $refundTotal,
            'net' => $gross - $refundTotal,
            'paid' => $payments->count(),
            'refundCount' => $refunds->count(),
            'pending' => $invoices->where('status', 'pending')->count(),
            'void' => $invoices->where('status', 'void')->count(),
        ];

        return view('admin.print.shift-report', compact('from', 'to', 'payments', 'refunds', 'invoices', 'stats'));
    }
}
