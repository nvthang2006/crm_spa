<div
    class="spa-customers-page"
    x-data
    x-init="
        let wasOpen = Boolean($el.querySelector('.spa-customer-detail'));
        const syncDetailPosition = () => {
            const detail = $el.querySelector('.spa-customer-detail');
            const isOpen = Boolean(detail);
            if (isOpen && !wasOpen && window.matchMedia('(max-width: 760px)').matches) {
                requestAnimationFrame(() => detail.scrollIntoView({ block: 'start', behavior: 'auto' }));
            }
            wasOpen = isOpen;
        };
        const observer = new MutationObserver(syncDetailPosition);
        observer.observe($el, { childList: true, subtree: true });
        $nextTick(syncDetailPosition);
    "
>
    @error('package')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.page-heading title="Khách hàng & CRM liệu trình" description="Quản lý hồ sơ, thẻ liệu trình và nhật ký sử dụng của khách hàng.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" loading="exportCustomers" wire:click="exportCustomers">Xuất danh sách</x-ui.button>
            <x-ui.button icon="person_add" wire:click="newCustomer">Thêm khách hàng</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="spa-grid spa-grid-3 spa-summary-strip spa-summary-strip--3 spa-customers-summary">
        <div class="spa-card spa-stat"><div class="spa-stat-label">Tổng khách hàng</div><div class="spa-stat-value">{{ number_format($stats['total']) }}</div><div class="spa-stat-note">Hồ sơ đang hoạt động</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Hội viên VIP</div><div class="spa-stat-value">{{ $stats['vip'] }}</div><div class="spa-stat-note">Kim Cương và Vàng</div></div>
        <div class="spa-card spa-stat"><div class="spa-stat-label">Thẻ liệu trình đang chạy</div><div class="spa-stat-value">{{ $stats['packages'] }}</div><div class="spa-stat-note">Theo dõi số buổi tích lũy</div></div>
    </div>

    @if ($showForm)
        <section class="spa-card spa-context-form" style="margin-bottom:18px">
            <div class="spa-card-header">
                <div>
                    <h2>{{ $editingId ? 'Sửa hồ sơ khách hàng' : 'Thêm khách hàng mới' }}</h2>
                    <p>Thông tin sức khỏe được mã hóa và ghi nhật ký khi truy cập.</p>
                </div>
                <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('showForm', false)" />
            </div>

            <form wire:submit="saveCustomer" class="spa-card-body">
                <div class="spa-grid spa-grid-3">
                    <x-ui.input label="Mã khách hàng" wire:model="code" required />
                    <x-ui.input label="Họ và tên" wire:model="name" required />
                    <x-ui.input label="Số điện thoại" type="tel" wire:model="phone" required />
                    <x-ui.input label="Email" type="email" wire:model="email" />
                    <x-ui.select label="Hạng thẻ" wire:model="membershipTier">
                        <option>Thành viên</option>
                        <option>Bạc</option>
                        <option>Vàng</option>
                        <option>Kim Cương</option>
                    </x-ui.select>
                    <x-ui.select label="KTV ưu tiên" wire:model="preferredEmployeeId" placeholder="Không chỉ định">
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input class="md:col-span-3" label="Địa chỉ" wire:model="address" />
                    <x-ui.textarea label="Dị ứng" wire:model="allergyNotes" />
                    <x-ui.textarea label="Chống chỉ định" wire:model="contraindications" />
                    <x-ui.textarea label="Tình trạng hiện tại" wire:model="currentCondition" />
                    <x-ui.textarea class="md:col-span-3" label="Ghi chú chăm sóc" wire:model="careNotes" />
                </div>

                <div class="spa-actions" style="justify-content:flex-end;margin-top:18px">
                    <x-ui.button variant="secondary" wire:click="$set('showForm', false)">Hủy</x-ui.button>
                    <x-ui.button type="submit">Lưu hồ sơ</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    <div class="spa-customers-workspace">
    <section class="spa-card spa-customer-list" style="margin-bottom:18px">
        <div class="spa-card-header">
            <div>
                <h2>Danh bạ khách hàng</h2>
                <p>{{ $customers->total() }} hồ sơ · chọn “Hồ sơ” để xem thông tin chi tiết</p>
            </div>
        </div>
        <div class="spa-filters">
            <x-ui.input class="min-w-[300px]" type="search" wire:model.live.debounce.300ms="search" aria-label="Tìm khách hàng" placeholder="Tìm tên, mã KH, số điện thoại…" />
            <x-ui.select class="min-w-[220px]" wire:model.live="tierFilter" aria-label="Lọc hạng thẻ khách hàng">
                <option value="all">Tất cả hạng thẻ</option>
                @foreach ($tiers as $tier)
                    <option value="{{ $tier }}">{{ $tier }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <x-ui.table mobile-cards>
            <x-slot:header>
                <th>Mã KH</th>
                <th>Họ và tên</th>
                <th>Hạng thẻ</th>
                <th>Thẻ liệu trình</th>
                <th>Tiến độ</th>
                <th>Chi tiêu tích lũy</th>
                <th>Thao tác</th>
            </x-slot:header>

            @forelse ($customers as $customer)
                @php($activePackage = $customer->packages->first())
                <tr>
                    <td data-label="Mã KH"><strong>{{ $customer->code }}</strong></td>
                    <td data-label="Họ và tên"><strong>{{ $customer->name }}</strong><br><span class="spa-muted spa-small">{{ $customer->phone }}</span></td>
                    <td data-label="Hạng thẻ"><x-ui.badge :variant="in_array($customer->membership_tier, ['Vàng', 'Kim Cương']) ? 'warning' : 'neutral'">{{ $customer->membership_tier }}</x-ui.badge></td>
                    <td data-label="Thẻ liệu trình">{{ $customer->active_packages }} thẻ đang chạy</td>
                    <td data-label="Tiến độ">
                        @if ($activePackage)
                            <div class="spa-progress"><span style="width:{{ min(100, ($activePackage->sessions_used / max(1, $activePackage->sessions_total)) * 100) }}%"></span></div>
                            <span class="spa-muted spa-small">{{ $activePackage->sessions_used }}/{{ $activePackage->sessions_total }} buổi</span>
                        @else
                            <span class="spa-muted">Khách lẻ</span>
                        @endif
                    </td>
                    <td data-label="Chi tiêu tích lũy"><strong>{{ number_format($customer->total_spend, 0, ',', '.') }} ₫</strong></td>
                    <td data-label="Thao tác">
                        <div class="spa-actions">
                            <x-ui.button size="sm" wire:click="showCustomer({{ $customer->id }})">Hồ sơ</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" wire:click="editCustomer({{ $customer->id }})">Sửa</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-ui.empty-state title="Chưa có khách hàng phù hợp" description="Điều chỉnh bộ lọc hoặc thêm hồ sơ khách hàng mới." icon="group" />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div class="spa-pagination">
            <span class="spa-pagination-info">Hiển thị <strong>{{ $customers->firstItem() ?? 0 }}</strong>–<strong>{{ $customers->lastItem() ?? 0 }}</strong> trên <strong>{{ $customers->total() }}</strong> khách hàng</span>
            {{ $customers->links() }}
        </div>
    </section>

    @if ($showDetail && $selectedCustomer)
        <section class="spa-card spa-customer-detail">
            <div class="spa-card-header">
                <div>
                    <h2>{{ $selectedCustomer->name }} <x-ui.badge variant="warning">{{ $selectedCustomer->membership_tier }}</x-ui.badge></h2>
                    <p>{{ $selectedCustomer->code }} · {{ $selectedCustomer->phone }} · Nhật ký truy cập đã ghi nhận</p>
                </div>
                <div class="spa-actions">
                    <x-ui.button href="{{ route('invoices') }}">Bán gói liệu trình</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="editCustomer({{ $selectedCustomer->id }})">Sửa thông tin</x-ui.button>
                    <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('showDetail', false)" />
                </div>
            </div>

            <div class="spa-card-body">
                <div class="spa-grid spa-grid-2">
                    <div>
                        <h3 style="margin:0 0 10px;font-size:14px">Hồ sơ da & sức khỏe bảo mật</h3>
                        <div class="spa-list">
                            <div class="spa-list-item"><span>Dị ứng</span><strong>{{ $selectedCustomer->healthProfile?->allergy_notes ?: 'Chưa cập nhật' }}</strong></div>
                            <div class="spa-list-item"><span>Chống chỉ định</span><strong>{{ $selectedCustomer->healthProfile?->contraindications ?: 'Chưa cập nhật' }}</strong></div>
                            <div class="spa-list-item"><span>Tình trạng hiện tại</span><strong>{{ $selectedCustomer->healthProfile?->current_condition ?: 'Chưa cập nhật' }}</strong></div>
                            <div class="spa-list-item"><span>KTV ưu tiên</span><strong>{{ $selectedCustomer->healthProfile?->preferredEmployee?->name ?: 'Chưa chỉ định' }}</strong></div>
                        </div>
                    </div>

                    <div>
                        <h3 style="margin:0 0 10px;font-size:14px">Thẻ liệu trình đang sử dụng</h3>
                        @forelse ($selectedCustomer->packages as $package)
                            <div style="padding:14px;border:1px solid var(--spa-border);border-radius:9px;margin-bottom:10px">
                                <div style="display:flex;justify-content:space-between;gap:10px">
                                    <strong>{{ $package->offering->name }}</strong>
                                    <x-ui.badge :variant="$package->status === 'active' ? 'success' : ($package->status === 'refunded' ? 'danger' : 'neutral')">
                                        {{ ['active' => 'Đang hoạt động', 'completed' => 'Hoàn thành', 'expired' => 'Đã hết hạn', 'refunded' => 'Đã hoàn tiền'][$package->status] ?? $package->status }}
                                    </x-ui.badge>
                                </div>
                                <p class="spa-muted spa-small" style="margin:6px 0">Mã thẻ {{ $package->code }} · Đã thực hiện {{ $package->sessions_used }}/{{ $package->sessions_total }} buổi</p>
                                <div class="spa-progress"><span style="width:{{ min(100, ($package->sessions_used / max(1, $package->sessions_total)) * 100) }}%"></span></div>

                                @if ($package->status === 'active')
                                    <div class="spa-actions" style="margin-top:10px">
                                        <x-ui.input class="min-w-[220px]" wire:model="usageNote" aria-label="Ghi chú xác nhận" placeholder="Ghi chú xác nhận (không bắt buộc)" />
                                        <x-ui.button size="sm" wire:click="consumeSession({{ $package->id }})">Trừ 1 buổi</x-ui.button>
                                    </div>
                                @endif

                                @if ($package->usages->isNotEmpty())
                                    <div style="margin-top:12px">
                                        <span class="spa-muted spa-small">Lịch sử trừ buổi</span>
                                        @foreach ($package->usages->sortByDesc('session_number')->take(5) as $usage)
                                            <div class="spa-list-item">
                                                <span>Buổi {{ $usage->session_number }} · {{ $usage->used_at?->format('d/m/Y H:i') }}<br><small class="spa-muted">{{ $usage->verification_note ?: 'Xác nhận quản trị' }}</small></span>
                                                <span class="spa-actions">
                                                    @if ($usage->direction === 'use')
                                                        <x-ui.badge variant="success">Đã trừ</x-ui.badge>
                                                        @if ($package->status !== 'refunded')
                                                            <x-ui.button size="sm" variant="secondary" wire:click="openRestore({{ $package->id }}, {{ $usage->session_number }})">Hoàn lại</x-ui.button>
                                                        @endif
                                                    @else
                                                        <x-ui.badge variant="warning">Đã hoàn</x-ui.badge>
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <x-ui.empty-state title="Khách chưa có thẻ liệu trình" icon="card_membership" />
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        @if ($restoringPackageId)
            <section class="spa-card spa-customer-restore" style="margin-top:18px">
                <div class="spa-card-header">
                    <div>
                        <h2>Hoàn lại buổi liệu trình</h2>
                        <p>Thao tác chỉ thực hiện khi có lý do rõ ràng và sẽ được lưu audit.</p>
                    </div>
                    <x-ui.icon-button icon="close" label="Đóng" wire:click="$set('restoringPackageId', null)" />
                </div>

                <form wire:submit="restoreSession" class="spa-card-body">
                    <div class="spa-grid spa-grid-2">
                        <x-ui.input label="Số buổi" type="number" min="1" wire:model="restoringSessionNumber" readonly />
                        <x-ui.textarea label="Lý do hoàn" wire:model="restoreReason" required />
                    </div>
                    <div class="spa-actions" style="justify-content:flex-end;margin-top:14px">
                        <x-ui.button variant="secondary" wire:click="$set('restoringPackageId', null)">Hủy</x-ui.button>
                        <x-ui.button type="submit">Xác nhận hoàn buổi</x-ui.button>
                    </div>
                </form>
            </section>
        @endif
    @endif
    </div>
</div>
