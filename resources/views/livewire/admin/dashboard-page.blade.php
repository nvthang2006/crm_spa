<div class="spa-dashboard-page space-y-6">
    <x-ui.page-heading
        title="Tổng quan vận hành"
        :description="'Dữ liệu hoạt động của cơ sở ' . config('spa.address') . ' · ' . $date->translatedFormat('l, d/m/Y')"
    >
        <x-slot:actions>
            <x-ui.input type="date" wire:model.live="selectedDate" aria-label="Ngày xem báo cáo" />
            <x-ui.button href="{{ route('daily-revenue') }}" icon="edit_note">Nhập ca làm cuối ngày</x-ui.button>
            <x-ui.button href="{{ route('appointments') }}" variant="secondary" icon="calendar_month">Lịch hẹn</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    {{-- DẢI KPI TRỌNG TÂM --}}
    <div class="spa-grid spa-grid-4 spa-summary-strip spa-summary-strip--4 spa-dashboard-kpis">
        <div class="spa-card spa-stat">
            <span class="spa-stat-icon material-symbols-outlined text-emerald-600" aria-hidden="true">payments</span>
            <div class="spa-stat-label">Doanh thu ngày</div>
            <div class="spa-stat-value text-emerald-700">{{ number_format($revenueToday, 0, ',', '.') }} ₫</div>
            <div class="spa-stat-note">Chỉ tính phiếu đã thanh toán</div>
        </div>
        <div class="spa-card spa-stat">
            <span class="spa-stat-icon material-symbols-outlined text-amber-600" aria-hidden="true">account_balance_wallet</span>
            <div class="spa-stat-label">Hoa hồng KTV hôm nay</div>
            <div class="spa-stat-value text-amber-700">{{ number_format($commissionToday, 0, ',', '.') }} ₫</div>
            <div class="spa-stat-note">Lũy kế tháng: {{ number_format($commissionMonth, 0, ',', '.') }} ₫</div>
        </div>
        <div class="spa-card spa-stat">
            <span class="spa-stat-icon material-symbols-outlined text-sky-600" aria-hidden="true">spa</span>
            <div class="spa-stat-label">Ca phục vụ hôm nay</div>
            <div class="spa-stat-value text-sky-800">{{ $sessionsRetailToday + $sessionsPackageToday }} ca</div>
            <div class="spa-stat-note">{{ $sessionsRetailToday }} khách lẻ · {{ $sessionsPackageToday }} ca làm gói</div>
        </div>
        <div class="spa-card spa-stat">
            <span class="spa-stat-icon material-symbols-outlined text-indigo-600" aria-hidden="true">group</span>
            <div class="spa-stat-label">Khách đã thanh toán</div>
            <div class="spa-stat-value">{{ $customersToday }} khách</div>
            <div class="spa-stat-note">Mỗi khách tính một lần · phiếu đã thanh toán</div>
        </div>
    </div>

    {{-- BIỂU ĐỒ DOANH THU & TOP KTV --}}
    <div class="spa-grid spa-grid-dashboard spa-dashboard-analytics" @if (! config('spa.resource_management_enabled')) style="grid-template-columns:minmax(0, 2fr) minmax(300px, 1fr)" @endif>
        <section class="spa-card">
            <div class="spa-card-header">
                <div>
                    <h2>Xu hướng doanh thu</h2>
                    <p>{{ $trendDays }} ngày gần nhất · tiền thực thu</p>
                </div>
                <div class="spa-actions">
                    <x-ui.button size="sm" :variant="$trendDays === 7 ? 'primary' : 'secondary'" wire:click="$set('trendDays', 7)">7 ngày</x-ui.button>
                    <x-ui.button size="sm" :variant="$trendDays === 30 ? 'primary' : 'secondary'" wire:click="$set('trendDays', 30)">30 ngày</x-ui.button>
                </div>
            </div>
            <div class="spa-card-body">
                @php($maxTrend = max(1, $trend->max()))
                <div class="spa-chart-wrap">
                    <div class="spa-chart {{ $trendDays > 14 ? 'spa-chart-dense' : '' }}">
                        @foreach ($trend as $label => $amount)
                            @php($isToday = $loop->last)
                            @php($showLabel = ($trendDays <= 10) || $loop->first || $loop->last || ($loop->iteration % 5 === 0))
                            <div class="spa-bar {{ $isToday ? 'is-today' : '' }}" style="height:{{ max(8, (int) (($amount / $maxTrend) * 170)) }}px" title="{{ $label }}: {{ number_format($amount, 0, ',', '.') }} ₫">
                                @if ($showLabel)
                                    <span>{{ $isToday ? 'Hôm nay' : $label }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="spa-kpi-inline" style="margin-top:16px">
                    <strong>{{ number_format($trend->sum(), 0, ',', '.') }} ₫</strong>
                    <small>tổng {{ $trendDays }} ngày</small>
                </div>
            </div>
        </section>

        @if (config('spa.resource_management_enabled'))
        <section class="spa-card">
            <div class="spa-card-header">
                <div>
                    <h2>Trạng thái phòng nhanh</h2>
                    <p>{{ $resources->count() }} tài nguyên đang theo dõi</p>
                </div>
                <x-ui.button variant="ghost" size="sm" href="{{ route('rooms') }}">Xem sơ đồ →</x-ui.button>
            </div>
            <div class="spa-card-body">
                <div class="spa-room-legend" aria-label="Chú thích trạng thái phòng">
                    <span class="spa-legend-item spa-legend-busy" title="Đang có lịch hẹn phục vụ"><span class="spa-legend-dot"></span> Đang dùng ({{ $resources->where('status', 'busy')->count() }})</span>
                    <span class="spa-legend-item spa-legend-ready" title="Sẵn sàng đón khách"><span class="spa-legend-dot"></span> Trống ({{ $resources->where('status', 'ready')->count() }})</span>
                    <span class="spa-legend-item spa-legend-cleaning" title="Cần thay drap & dọn dẹp"><span class="spa-legend-dot"></span> Chờ dọn ({{ $resources->where('status', 'cleaning')->count() }})</span>
                    <span class="spa-legend-item spa-legend-maintenance" title="Đang bảo trì tạm ngưng"><span class="spa-legend-dot"></span> Bảo trì ({{ $resources->where('status', 'maintenance')->count() }})</span>
                </div>

                <div class="spa-mini-room-list">
                    @foreach ($areas as $area)
                        <div class="spa-mini-room">
                            <div class="spa-mini-room-info">
                                <h4>{{ $area->name }}{{ $area->floor ? ' (' . $area->floor . ')' : '' }}</h4>
                                <p>{{ $area->resources->count() }} giường / phòng · {{ $area->description }}</p>
                            </div>
                            <div class="spa-bed-group">
                                @foreach ($area->resources as $resource)
                                    <x-ui.badge
                                        shape="badge"
                                        size="sm"
                                        :variant="$resource->status === 'busy' ? 'info' : ($resource->status === 'ready' ? 'success' : ($resource->status === 'cleaning' ? 'warning' : 'danger'))"
                                        class="spa-bed-badge {{ $resource->status_class }}"
                                        title="{{ $resource->name }} ({{ $resource->code }}): {{ $resource->status_text }}"
                                    >{{ $resource->short_code }}</x-ui.badge>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="spa-room-footer">
                    <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>
                    <span>Hiện có <strong>{{ $resources->where('status', 'ready')->count() }} / {{ $resources->count() }}</strong> tài nguyên sẵn sàng tiếp đón khách mới</span>
                </div>
            </div>
        </section>
        @else
        <div class="spa-grid spa-dashboard-side" style="gap:18px">
            <section class="spa-card">
                <div class="spa-card-header">
                    <div>
                        <h2>Top KTV tháng này</h2>
                        <p>Xếp theo hoa hồng tạm tính</p>
                    </div>
                </div>
                <div class="spa-card-body">
                    <div class="spa-list">
                        @forelse ($topEmployees as $index => $employee)
                            <div class="spa-list-item"><span><strong>{{ $index + 1 }}. {{ $employee->name }}</strong><br><small class="spa-muted">{{ $employee->job_title }}</small></span><strong class="text-amber-700">{{ number_format($employee->month_commission ?? 0, 0, ',', '.') }} ₫</strong></div>
                        @empty
                            <x-ui.empty-state title="Chưa có dữ liệu hoa hồng" icon="payments" />
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="spa-card spa-dashboard-alerts">
                <div class="spa-card-header">
                    <div>
                        <h2>Cảnh báo thẻ liệu trình</h2>
                        <p>Khách sắp hết buổi</p>
                    </div>
                </div>
                <div class="spa-card-body">
                    <div class="spa-list">
                        <div class="spa-list-item"><span><x-ui.badge variant="warning">Thẻ sắp hết buổi</x-ui.badge></span><strong>{{ $alerts['packages']->count() }}</strong></div>
                    </div>
                </div>
            </section>
        </div>
        @endif
    </div>

    {{-- BẢNG NĂNG SUẤT & HOA HỒNG NHÂN VIÊN TRONG NGÀY --}}
    <section class="spa-card">
        <div class="spa-card-header">
            <div>
                <h2>Năng suất & Hoa hồng nhân viên hôm nay</h2>
                <p>Chi tiết số ca làm, doanh số và hoa hồng nhận được theo từng KTV trong ngày</p>
            </div>
            <x-ui.button variant="secondary" size="sm" href="{{ route('daily-revenue') }}" icon="add">Nhập thêm ca</x-ui.button>
        </div>
        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Nhân viên</th>
                <th>Chức danh</th>
                <th style="text-align:center">Số ca làm</th>
                <th style="text-align:center">Phân loại ca</th>
                <th style="text-align:right">Doanh số đem lại</th>
                <th style="text-align:right">Hoa hồng nhận hôm nay</th>
            </x-slot:header>
            @forelse ($staffPerformanceToday as $staff)
                <tr>
                    <td data-label="Nhân viên"><strong>{{ $staff['name'] }}</strong></td>
                    <td data-label="Chức danh"><x-ui.badge variant="neutral">{{ $staff['job_title'] }}</x-ui.badge></td>
                    <td data-label="Số ca làm" style="text-align:center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                            {{ $staff['total_sessions'] }} ca
                        </span>
                    </td>
                    <td data-label="Phân loại ca" style="text-align:center">
                        <span class="text-xs text-slate-600">{{ $staff['retail_sessions'] }} ca lẻ · {{ $staff['package_sessions'] }} ca gói</span>
                    </td>
                    <td data-label="Doanh số đem lại" style="text-align:right">
                        <strong class="tabular-nums">{{ number_format($staff['revenue'], 0, ',', '.') }} ₫</strong>
                    </td>
                    <td data-label="Hoa hồng nhận hôm nay" style="text-align:right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded font-bold text-amber-800 bg-amber-50 border border-amber-200 tabular-nums">
                            {{ number_format($staff['commission'], 0, ',', '.') }} ₫
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><x-ui.empty-state title="Chưa có ca làm nào được ghi nhận hôm nay" description="Bấm 'Nhập ca làm cuối ngày' để ghi nhận danh sách ca phục vụ." icon="badge" /></td>
                </tr>
            @endforelse
        </x-ui.table>
    </section>

    {{-- NHẬT KÝ CA PHỤC VỤ TRONG NGÀY --}}
    <section class="spa-card">
        <div class="spa-card-header">
            <div>
                <h2>Nhật ký ca phục vụ hôm nay</h2>
                <p>Danh sách các ca khách lẻ và khách làm gói đã ghi nhận trong ca</p>
            </div>
            <x-ui.button variant="ghost" size="sm" href="{{ route('daily-revenue') }}">Xem trang nhập liệu →</x-ui.button>
        </div>
        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Giờ</th>
                <th>Khách hàng</th>
                <th>Phân loại</th>
                <th>Dịch vụ / Gói</th>
                <th>KTV thực hiện</th>
                <th style="text-align:right">Tiền thực thu</th>
                <th style="text-align:right">Hoa hồng KTV</th>
            </x-slot:header>
            @forelse ($recentShiftLogs as $log)
                <tr>
                    <td data-label="Giờ"><span class="font-mono text-xs text-slate-500 font-semibold">{{ $log['time'] }}</span></td>
                    <td data-label="Khách hàng">
                        <strong class="text-[13px] text-slate-900">{{ $log['customer_name'] }}</strong>
                        @if ($log['customer_phone'])<br><span class="text-xs text-slate-400 font-mono">{{ $log['customer_phone'] }}</span>@endif
                    </td>
                    <td data-label="Phân loại">
                        <x-ui.badge :variant="$log['is_package'] ? 'info' : 'neutral'">
                            {{ $log['is_package'] ? 'Khách làm gói' : 'Khách lẻ' }}
                        </x-ui.badge>
                    </td>
                    <td data-label="Dịch vụ / Gói">
                        <span class="font-medium text-slate-800 text-[13px]">{{ $log['offering_name'] }}</span>
                    </td>
                    <td data-label="KTV thực hiện">
                        <span class="inline-flex items-center gap-1 font-semibold text-slate-700 text-[13px]">
                            <span class="material-symbols-outlined text-[16px] text-slate-400" aria-hidden="true">person</span>
                            {{ $log['employee_name'] }}
                        </span>
                    </td>
                    <td data-label="Tiền thực thu" style="text-align:right">
                        <strong class="tabular-nums {{ $log['amount'] == 0 ? 'text-slate-400 font-normal' : 'text-slate-900' }}">
                            {{ number_format($log['amount'], 0, ',', '.') }} ₫
                        </strong>
                    </td>
                    <td data-label="Hoa hồng KTV" style="text-align:right">
                        <span class="font-bold text-amber-700 tabular-nums">
                            {{ number_format($log['commission'], 0, ',', '.') }} ₫
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><x-ui.empty-state title="Chưa có ca làm nào trong ngày" description="Các ca phục vụ nhập cuối ngày sẽ xuất hiện ở đây." icon="receipt_long" /></td>
                </tr>
            @endforelse
        </x-ui.table>
    </section>

    @if ($appointments->isNotEmpty())
        <section class="spa-card">
            <div class="spa-card-header">
                <div>
                    <h2>Lịch hẹn đặt trước trong ngày</h2>
                    <p>{{ $appointments->count() }} lịch hẹn ghi nhận</p>
                </div>
                <x-ui.button variant="ghost" size="sm" href="{{ route('appointments') }}">Xem tất cả →</x-ui.button>
            </div>
            <x-ui.table mobile-cards>
                <x-slot:header>
                    <th>Giờ</th>
                    <th>Khách hàng</th>
                    <th>Dịch vụ</th>
                    <th>Nhân viên</th>
                    <th>Trạng thái</th>
                </x-slot:header>
                @foreach ($appointments->take(6) as $appointment)
                    <tr>
                        <td data-label="Giờ"><strong>{{ substr($appointment->start_time, 0, 5) }}</strong><br><span class="spa-muted spa-small">{{ substr($appointment->end_time, 0, 5) }}</span></td>
                        <td data-label="Khách hàng"><strong>{{ $appointment->customer->name }}</strong><br><span class="spa-muted spa-small">{{ $appointment->customer->phone }}</span></td>
                        <td data-label="Dịch vụ">{{ $appointment->offering->name }}</td>
                        <td data-label="Nhân viên">{{ $appointment->primaryEmployee?->name ?? 'Chưa gán' }}</td>
                        <td data-label="Trạng thái">
                            <x-ui.badge :variant="in_array($appointment->status, ['paid', 'package_used']) ? 'success' : ($appointment->status === 'in_service' ? 'info' : 'warning')" :icon="false">
                                {{ ['scheduled' => 'Đã đặt', 'arrived' => 'Đã đến', 'in_service' => 'Đang phục vụ', 'completed_pending_payment' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'package_used' => 'Đã dùng thẻ', 'cancelled' => 'Đã hủy'][$appointment->status] ?? $appointment->status }}
                            </x-ui.badge>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </section>
    @endif
</div>
