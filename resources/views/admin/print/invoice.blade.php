<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->code }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #17202a; }
        body { margin: 0; background: #f3f4f6; }
        .sheet { max-width: 780px; margin: 24px auto; padding: 36px; background: #fff; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 16px; }
        button, .print-button { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 14px; background: #fff; cursor: pointer; color: inherit; font: inherit; text-decoration: none; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 4px; font-size: 24px; }
        h2 { margin: 26px 0 10px; font-size: 15px; }
        .muted { color: #64748b; font-size: 13px; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #0f766e; padding-bottom: 18px; }
        .meta { text-align: right; font-size: 13px; line-height: 1.7; }
        .status { display: inline-block; margin-top: 8px; padding: 4px 8px; border-radius: 999px; background: #e2e8f0; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 22px; }
        .box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .box strong { display: block; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { padding: 9px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th:last-child, td:last-child { text-align: right; }
        .total { display: flex; justify-content: flex-end; gap: 28px; margin-top: 16px; font-size: 16px; font-weight: 700; }
        .note { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px; }
        @media print { body { background: #fff; } .sheet { margin: 0; max-width: none; padding: 0; } .no-print { display: none !important; } }
        @media (max-width: 640px) { .sheet { margin: 0; padding: 20px; } .header, .grid { display: block; } .meta { margin-top: 12px; text-align: left; } .box + .box { margin-top: 12px; } }
    </style>
</head>
<body>
<main class="sheet">
    <div class="toolbar no-print">
        <x-ui.button variant="secondary" href="{{ route('invoices') }}" class="print-button">Quay lại</x-ui.button>
        <x-ui.button variant="primary" type="button" class="print-button" onclick="window.print()">In chứng từ</x-ui.button>
    </div>
    <header class="header">
        <div>
            <h1>{{ config('app.name') }}</h1>
            <p class="muted">{{ config('spa.address') }}</p>
            <strong>{{ $invoice->status === 'refunded' ? 'PHIẾU HOÀN TIỀN' : 'PHIẾU THU' }}</strong>
        </div>
        <div class="meta">
            <div><strong>{{ $invoice->code }}</strong></div>
            <div>Lập lúc: {{ $invoice->issued_at?->format('H:i d/m/Y') ?? $invoice->created_at?->format('H:i d/m/Y') }}</div>
            <span class="status">{{ ['pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'void' => 'Đã hủy', 'refunded' => 'Đã hoàn tiền'][$invoice->status] ?? $invoice->status }}</span>
        </div>
    </header>
    <section class="grid">
        <div class="box"><strong>Khách hàng</strong>{{ $invoice->customer->name }}<br><span class="muted">{{ $invoice->customer->phone }}{{ $invoice->customer->email ? ' · '.$invoice->customer->email : '' }}</span></div>
        <div class="box"><strong>Dịch vụ / lịch hẹn</strong>{{ $invoice->items->first()?->description ?? '—' }}@if($invoice->appointment)<br><span class="muted">{{ $invoice->appointment->code }} · {{ $invoice->appointment->appointment_date->format('d/m/Y') }}</span>@endif</div>
    </section>
    <h2>Chi tiết chứng từ</h2>
    <x-ui.table>
        <x-slot:header>
            <th>Nội dung</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th>
        </x-slot:header>
        @foreach($invoice->items as $item)
            <tr><td>{{ $item->description }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_price, 0, ',', '.') }} ₫</td><td>{{ number_format($item->line_total, 0, ',', '.') }} ₫</td></tr>
        @endforeach
    </x-ui.table>
    <div class="total"><span>Tổng phải thu</span><span>{{ number_format($invoice->subtotal - $invoice->discount_amount, 0, ',', '.') }} ₫</span></div>
    @if($invoice->payments->isNotEmpty())
        <h2>Thanh toán</h2>
        <x-ui.table>
            <x-slot:header>
                <th>Mã phiếu</th><th>Phương thức</th><th>Thời điểm</th><th>Số tiền</th>
            </x-slot:header>
        @foreach($invoice->payments as $payment)<tr><td>{{ $payment->receipt_code }}</td><td>{{ ['cash' => 'Tiền mặt', 'vietqr' => 'VietQR', 'pos' => 'POS'][$payment->method] ?? $payment->method }}</td><td>{{ $payment->paid_at->format('H:i d/m/Y') }}</td><td>{{ number_format($payment->amount, 0, ',', '.') }} ₫</td></tr>@endforeach
        </x-ui.table>
    @endif
    @if($invoice->refunds->isNotEmpty())
        <h2>Hoàn tiền</h2>
        @foreach($invoice->refunds as $refund)
            <div class="box"><strong>{{ $refund->code }} · {{ number_format($refund->amount, 0, ',', '.') }} ₫</strong>{{ $refund->reason }}<br><span class="muted">{{ $refund->refunded_at->format('H:i d/m/Y') }} · {{ ['cash' => 'Tiền mặt', 'vietqr' => 'VietQR', 'pos' => 'POS'][$refund->method] ?? $refund->method }}</span></div>
        @endforeach
    @endif
    <p class="note">Chứng từ được tạo từ hệ thống {{ config('app.name') }}. Mọi điều chỉnh sau thanh toán phải có phiếu hoàn và lý do kiểm toán.</p>
</main>
</body>
</html>
