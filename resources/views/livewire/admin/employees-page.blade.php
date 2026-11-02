<div class="spa-employees-page">
    <x-ui.page-heading title="Nhân viên & chấm công" description="Quản lý biên chế, ca làm và điểm danh thủ công trong ngày.">
        <x-slot:actions>
            <x-ui.input type="date" wire:model.live="attendanceDate" aria-label="Ngày chấm công" />
            <x-ui.button variant="secondary" icon="edit_calendar" wire:click="newSchedule">Phân ca</x-ui.button>
            <x-ui.button variant="secondary" icon="download" loading="exportEmployees" wire:click="exportEmployees">Xuất CSV</x-ui.button>
            <x-ui.button icon="person_add" wire:click="newEmployee">Thêm nhân viên</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="spa-grid spa-grid-4 spa-summary-strip spa-summary-strip--4 spa-employees-summary">
        <div class="spa-card spa-stat"><div class="spa-stat-label">Tổng nhân sự</div><div class="spa-stat-value">{{ $stats['total'] }}</div><div class="spa-stat-note">Hồ sơ trong hệ thống</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Đang làm việc</div><div class="spa-stat-value">{{ $stats['working'] }}</div><div class="spa-stat-note">Trạng thái đang hoạt động</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Đã check-in</div><div class="spa-stat-value">{{ $stats['checkedIn'] }}</div><div class="spa-stat-note">Chưa check-out trong ngày</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Đi trễ</div><div class="spa-stat-value">{{ $stats['late'] }}</div><div class="spa-stat-note">Cần kiểm tra ghi chú</div></div>
    </div>

    @if ($showForm)
        <section class="spa-card spa-context-form" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>{{ $editingId ? 'Sửa hồ sơ nhân viên' : 'Thêm nhân viên mới' }}</h2>
                    <p>Thông tin dùng cho phân ca, chấm công và tính lương.</p>
                </div>
                <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('showForm', false)" />
            </div>

            <form wire:submit="saveEmployee" class="spa-card-body">
                <div class="spa-grid spa-grid-3">
                    <x-ui.input label="Mã nhân viên" wire:model="code" required />
                    <x-ui.input label="Họ và tên" wire:model="name" required />
                    <x-ui.input label="Số điện thoại" type="tel" wire:model="phone" />
                    <x-ui.input label="Chức vụ" wire:model="jobTitle" placeholder="Kỹ thuật viên Facial" required />
                    <x-ui.select label="Ca làm việc" wire:model="shiftName">
                        <option>Ca sáng</option>
                        <option>Ca chiều</option>
                        <option>Ca gãy</option>
                    </x-ui.select>
                    <x-ui.input label="Lương cứng (₫)" type="number" min="0" wire:model="baseSalary" required />
                </div>

                <div class="spa-actions" style="justify-content:flex-end;margin-top:18px">
                    <x-ui.button variant="secondary" wire:click="$set('showForm', false)">Hủy</x-ui.button>
                    <x-ui.button type="submit">Lưu hồ sơ</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    @if ($showScheduleForm)
        <section class="spa-card spa-context-form" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>Phân ca làm việc</h2>
                    <p>Ca được dùng để đối chiếu chấm công và tính ngày công.</p>
                </div>
                <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('showScheduleForm', false)" />
            </div>

            <form wire:submit="saveSchedule" class="spa-card-body">
                <div class="spa-grid spa-grid-3">
                    <x-ui.select label="Nhân viên" wire:model="scheduleEmployeeId" placeholder="Chọn nhân viên" required>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->code }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input label="Ngày làm" type="date" wire:model="scheduleDate" required />
                    <x-ui.select label="Tên ca" wire:model="scheduleShiftName">
                        <option>Ca sáng</option>
                        <option>Ca chiều</option>
                        <option>Ca gãy</option>
                    </x-ui.select>
                    <x-ui.input label="Bắt đầu" type="time" wire:model="scheduleStartsAt" required />
                    <x-ui.input label="Kết thúc" type="time" wire:model="scheduleEndsAt" required />
                    <x-ui.input label="Ghi chú" wire:model="scheduleNotes" />
                </div>

                <div class="spa-actions" style="justify-content:flex-end;margin-top:18px">
                    <x-ui.button variant="secondary" wire:click="$set('showScheduleForm', false)">Hủy</x-ui.button>
                    <x-ui.button type="submit">Lưu ca</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    <section class="spa-card spa-employees-list">
        <div class="spa-card-header">
            <div>
                <h2>Chấm công & nhân sự</h2>
                <p>{{ $employees->total() }} hồ sơ theo bộ lọc hiện tại · ngày {{ \Carbon\Carbon::parse($attendanceDate)->format('d/m/Y') }}</p>
            </div>
        </div>
        <div class="spa-filters">
            <x-ui.input class="min-w-[260px]" type="search" wire:model.live.debounce.300ms="search" aria-label="Tìm nhân viên" placeholder="Tìm tên, mã NV, số điện thoại…" />
            <x-ui.select class="min-w-[220px]" wire:model.live="statusFilter" aria-label="Lọc trạng thái nhân viên">
                <option value="active">Đang hoạt động</option>
                <option value="inactive">Đã tạm ngưng</option>
                <option value="all">Tất cả trạng thái</option>
            </x-ui.select>
        </div>

        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Mã NV</th>
                <th>Họ và tên</th>
                <th>Chức vụ</th>
                <th>Ca làm việc</th>
                <th>Check-in</th>
                <th>Phục vụ</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </x-slot:header>

            @forelse ($employees as $employee)
                @php($attendance = $employee->attendanceRecords->first())
                <tr>
                    <td data-label="Mã NV"><strong>{{ $employee->code }}</strong></td>
                    <td data-label="Họ và tên"><strong>{{ $employee->name }}</strong><br><span class="spa-muted spa-small">{{ $employee->phone }}</span></td>
                    <td data-label="Chức vụ">{{ $employee->job_title }}</td>
                    <td data-label="Ca làm việc">{{ $employee->shift_name }}@if ($employee->schedules->first())<br><span class="spa-muted spa-small">{{ substr($employee->schedules->first()->starts_at, 0, 5) }} – {{ substr($employee->schedules->first()->ends_at, 0, 5) }}</span>@endif</td>
                    <td data-label="Check-in">
                        @if ($attendance?->check_in_at)
                            <strong>{{ $attendance->check_in_at->format('H:i') }}</strong>
                            @if ($attendance->status === 'late')
                                <br><span class="spa-error">Trễ {{ $attendance->late_minutes }} phút</span>
                            @endif
                            @if ($attendance->check_out_at)
                                <br><span class="spa-muted spa-small">ra {{ $attendance->check_out_at->format('H:i') }}</span>
                            @endif
                        @else
                            <span class="spa-muted">Chưa chấm công</span>
                        @endif
                    </td>
                    <td data-label="Phục vụ">{{ $employee->today_services }} lượt</td>
                    <td data-label="Trạng thái"><x-ui.badge :variant="$employee->status === 'active' ? 'success' : 'neutral'">{{ $employee->status === 'active' ? 'Đang làm' : 'Tạm ngưng' }}</x-ui.badge></td>
                    <td data-label="Thao tác">
                        <div class="spa-actions">
                            <x-ui.button size="sm" variant="secondary" wire:click="recordAttendance({{ $employee->id }})">{{ $attendance?->check_in_at && ! $attendance?->check_out_at ? 'Check-out' : 'Chấm công' }}</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" wire:click="editEmployee({{ $employee->id }})">Sửa</x-ui.button>
                            <x-ui.button size="sm" variant="danger" wire:click="toggleStatus({{ $employee->id }})">{{ $employee->status === 'active' ? 'Tạm ngưng' : 'Kích hoạt' }}</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-ui.empty-state title="Không tìm thấy nhân viên" description="Điều chỉnh bộ lọc hoặc thêm hồ sơ nhân viên mới." icon="badge" />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div class="spa-pagination">
            <span class="spa-pagination-info">Hiển thị <strong>{{ $employees->firstItem() ?? 0 }}</strong>–<strong>{{ $employees->lastItem() ?? 0 }}</strong> trên <strong>{{ $employees->total() }}</strong> nhân viên</span>
            {{ $employees->links() }}
        </div>
    </section>
</div>
