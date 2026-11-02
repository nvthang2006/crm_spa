<div class="spa-appointments-page">
    @error('appointment')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror
    @error('package')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.page-heading title="Lịch hẹn & tiếp đón" description="Điều phối khách, dịch vụ và nhân viên theo ca phục vụ.">
        <x-slot:actions>
            <x-ui.input type="date" wire:model.live="date" aria-label="Ngày lịch hẹn" />
            <x-ui.button icon="add" wire:click="newAppointment">Tạo lịch hẹn</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="spa-grid spa-grid-4 spa-summary-strip spa-summary-strip--4 spa-appointments-summary">
        <div class="spa-card spa-stat"><div class="spa-stat-label">Tổng lịch hôm nay</div><div class="spa-stat-value">{{ $stats['total'] }}</div><div class="spa-stat-note">Theo ngày đã chọn</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Chờ tiếp đón</div><div class="spa-stat-value">{{ $stats['waiting'] }}</div><div class="spa-stat-note">Đã đặt hoặc đã đến sảnh</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Đang phục vụ</div><div class="spa-stat-value">{{ $stats['serving'] }}</div><div class="spa-stat-note">Đang thực hiện dịch vụ</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Chờ thanh toán</div><div class="spa-stat-value">{{ $stats['pending'] }}</div><div class="spa-stat-note">Đã hoàn thành dịch vụ</div></div>
    </div>

    {{-- MODAL DIALOG: TẠO / CẬP NHẬT LỊCH HẸN --}}
    @if ($showForm)
        <x-ui.modal
            :show="true"
            name="appointment-modal"
            maxWidth="2xl"
            :title="$editingId ? 'Cập nhật thông tin lịch hẹn' : 'Tạo lịch hẹn & xếp ca phục vụ'"
            @modal-closed="$set('showForm', false)"
        >
            <form wire:submit="saveAppointment" id="appointment-form" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Khách hàng --}}
                    <div class="col-span-1 md:col-span-2">
                        <x-ui.select
                            label="Khách hàng"
                            wire:model.live="customerId"
                            placeholder="-- Chọn khách hàng đến spa --"
                            icon="person"
                            required
                        >
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    {{-- Dịch vụ / Gói liệu trình --}}
                    <div class="col-span-1 md:col-span-2">
                        <x-ui.select
                            label="Dịch vụ / Gói liệu trình"
                            wire:model="offeringId"
                            placeholder="-- Chọn dịch vụ chăm sóc --"
                            icon="spa"
                            required
                        >
                            @foreach ($offerings as $offering)
                                <option value="{{ $offering->id }}">{{ $offering->name }} · {{ number_format($offering->price ?? 0, 0, ',', '.') }} ₫ ({{ $offering->duration_minutes }} phút)</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    {{-- Hình thức thanh toán --}}
                    <div class="{{ $paymentType === 'package' ? 'col-span-1' : 'col-span-1 md:col-span-2' }}">
                        <x-ui.select
                            label="Hình thức thanh toán"
                            wire:model.live="paymentType"
                            icon="payments"
                            required
                        >
                            <option value="service">Thanh toán dịch vụ lẻ</option>
                            <option value="package">Trừ buổi thẻ liệu trình</option>
                        </x-ui.select>
                    </div>

                    {{-- Thẻ liệu trình nếu chọn hình thức package --}}
                    @if ($paymentType === 'package')
                        <div class="col-span-1">
                            <x-ui.select
                                label="Thẻ liệu trình cần trừ"
                                wire:model="packageId"
                                placeholder="-- Chọn thẻ còn buổi --"
                                icon="confirmation_number"
                                required
                            >
                                @foreach ($customerPackages as $package)
                                    <option value="{{ $package->id }}">{{ $package->code }} · {{ $package->offering?->name }} (còn {{ $package->sessions_total - $package->sessions_used }} buổi)</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif

                    {{-- Thời gian ca hẹn --}}
                    <div class="col-span-1 md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <x-ui.input
                            label="Ngày hẹn"
                            type="date"
                            wire:model="appointmentDate"
                            required
                        />
                        <x-ui.input
                            label="Giờ bắt đầu"
                            type="time"
                            wire:model="startTime"
                            required
                        />
                        <x-ui.input
                            label="Giờ kết thúc"
                            type="time"
                            wire:model="endTime"
                            required
                        />
                    </div>

                    {{-- Phân công KTV chính --}}
                    <div class="col-span-1">
                        <x-ui.select
                            label="KTV chính phụ trách"
                            wire:model="primaryEmployeeId"
                            placeholder="-- Chưa phân công --"
                            icon="badge"
                        >
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->job_title }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    {{-- Phân công KTV hỗ trợ --}}
                    <div class="col-span-1">
                        <x-ui.select
                            label="KTV phụ tá (nếu có)"
                            wire:model="supportEmployeeId"
                            placeholder="-- Không có --"
                            icon="group"
                        >
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    @if (config('spa.resource_management_enabled'))
                    {{-- Xếp phòng / Giường --}}
                    <div class="col-span-1 md:col-span-2">
                        <x-ui.select
                            label="Phòng / giường thực hiện"
                            wire:model="resourceId"
                            placeholder="-- Chưa xếp phòng / giường --"
                            icon="meeting_room"
                        >
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    @endif

                    {{-- Ghi chú điều phối --}}
                    <div class="col-span-1 md:col-span-2">
                        <x-ui.input
                            label="Ghi chú điều phối & dặn dò"
                            wire:model="notes"
                            placeholder="VD: Khách da nhạy cảm, cần thao tác nhẹ..."
                        />
                    </div>

                    {{-- Lý do ghi đè trùng lịch --}}
                    <div class="col-span-1 md:col-span-2">
                        <x-ui.input
                            label="Lý do ghi đè trùng lịch (nếu có)"
                            wire:model="overrideReason"
                            placeholder="Để trống nếu không phát sinh xung đột lịch"
                            hint="Chỉ cần nhập khi hệ thống cảnh báo trùng giờ nhân viên"
                        />
                    </div>
                </div>

                @error('appointment')
                    <x-ui.alert variant="danger" class="mt-3">{{ $message }}</x-ui.alert>
                @enderror
            </form>

            <x-slot:actions>
                <x-ui.button variant="secondary" type="button" wire:click="$set('showForm', false)">Hủy bỏ</x-ui.button>
                <x-ui.button type="submit" form="appointment-form">{{ $editingId ? 'Lưu cập nhật lịch hẹn' : 'Xác nhận tạo lịch hẹn' }}</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif

    <div class="spa-appointments-workbench">
    <section class="spa-card spa-appointments-list">
        <div class="spa-card-header">
            <div>
                <h2>Lịch trong ngày</h2>
                <p>{{ $appointments->count() }} lịch hẹn theo ngày và bộ lọc hiện tại</p>
            </div>
        </div>
        <div class="spa-filters">
            <x-ui.input class="min-w-[280px]" type="search" wire:model.live.debounce.300ms="search" aria-label="Tìm lịch hẹn" placeholder="Tìm tên khách, SĐT…" />
            <x-ui.select class="min-w-[220px]" wire:model.live="statusFilter" aria-label="Lọc trạng thái lịch hẹn">
                <option value="all">Tất cả trạng thái</option>
                <option value="scheduled">Đã đặt</option>
                <option value="arrived">Đã đến</option>
                <option value="in_service">Đang phục vụ</option>
                <option value="completed_pending_payment">Chờ thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="package_used">Đã dùng thẻ</option>
                <option value="cancelled">Đã hủy</option>
                <option value="no_show">Không đến</option>
                <option value="refunded">Đã hoàn tiền</option>
            </x-ui.select>
        </div>

        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Giờ</th>
                <th>Khách hàng</th>
                <th>Dịch vụ</th>
                <th>KTV</th>
                @if (config('spa.resource_management_enabled'))<th>Phòng / giường</th>@endif
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </x-slot:header>

            @forelse ($appointments as $appointment)
                <tr>
                    <td data-label="Giờ"><strong>{{ substr($appointment->start_time, 0, 5) }}</strong><br><span class="spa-muted spa-small">đến {{ substr($appointment->end_time, 0, 5) }}</span></td>
                    <td data-label="Khách hàng"><strong>{{ $appointment->customer->name }}</strong><br><span class="spa-muted spa-small">{{ $appointment->customer->phone }}</span></td>
                    <td data-label="Dịch vụ">{{ $appointment->offering->name }}</td>
                    <td data-label="KTV">{{ $appointment->primaryEmployee?->name ?? 'Chưa gán' }}@if ($appointment->supportEmployee)<br><span class="spa-muted spa-small">+ {{ $appointment->supportEmployee->name }}</span>@endif</td>
                    @if (config('spa.resource_management_enabled'))<td data-label="Phòng / giường">{{ $appointment->resource?->name ?? 'Chưa xếp' }}</td>@endif
                    <td data-label="Trạng thái">
                        <x-ui.badge :variant="in_array($appointment->status, ['paid', 'package_used']) ? 'success' : ($appointment->status === 'refunded' ? 'danger' : (in_array($appointment->status, ['in_service', 'arrived']) ? 'info' : ($appointment->status === 'cancelled' ? 'danger' : 'warning')))" :icon="false">
                            {{ ['scheduled' => 'Đã đặt', 'arrived' => 'Đã đến', 'in_service' => 'Đang phục vụ', 'completed_pending_payment' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'package_used' => 'Đã dùng thẻ', 'cancelled' => 'Đã hủy', 'no_show' => 'Không đến', 'refunded' => 'Đã hoàn tiền'][$appointment->status] ?? $appointment->status }}
                        </x-ui.badge>
                    </td>
                    <td data-label="Thao tác">
                        <div class="spa-actions">
                            @if (in_array($appointment->status, ['scheduled', 'arrived']))
                                <x-ui.button size="sm" variant="secondary" wire:click="editAppointment({{ $appointment->id }})">Sửa</x-ui.button>
                            @endif
                            @if ($appointment->status === 'scheduled')
                                <x-ui.button size="sm" variant="secondary" wire:click="arrive({{ $appointment->id }})">Khách đến</x-ui.button>
                            @elseif ($appointment->status === 'arrived')
                                <x-ui.button size="sm" wire:click="startService({{ $appointment->id }})">Bắt đầu</x-ui.button>
                            @elseif ($appointment->status === 'in_service')
                                <x-ui.button size="sm" wire:click="completeService({{ $appointment->id }})">Hoàn thành</x-ui.button>
                            @elseif ($appointment->status === 'completed_pending_payment')
                                @if ($appointment->payment_type === 'package')
                                    <x-ui.button size="sm" wire:click="usePackage({{ $appointment->id }})">Trừ thẻ</x-ui.button>
                                @else
                                    <x-ui.button size="sm" href="{{ route('invoices') }}">Thu ngân</x-ui.button>
                                @endif
                            @endif
                            @if (in_array($appointment->status, ['scheduled', 'arrived']))
                                <x-ui.button size="sm" variant="danger" wire:click="markNoShow({{ $appointment->id }})">Không đến</x-ui.button>
                            @endif
                            @if (! in_array($appointment->status, ['paid', 'package_used', 'cancelled', 'no_show', 'refunded']))
                                <x-ui.button size="sm" variant="danger" wire:click="cancelAppointment({{ $appointment->id }})">Hủy</x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ config('spa.resource_management_enabled') ? 7 : 6 }}">
                        <x-ui.empty-state title="Chưa có lịch hẹn" description="Không có lịch hẹn phù hợp với ngày và bộ lọc hiện tại." icon="event_busy" />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </section>

    <section class="spa-card spa-timeline-panel">
        <div class="spa-card-header">
            <div>
                <h2>Timeline vận hành</h2>
                <p>Khung giờ 30 phút · dùng bảng trên để cập nhật trạng thái</p>
            </div>
        </div>
        <div class="spa-timeline">
            <div class="spa-timeline-row" style="grid-template-columns:175px repeat({{ $timeSlots->count() }}, minmax(72px, 1fr))">
                <div class="spa-timeline-label">{{ config('spa.resource_management_enabled') ? 'Phòng / giường' : 'Nhân viên' }}</div>
                @foreach ($timeSlots as $slot)
                    <div class="spa-timeline-label">{{ $slot }}</div>
                @endforeach
            </div>
            @foreach ($appointments->groupBy(fn ($appointment) => config('spa.resource_management_enabled') ? 'resource-'.$appointment->service_resource_id : 'employee-'.$appointment->primary_employee_id) as $groupKey => $groupAppointments)
                <div class="spa-timeline-row" style="grid-template-columns:175px repeat({{ $timeSlots->count() }}, minmax(72px, 1fr))" wire:key="timeline-{{ $groupKey }}">
                    <div class="spa-timeline-label">{{ config('spa.resource_management_enabled') ? ($groupAppointments->first()->resource?->name ?? 'Chưa xếp phòng') : ($groupAppointments->first()->primaryEmployee?->name ?? 'Chưa gán nhân viên') }}</div>
                    @foreach ($timeSlots as $slot)
                        <div>
                            @foreach ($groupAppointments->filter(fn ($appointment) => substr($appointment->start_time, 0, 3).((int) substr($appointment->start_time, 3, 2) < 30 ? '00' : '30') === $slot) as $appointment)
                                <div class="spa-event {{ $appointment->status === 'in_service' ? 'spa-event-info' : 'spa-event-warning' }}">
                                    <strong>{{ $appointment->customer->name }}</strong>
                                    <span>{{ substr($appointment->start_time, 0, 5) }}–{{ substr($appointment->end_time, 0, 5) }}</span>
                                    <span>{{ $appointment->offering->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
    </div>
</div>
