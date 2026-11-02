<div class="spa-payroll-page">
    @error('period')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror
    @error('adjustment')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.page-heading title="Bảng lương & hoa hồng" description="Tính lương theo kỳ, theo dõi hoa hồng và khóa dữ liệu kế toán.">
        <x-slot:actions>
            <x-ui.input type="date" wire:model="periodStart" aria-label="Ngày bắt đầu" />
            <x-ui.input type="date" wire:model="periodEnd" aria-label="Ngày kết thúc" />
            <x-ui.button variant="secondary" icon="download" loading="exportPayroll" wire:click="exportPayroll">Xuất CSV</x-ui.button>
            <x-ui.button icon="calculate" loading="generatePayroll" wire:click="generatePayroll">Tính bảng lương</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="spa-grid spa-grid-3 spa-summary-strip spa-summary-strip--3 spa-payroll-summary">
        <div class="spa-card spa-stat"><div class="spa-stat-label">Tổng quỹ lương</div><div class="spa-stat-value">{{ number_format($stats['base'], 0, ',', '.') }} ₫</div><div class="spa-stat-note">Lương cứng nhân sự hoạt động</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Hoa hồng dịch vụ</div><div class="spa-stat-value">{{ number_format($stats['commission'], 0, ',', '.') }} ₫</div><div class="spa-stat-note">Theo các entry đã phát sinh</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Thực lĩnh dự kiến</div><div class="spa-stat-value">{{ number_format($stats['net'], 0, ',', '.') }} ₫</div><div class="spa-stat-note">{{ $period?->status === 'locked' ? 'Đã khóa dữ liệu' : 'Bản nháp có thể tính lại' }}</div></div>
    </div>

    <section class="spa-card spa-payroll-period" style="margin-bottom:18px">
        <div class="spa-card-header">
            <div>
                <h2>Kỳ lương hiện tại</h2>
                <p>{{ $period ? $period->code . ' · ' . $period->period_start->format('d/m/Y') . ' – ' . $period->period_end->format('d/m/Y') : 'Chưa tính kỳ lương' }}</p>
            </div>
            <div class="spa-actions">
                @if ($period)
                    <x-ui.badge :variant="$period->status === 'locked' ? 'success' : 'warning'">{{ $period->status === 'locked' ? 'Đã chốt kỳ lương' : 'Bản nháp' }}</x-ui.badge>
                    @if ($period->status !== 'locked')
                        <x-ui.button variant="secondary" icon="tune" wire:click="newAdjustment">Tạo phiếu điều chỉnh</x-ui.button>
                        <x-ui.button icon="lock" wire:click="lockPayroll">Chốt & khóa</x-ui.button>
                    @endif
                @endif
            </div>
        </div>

        @if ($showAdjustmentForm)
            <form wire:submit="createAdjustment" class="spa-card-body" style="border-bottom:1px solid var(--spa-border)">
                <div class="spa-grid spa-grid-3">
                    <x-ui.select label="Nhân viên" wire:model="adjustmentEmployeeId" placeholder="Chọn nhân viên" required>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->code }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Loại điều chỉnh" wire:model="adjustmentType">
                        <option value="bonus">Thưởng / tips</option>
                        <option value="deduction">Khấu trừ</option>
                    </x-ui.select>
                    <x-ui.input label="Số tiền (₫)" type="number" min="1" wire:model="adjustmentAmount" required />
                    <x-ui.textarea class="md:col-span-3" label="Lý do" wire:model="adjustmentReason" required />
                </div>
                <div class="spa-actions" style="justify-content:flex-end;margin-top:14px">
                    <x-ui.button variant="secondary" wire:click="$set('showAdjustmentForm', false)">Hủy</x-ui.button>
                    <x-ui.button type="submit">Lưu phiếu</x-ui.button>
                </div>
            </form>
        @endif

        @if ($period)
            <x-ui.table mobile-cards>
                <x-slot:header>
                    <th>Mã NV</th>
                    <th>Tên nhân viên</th>
                    <th>Lương cứng</th>
                    <th>Ngày công</th>
                    <th>Hoa hồng</th>
                    <th>Thưởng / tips</th>
                    <th>Khấu trừ</th>
                    <th>Thực lĩnh</th>
                    <th>Trạng thái</th>
                </x-slot:header>

                @foreach ($period->lines as $line)
                    <tr>
                        <td data-label="Mã NV">{{ $line->employee->code }}</td>
                        <td data-label="Tên nhân viên"><strong>{{ $line->employee->name }}</strong><br><span class="spa-muted spa-small">{{ $line->employee->job_title }}</span></td>
                        <td data-label="Lương cứng">{{ number_format($line->base_salary, 0, ',', '.') }} ₫</td>
                        <td data-label="Ngày công">{{ $line->work_days }} công</td>
                        <td data-label="Hoa hồng">{{ number_format($line->commission_amount, 0, ',', '.') }} ₫</td>
                        <td data-label="Thưởng / tips">{{ number_format($line->tips, 0, ',', '.') }} ₫</td>
                        <td data-label="Khấu trừ">{{ number_format($line->deductions, 0, ',', '.') }} ₫</td>
                        <td data-label="Thực lĩnh"><strong>{{ number_format($line->net_pay, 0, ',', '.') }} ₫</strong></td>
                        <td data-label="Trạng thái"><x-ui.badge :variant="$line->status === 'locked' ? 'success' : 'warning'">{{ $line->status === 'locked' ? 'Đã khóa' : 'Bản nháp' }}</x-ui.badge></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @else
            <x-ui.empty-state title="Chưa có dữ liệu kỳ lương" description="Chọn kỳ và bấm “Tính bảng lương” để tạo dữ liệu." icon="payments" />
        @endif
    </section>

    @if ($period)
        <section class="spa-card spa-payroll-adjustments" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>Phiếu điều chỉnh đang xử lý</h2>
                    <p>Phiếu phải được duyệt hoặc từ chối trước khi chốt kỳ.</p>
                </div>
            </div>

            <x-ui.table mobile-cards>
                <x-slot:header>
                    <th>Mã phiếu</th>
                    <th>Nhân viên</th>
                    <th>Loại</th>
                    <th>Số tiền</th>
                    <th>Lý do</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </x-slot:header>

                @forelse ($period->adjustments as $adjustment)
                    <tr>
                        <td data-label="Mã phiếu"><strong>{{ $adjustment->code }}</strong></td>
                        <td data-label="Nhân viên">{{ $adjustment->employee->name }}</td>
                        <td data-label="Loại">{{ $adjustment->type === 'bonus' ? 'Thưởng / tips' : 'Khấu trừ' }}</td>
                        <td data-label="Số tiền">{{ number_format($adjustment->amount, 0, ',', '.') }} ₫</td>
                        <td data-label="Lý do">{{ $adjustment->reason }}</td>
                        <td data-label="Trạng thái"><x-ui.badge :variant="$adjustment->status === 'approved' ? 'success' : ($adjustment->status === 'rejected' ? 'danger' : 'warning')">{{ ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'][$adjustment->status] ?? $adjustment->status }}</x-ui.badge></td>
                        <td data-label="Thao tác">
                            @if ($adjustment->status === 'pending' && $period->status !== 'locked')
                                <div class="spa-actions">
                                    <x-ui.button size="sm" wire:click="reviewAdjustment({{ $adjustment->id }}, true)">Duyệt</x-ui.button>
                                    <x-ui.button size="sm" variant="danger" wire:click="reviewAdjustment({{ $adjustment->id }}, false)">Từ chối</x-ui.button>
                                </div>
                            @else
                                <span class="spa-muted spa-small">Đã xử lý</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><x-ui.empty-state title="Chưa có phiếu điều chỉnh" icon="tune" /></td>
                    </tr>
                @endforelse
            </x-ui.table>
        </section>
    @endif

    <section class="spa-card spa-payroll-history">
        <div class="spa-card-header">
            <div>
                <h2>Lịch sử kỳ lương</h2>
                <p>Chỉ kỳ bản nháp mới được tính lại.</p>
            </div>
        </div>

        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Mã kỳ</th>
                <th>Thời gian</th>
                <th>Tổng quỹ</th>
                <th>Hoa hồng</th>
                <th>Thực lĩnh</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </x-slot:header>

            @forelse ($periods as $item)
                <tr>
                    <td data-label="Mã kỳ"><strong>{{ $item->code }}</strong></td>
                    <td data-label="Thời gian">{{ $item->period_start->format('d/m/Y') }} – {{ $item->period_end->format('d/m/Y') }}</td>
                    <td data-label="Tổng quỹ">{{ number_format($item->total_base_salary, 0, ',', '.') }} ₫</td>
                    <td data-label="Hoa hồng">{{ number_format($item->total_commission, 0, ',', '.') }} ₫</td>
                    <td data-label="Thực lĩnh">{{ number_format($item->total_net_pay, 0, ',', '.') }} ₫</td>
                    <td data-label="Trạng thái"><x-ui.badge :variant="$item->status === 'locked' ? 'success' : 'warning'">{{ $item->status === 'locked' ? 'Đã chốt' : 'Bản nháp' }}</x-ui.badge></td>
                    <td data-label="Thao tác"><x-ui.button size="sm" variant="secondary" wire:click="$set('periodId', {{ $item->id }})">Xem chi tiết</x-ui.button></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><x-ui.empty-state title="Chưa có kỳ lương" description="Tạo kỳ lương đầu tiên từ bộ chọn ngày ở đầu trang." icon="payments" /></td>
                </tr>
            @endforelse
        </x-ui.table>
    </section>
</div>
