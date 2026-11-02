<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sổ giao ca · {{ config('app.name') }}</title>
    <style>
        :root { font-family: Arial, sans-serif; color: #17202a; }
        body { margin: 0; background: #f3f4f6; }
        .sheet { max-width: 980px; margin: 24px auto; padding: 32px; background: #fff; }
        .toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 22px; }
        .toolbar form { display: flex; gap: 8px; align-items: end; }
        label { display: grid; gap: 4px; color: #64748b; font-size: 12px; } input, button { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; background: #fff; } button { cursor: pointer; }
        h1 { margin: 0 0 4px; font-size: 24px; } h2 { margin: 26px 0 8px; font-size: 16px; } .muted { color: #64748b; font-size: 13px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; } .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; } .card span { display: block; color: #64748b; font-size: 12px; } .card strong { display: block; margin-top: 5px; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; } th, td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; } th:last-child, td:last-child { text-align: right; }
        .net { color: #0f766e; } .negative { color: #b91c1c; } .note { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 12px; color: #64748b; }
        @media print { body { background: #fff; } .sheet { margin: 0; max-width: none; padding: 0; } .no-print { display: none !important; } } @media (max-width: 700px) { .summary { grid-template-columns: repeat(2, 1fr); } .toolbar, .toolbar form { display: block; } .toolbar form label, .toolbar form button { margin-top: 8px; } }
    </style>
</head>
<body>
<main class="sheet">
    <div class="toolbar no-print">
        <div><a href="{{ route('invoices') }}">Quay lại hóa đơn</a></div>
        <form method="get" action="{{ route('invoices.shift-report') }}">
            <label for="report-from">Từ ngày<input id="report-from" type="date" name="from" value="{{ $from->toDateString() }}"></label>
            <label for="report-to">Đến ngày<input id="report-to" type="date" name="to" value="{{ $to->toDateString() }}"></label>
            <x-ui.button variant="primary" type="submit">Xem báo cáo</x-ui.button>
            <x-ui.button variant="secondary" type="button" onclick="window.print()">In sổ</x-ui.button>
        </form>
    </div>
    <header>
        <h1>Sổ giao ca · {{ config('app.name') }}</h1>
        <p class="muted">{{ config('spa.address') }} · {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</p>
    </header>
    <section class="summary" style="margin-top:20px">
        <div class="card"><span>Tổng thu</span><strong>{{ number_format($stats['gross'], 0, ',', '.') }} ₫</strong></div>
        <div class="card"><span>Tổng hoàn</span><strong class="negative">{{ number_format($stats['refund'], 0, ',', '.') }} ₫</strong></div>
        <div class="card"><span>Doanh thu thuần</span><strong class="net">{{ number_format($stats['net'], 0, ',', '.') }} ₫</strong></div>
        <div class="card"><span>Phiếu thu / hoàn</span><strong>{{ $stats['paid'] }} / {{ $stats['refundCount'] }}</strong></div>
    </section>
    <h2>Giao dịch đã thu</h2>
    <x-ui.table>
        <x-slot:header>
            <th>Thời điểm</th><th>Mã hóa đơn</th><th>Khách hàng</th><th>Phương thức</th><th>Số tiền</th>
        </x-slot:header>
    @forelse($payments as $invoice)
        @foreach($invoice->payments as $payment)<tr><td>{{ $payment->paid_at->format('H:i d/m/Y') }}</td><td>{{ $invoice->code }}</td><td>{{ $invoice->customer->name }}</td><td>{{ ['cash' => 'Tiền mặt', 'vietqr' => 'VietQR', 'pos' => 'POS'][$payment->method] ?? $payment->method }}</td><td>{{ number_format($payment->amount, 0, ',', '.') }} ₫</td></tr>@endforeach
    @empty
        <tr><td colspan="5">Không có giao dịch thu trong khoảng này.</td></tr>
    @endforelse
    </x-ui.table>
    <h2>Giao dịch hoàn tiền</h2>
    <x-ui.table>
        <x-slot:header>
            <th>Thời điểm</th><th>Mã phiếu hoàn</th><th>Mã hóa đơn</th><th>Lý do</th><th>Số tiền</th>
        </x-slot:header>
        @forelse($refunds as $refund)<tr><td>{{ $refund->refunded_at->format('H:i d/m/Y') }}</td><td>{{ $refund->code }}</td><td>{{ $refund->invoice->code }}</td><td>{{ $refund->reason }}</td><td class="negative">-{{ number_format($refund->amount, 0, ',', '.') }} ₫</td></tr>@empty<tr><td colspan="5">Không có giao dịch hoàn trong khoảng này.</td></tr>@endforelse
    </x-ui.table>
    <h2>Chứng từ cần theo dõi</h2>
    <x-ui.table>
        <x-slot:header>
            <th>Mã hóa đơn</th><th>Khách hàng</th><th>Ngày lập</th><th>Trạng thái</th><th>Số tiền</th>
        </x-slot:header>
        @forelse($invoices->whereIn('status', ['pending', 'void']) as $invoice)<tr><td>{{ $invoice->code }}</td><td>{{ $invoice->customer->name }}</td><td>{{ $invoice->issued_at?->format('d/m/Y') }}</td><td>{{ $invoice->status === 'pending' ? 'Chờ thanh toán' : 'Đã hủy' }}</td><td>{{ number_format($invoice->subtotal - $invoice->discount_amount, 0, ',', '.') }} ₫</td></tr>@empty<tr><td colspan="5">Không có chứng từ chờ xử lý.</td></tr>@endforelse
    </x-ui.table>
    <p class="note">Báo cáo tính doanh thu thuần = tổng thanh toán đã ghi nhận − tổng phiếu hoàn trong khoảng thời gian. Phiếu hủy không được tính vào doanh thu.</p>
</main>
</body>
</html>
