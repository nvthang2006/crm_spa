<div class="spa-invoices-page">
    @error('invoice')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.page-heading title="Hóa đơn & dòng tiền doanh thu" description="Tách bạch tiền thực thu, hoàn tiền và chứng từ sử dụng thẻ liệu trình.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('invoices.shift-report', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" icon="print">In sổ giao ca</x-ui.button>
            <x-ui.button variant="secondary" icon="download" loading="exportInvoices" wire:click="exportInvoices">Xuất CSV</x-ui.button>
            <x-ui.button icon="point_of_sale" wire:click="newInvoice">Lập hóa đơn mới</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="spa-grid spa-grid-3 spa-summary-strip spa-summary-strip--3 spa-invoices-summary">
        <div class="spa-card spa-stat"><div class="spa-stat-label">Tổng thực thu hôm nay</div><div class="spa-stat-value">{{ number_format($stats['today'], 0, ',', '.') }} ₫</div><div class="spa-stat-note">Theo phiếu đã thanh toán</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Phiếu chờ thanh toán</div><div class="spa-stat-value">{{ $stats['pending'] }}</div><div class="spa-stat-note">Cần xử lý tại thu ngân</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Gói bán hôm nay</div><div class="spa-stat-value">{{ $stats['package'] }}</div><div class="spa-stat-note">Không tính là lượt trừ thẻ</div></div>
    </div>

    @if ($showForm)
        <section class="spa-card spa-context-form" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>Lập phiếu thu mới</h2>
                    <p>Giá dịch vụ được snapshot tại thời điểm lập.</p>
                </div>
                <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('showForm', false)" />
            </div>

            <form wire:submit="createInvoice" class="spa-card-body">
                <div class="spa-grid spa-grid-2">
                    <x-ui.select label="Khách hàng" wire:model="customerId" placeholder="Chọn khách" required>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Dịch vụ / gói" wire:model="offeringId" placeholder="Chọn dịch vụ" required>
                        @foreach ($offerings as $offering)
                            <option value="{{ $offering->id }}">{{ $offering->name }} · {{ number_format($offering->price ?? 0, 0, ',', '.') }} ₫</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="KTV / tư vấn viên (tùy chọn)" wire:model="employeeId" placeholder="Chưa gán">
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->job_title }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Liên kết lịch hẹn (tùy chọn)" wire:model="appointmentId" placeholder="Không liên kết">
                        @foreach ($appointments as $appointment)
                            <option value="{{ $appointment->id }}">{{ $appointment->code }} · {{ $appointment->customer->name }} · {{ $appointment->appointment_date->format('d/m') }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input label="Ưu đãi / giảm trừ (₫)" type="number" min="0" wire:model="discountAmount" />
                </div>

                <div class="spa-actions" style="justify-content:flex-end;margin-top:18px">
                    <x-ui.button variant="secondary" wire:click="$set('showForm', false)">Hủy</x-ui.button>
                    <x-ui.button type="submit">Lưu phiếu thu</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    @if ($voidingId)
        <section class="spa-card spa-context-form spa-context-form-danger" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>Hủy hóa đơn</h2>
                    <p>Thao tác sẽ khóa chứng từ và lưu lý do vào nhật ký kiểm toán.</p>
                </div>
                <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('voidingId', null)" />
            </div>

            <form wire:submit="voidInvoice({{ $voidingId }})" class="spa-card-body">
                <x-ui.textarea label="Lý do hủy" wire:model="voidReason" required />
                <div class="spa-actions" style="justify-content:flex-end;margin-top:14px">
                    <x-ui.button variant="secondary" wire:click="$set('voidingId', null)">Hủy</x-ui.button>
                    <x-ui.button type="submit" variant="danger">Xác nhận hủy hóa đơn</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    @if ($refundingId)
        <section class="spa-card spa-context-form spa-context-form-danger" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>Hoàn tiền toàn bộ</h2>
                    <p>Chỉ hóa đơn chưa có hoa hồng kỳ lương khóa và gói chưa dùng mới được hoàn.</p>
                </div>
                <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('refundingId', null)" />
            </div>

            <form wire:submit="refundInvoice({{ $refundingId }})" class="spa-card-body">
                <div class="spa-grid spa-grid-2">
                    <x-ui.select label="Phương thức hoàn" wire:model="refundMethod">
                        <option value="cash">Tiền mặt</option>
                        <option value="vietqr">VietQR</option>
                        <option value="pos">POS</option>
                    </x-ui.select>
                    <x-ui.input label="Mã giao dịch (nếu không phải tiền mặt)" wire:model="refundReference" placeholder="Mã giao dịch hoàn" />
                    <x-ui.textarea class="md:col-span-2" label="Lý do hoàn tiền" wire:model="refundReason" required />
                </div>
                <div class="spa-actions" style="justify-content:flex-end;margin-top:14px">
                    <x-ui.button variant="secondary" wire:click="$set('refundingId', null)">Hủy</x-ui.button>
                    <x-ui.button type="submit" variant="danger">Xác nhận hoàn tiền</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    <x-ui.alert variant="info" class="spa-invoice-payment-note !p-2.5 flex flex-wrap items-center gap-2.5">
        <span>Với VietQR hoặc POS, nhập mã giao dịch trước khi bấm “Thu tiền” hoặc “Hoàn tiền” (không bắt buộc với tiền mặt).</span>
        <x-ui.input class="max-w-[180px]" wire:model="paymentReference" aria-label="Mã giao dịch dùng chung" placeholder="Mã giao dịch dùng chung" />
    </x-ui.alert>

    <section class="spa-card spa-invoice-list">
        <div class="spa-card-header">
            <div>
                <h2>Sổ phiếu thu</h2>
                <p>{{ $invoices->total() }} chứng từ theo khoảng ngày và bộ lọc hiện tại</p>
            </div>
        </div>
        <div class="spa-filters">
            <x-ui.input class="min-w-[300px]" type="search" wire:model.live.debounce.300ms="search" aria-label="Tìm hóa đơn" placeholder="Tìm mã hóa đơn, tên khách, SĐT…" />
            <x-ui.input type="date" wire:model.live="dateFrom" aria-label="Từ ngày" />
            <x-ui.input type="date" wire:model.live="dateTo" aria-label="Đến ngày" />
            <x-ui.select class="min-w-[170px]" wire:model.live="methodFilter" aria-label="Lọc phương thức thanh toán">
                <option value="all">Tất cả phương thức</option>
                <option value="cash">Tiền mặt</option>
                <option value="vietqr">VietQR</option>
                <option value="pos">POS</option>
            </x-ui.select>
            <x-ui.select class="min-w-[170px]" wire:model.live="statusFilter" aria-label="Lọc trạng thái hóa đơn">
                <option value="all">Tất cả trạng thái</option>
                <option value="pending">Chờ thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="void">Đã hủy</option>
                <option value="refunded">Đã hoàn tiền</option>
            </x-ui.select>
        </div>

        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Mã chứng từ</th>
                <th>Giờ lập</th>
                <th>Khách hàng</th>
                <th>Loại giao dịch</th>
                <th>Phương thức</th>
                <th>Tiền thực thu</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </x-slot:header>

            @forelse ($invoices as $invoice)
                <tr>
                    <td data-label="Mã chứng từ"><strong>{{ $invoice->code }}</strong></td>
                    <td data-label="Giờ lập">{{ $invoice->issued_at?->format('H:i d/m/Y') ?? $invoice->created_at->format('H:i d/m/Y') }}</td>
                    <td data-label="Khách hàng"><strong>{{ $invoice->customer->name }}</strong><br><span class="spa-muted spa-small">{{ $invoice->customer->phone }}</span></td>
                    <td data-label="Loại giao dịch">{{ $invoice->type === 'package' ? 'Bán gói liệu trình' : 'Dịch vụ lẻ' }}</td>
                    <td data-label="Phương thức">{{ ['cash' => 'Tiền mặt', 'vietqr' => 'VietQR', 'pos' => 'POS'][$invoice->payment_method] ?? '—' }}</td>
                    <td data-label="Tiền thực thu"><strong>{{ number_format($invoice->total_received ?: max(0, $invoice->subtotal - $invoice->discount_amount), 0, ',', '.') }} ₫</strong></td>
                    <td data-label="Trạng thái">
                        <x-ui.badge :variant="in_array($invoice->status, ['paid', 'refunded']) ? 'success' : ($invoice->status === 'void' ? 'danger' : 'warning')">
                            {{ ['pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'void' => 'Đã hủy', 'refunded' => 'Đã hoàn tiền', 'draft' => 'Bản nháp'][$invoice->status] ?? $invoice->status }}
                        </x-ui.badge>
                    </td>
                    <td data-label="Thao tác">
                        @if ($invoice->status === 'pending')
                            <div class="spa-actions">
                                <x-ui.select id="payment-method-{{ $invoice->id }}" class="min-w-[130px]" wire:model="paymentMethod" aria-label="Phương thức thu tiền">
                                    <option value="cash">Tiền mặt</option>
                                    <option value="vietqr">VietQR</option>
                                    <option value="pos">POS</option>
                                </x-ui.select>
                                <x-ui.button size="sm" wire:click="payInvoice({{ $invoice->id }})">Thu tiền</x-ui.button>
                                <x-ui.button size="sm" variant="danger" wire:click="openVoid({{ $invoice->id }})">Hủy</x-ui.button>
                            </div>
                        @elseif ($invoice->status === 'paid')
                            <div class="spa-actions">
                                <x-ui.button size="sm" variant="secondary" href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener">In phiếu</x-ui.button>
                                <x-ui.button size="sm" variant="danger" wire:click="openRefund({{ $invoice->id }})">Hoàn tiền</x-ui.button>
                            </div>
                        @elseif ($invoice->status === 'refunded')
                            <x-ui.button size="sm" variant="secondary" href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener">Xem phiếu</x-ui.button>
                        @else
                            <span class="spa-muted spa-small">Đã khóa</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-ui.empty-state title="Chưa có chứng từ phù hợp" description="Điều chỉnh bộ lọc hoặc lập phiếu thu mới." icon="receipt_long" />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div class="spa-pagination">
            <span class="spa-pagination-info">Hiển thị <strong>{{ $invoices->firstItem() ?? 0 }}</strong>–<strong>{{ $invoices->lastItem() ?? 0 }}</strong> trên <strong>{{ $invoices->total() }}</strong> hóa đơn</span>
            {{ $invoices->links() }}
        </div>
    </section>
</div>
