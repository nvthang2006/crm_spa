<div class="spa-services-page">
    @error('type')
        <x-ui.alert variant="danger" class="mb-3">{{ $message }}</x-ui.alert>
    @enderror
    @error('offering')
        <x-ui.alert variant="danger" class="mb-3">{{ $message }}</x-ui.alert>
    @enderror
    @error('commission')
        <x-ui.alert variant="danger" class="mb-3">{{ $message }}</x-ui.alert>
    @enderror

    {{-- Tiêu đề trang & Hành động chính --}}
    <x-ui.page-heading title="Dịch vụ & Cấu hình hoa hồng" description="Quản lý danh mục dịch vụ spa, gói thẻ liệu trình và thiết lập chính sách hoa hồng cho kỹ thuật viên.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="percent" wire:click="newCommission">Thêm quy tắc hoa hồng</x-ui.button>
            <x-ui.button variant="secondary" icon="card_membership" wire:click="newOffering('package')">Thêm gói liệu trình</x-ui.button>
            <x-ui.button icon="add" wire:click="newOffering('service')">Thêm dịch vụ lẻ</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    {{-- 4 Thẻ KPI thống kê thông minh (Hỗ trợ bấm để lọc nhanh) --}}
    <div class="spa-grid spa-grid-4 spa-summary-strip spa-summary-strip--4 spa-services-summary">
        <x-ui.action-card :active="$activeTab === 'offerings' && $typeFilter === 'all'" class="{{ $activeTab === 'offerings' && $typeFilter === 'all' ? 'spa-stat-active' : '' }}" wire:click="filterByType('all')" title="Xem tất cả dịch vụ">
            <div class="spa-stat-label">Tất cả dịch vụ</div>
            <div class="spa-stat-value text-slate-900">{{ number_format($stats['all']) }}</div>
            <div class="spa-stat-note">Tổng số mục trong danh mục</div>
            <div class="spa-stat-icon">
                <span class="material-symbols-outlined" aria-hidden="true">category</span>
            </div>
        </x-ui.action-card>

        <x-ui.action-card :active="$activeTab === 'offerings' && $typeFilter === 'service'" class="{{ $activeTab === 'offerings' && $typeFilter === 'service' ? 'spa-stat-active' : '' }}" wire:click="filterByType('service')" title="Lọc danh sách dịch vụ lẻ">
            <div class="spa-stat-label">Dịch vụ lẻ</div>
            <div class="spa-stat-value">{{ number_format($stats['service']) }}</div>
            <div class="spa-stat-note">Dịch vụ tính theo từng buổi</div>
            <div class="spa-stat-icon">
                <span class="material-symbols-outlined" aria-hidden="true">spa</span>
            </div>
        </x-ui.action-card>

        <x-ui.action-card :active="$activeTab === 'offerings' && $typeFilter === 'package'" class="{{ $activeTab === 'offerings' && $typeFilter === 'package' ? 'spa-stat-active' : '' }}" wire:click="filterByType('package')" title="Lọc danh sách gói liệu trình">
            <div class="spa-stat-label">Gói liệu trình</div>
            <div class="spa-stat-value">{{ number_format($stats['package']) }}</div>
            <div class="spa-stat-note">Thẻ tích lũy nhiều buổi</div>
            <div class="spa-stat-icon">
                <span class="material-symbols-outlined" aria-hidden="true">card_membership</span>
            </div>
        </x-ui.action-card>

        <x-ui.action-card :active="$activeTab === 'commissions'" class="{{ $activeTab === 'commissions' ? 'spa-stat-active' : '' }}" wire:click="switchTab('commissions')" title="Chuyển sang cấu hình hoa hồng">
            <div class="spa-stat-label">Quy tắc hoa hồng</div>
            <div class="spa-stat-value">{{ number_format($stats['active_commissions']) }}</div>
            <div class="spa-stat-note">Chính sách đang áp dụng</div>
            <div class="spa-stat-icon">
                <span class="material-symbols-outlined" aria-hidden="true">percent</span>
            </div>
        </x-ui.action-card>
    </div>

    {{-- Điều hướng Phân đoạn (Segmented Tabs) --}}
    <nav class="spa-tabs" aria-label="Phân hệ dịch vụ">
        <x-ui.tab :active="$activeTab === 'offerings'" wire:click="switchTab('offerings')">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">spa</span>
            Bảng giá & Danh mục dịch vụ
            <span class="spa-tab-badge">{{ $stats['all'] }}</span>
        </x-ui.tab>

        <x-ui.tab :active="$activeTab === 'commissions'" wire:click="switchTab('commissions')">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">percent</span>
            Chính sách hoa hồng KTV
            <span class="spa-tab-badge">{{ $stats['active_commissions'] }}</span>
        </x-ui.tab>
    </nav>

    {{-- TAB 1: BẢNG GIÁ VÀ DANH MỤC DỊCH VỤ --}}
    @if ($activeTab === 'offerings')
        <section class="spa-card">
            <div class="spa-card-header">
                <div>
                    <h2>Bảng giá & danh mục</h2>
                    <p>{{ $offerings->total() }} dịch vụ/gói đang hiển thị theo bộ lọc</p>
                </div>
            </div>
            {{-- Thanh công cụ & Bộ lọc đa chiều --}}
            <div class="spa-filters flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[260px]">
                    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Tìm tên dịch vụ, mã liệu trình…" />
                </div>

                <div class="w-[200px]">
                    <x-ui.select wire:model.live="categoryFilter" aria-label="Lọc theo phân loại">
                        <option value="all">Tất cả phân loại</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="w-[180px]">
                    <x-ui.select wire:model.live="typeFilter" aria-label="Lọc theo loại hình">
                        <option value="all">Tất cả loại hình</option>
                        <option value="service">Dịch vụ lẻ</option>
                        <option value="package">Gói liệu trình</option>
                    </x-ui.select>
                </div>

                @if ($search !== '' || $typeFilter !== 'all' || $categoryFilter !== 'all')
                    <x-ui.button variant="secondary" size="sm" icon="close" wire:click="resetFilters">Xóa lọc</x-ui.button>
                @endif
            </div>

            {{-- Bảng danh sách dịch vụ cao cấp --}}
            <x-ui.table mobile-cards>
                <x-slot:header>
                    <th style="width:100px">Mã DV</th>
                    <th>Tên dịch vụ / Gói liệu trình</th>
                    <th>Phân loại</th>
                    <th>Thời lượng</th>
                    <th>Giá niêm yết</th>
                    <th>Quy cách</th>
                    <th>Hoa hồng</th>
                    <th>Trạng thái</th>
                    <th style="text-align:right">Thao tác</th>
                </x-slot:header>

                @forelse ($offerings as $offering)
                    <tr>
                        <td data-label="Mã DV">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $offering->code }}
                            </span>
                        </td>
                        <td data-label="Tên dịch vụ / Gói">
                            <div class="flex items-center gap-3">
                                <div class="spa-offering-icon" style="background:var(--spa-surface-muted);color:var(--spa-text-secondary)">
                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">
                                        {{ $offering->type === 'package' ? 'card_membership' : 'spa' }}
                                    </span>
                                </div>
                                <div>
                                    <strong class="text-[13.5px] text-slate-900 block font-semibold leading-snug">{{ $offering->name }}</strong>
                                    <span class="text-[11.5px] text-slate-500 block max-w-[340px] truncate mt-0.5">
                                        {{ $offering->description ?: 'Chưa có mô tả chi tiết' }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Phân loại">
                            <x-ui.badge variant="neutral">{{ $offering->category?->name ?? 'Chưa phân loại' }}</x-ui.badge>
                        </td>
                        <td data-label="Thời lượng">
                            <span class="inline-flex items-center gap-1 text-[12.5px] text-slate-600 font-medium">
                                <span class="material-symbols-outlined text-[16px] text-slate-400" aria-hidden="true">schedule</span>
                                {{ $offering->duration_minutes }} phút
                            </span>
                        </td>
                        <td data-label="Giá niêm yết">
                            <strong class="text-[13.5px] text-slate-900 font-semibold tabular-nums">
                                {{ $offering->price !== null ? number_format($offering->price, 0, ',', '.') . ' ₫' : 'Chưa duyệt giá' }}
                            </strong>
                        </td>
                        <td data-label="Quy cách">
                            @if ($offering->type === 'package')
                                <x-ui.badge variant="neutral" shape="tag" :icon="false">{{ $offering->sessions_total }} buổi</x-ui.badge>
                            @else
                                <span class="text-[12px] text-slate-500">1 buổi</span>
                            @endif
                        </td>
                        <td data-label="Hoa hồng">
                            @php($primaryRule = $offering->commissionRules->first(fn ($r) => $r->is_active && $r->beneficiary_type === 'primary_technician'))
                            @if ($primaryRule)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[12px] font-bold bg-amber-50 text-amber-800 border border-amber-200 tabular-nums">
                                    {{ $primaryRule->calculation_type === 'percentage' ? rtrim(rtrim(number_format((float) $primaryRule->value, 2, ',', '.'), '0'), ',') . '%' : number_format((float) $primaryRule->value, 0, ',', '.') . ' ₫' }}
                                </span>
                            @else
                                <span class="text-[12px] text-slate-400 font-medium">Chưa đặt</span>
                            @endif
                        </td>
                        <td data-label="Trạng thái">
                            <x-ui.badge :variant="$offering->status === 'active' ? 'success' : 'neutral'">
                                {{ $offering->status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}
                            </x-ui.badge>
                        </td>
                        <td data-label="Thao tác" style="text-align:right">
                            <div class="spa-actions" style="justify-content:flex-end">
                                <x-ui.button size="sm" variant="secondary" wire:click="editOffering({{ $offering->id }})">Sửa</x-ui.button>
                                <x-ui.button size="sm" :variant="$offering->status === 'active' ? 'secondary' : 'primary'" wire:click="toggleStatus({{ $offering->id }})">
                                    {{ $offering->status === 'active' ? 'Tắt' : 'Bật' }}
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <x-ui.empty-state title="Chưa có dịch vụ phù hợp" description="Không tìm thấy dịch vụ nào theo tiêu chí lọc hiện tại." icon="spa" />
                        </td>
                    </tr>
                @endforelse
            </x-ui.table>

            {{-- Chân bảng phân trang chuẩn Sunny Beauty --}}
            <div class="spa-pagination">
                <span class="spa-pagination-info">Hiển thị <strong>{{ $offerings->firstItem() ?? 0 }}</strong>–<strong>{{ $offerings->lastItem() ?? 0 }}</strong> trên <strong>{{ $offerings->total() }}</strong> dịch vụ</span>
                {{ $offerings->links() }}
            </div>
        </section>
    @endif

    {{-- TAB 2: CHÍNH SÁCH HOA HỒNG KỸ THUẬT VIÊN --}}
    @if ($activeTab === 'commissions')
        <section class="spa-card">
            <div class="spa-card-header">
                <div>
                    <h2 class="text-[15px] font-bold text-slate-900 m-0">Danh sách quy tắc hoa hồng</h2>
                    <p class="text-[12px] text-slate-500 m-0 mt-0.5">Quy tắc được tự động áp dụng khi thanh toán hóa đơn hoặc khi kỹ thuật viên hoàn thành buổi trị liệu.</p>
                </div>
                <x-ui.button icon="add" wire:click="newCommission">Thêm quy tắc mới</x-ui.button>
            </div>

            <x-ui.table mobile-cards>
                <x-slot:header>
                    <th>Dịch vụ / Gói áp dụng</th>
                    <th>Đối tượng nhận</th>
                    <th>Thời điểm tính</th>
                    <th>Mức hoa hồng</th>
                    <th>Hiệu lực áp dụng</th>
                    <th>Trạng thái</th>
                    <th style="text-align:right">Thao tác</th>
                </x-slot:header>

                @forelse ($commissionRules as $rule)
                    <tr>
                        <td data-label="Dịch vụ / Gói">
                            <strong class="text-[13.5px] text-slate-900 font-semibold block">{{ $rule->offering?->name ?? '—' }}</strong>
                            <span class="text-[11.5px] text-slate-500 block mt-0.5">{{ $rule->notes ?: 'Không có ghi chú điều kiện' }}</span>
                        </td>
                        <td data-label="Đối tượng nhận">
                            <x-ui.badge variant="neutral">
                                {{ ['primary_technician' => 'KTV chính', 'support_technician' => 'KTV hỗ trợ', 'performer_employee' => 'Khi '.$rule->beneficiaryEmployee?->name.' thực hiện', 'specific_employee' => $rule->beneficiaryEmployee?->name ?? 'Nhân sự cụ thể'][$rule->beneficiary_type] ?? $rule->beneficiary_type }}
                            </x-ui.badge>
                        </td>
                        <td data-label="Thời điểm tính">
                            <span class="text-[12.5px] text-slate-700 font-medium">
                                {{ $rule->basis === 'payment' ? 'Khi thanh toán' : 'Khi hoàn thành dịch vụ' }}
                            </span>
                        </td>
                        <td data-label="Mức hoa hồng">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[13px] font-bold bg-[var(--spa-surface-muted)] text-[var(--spa-text)] border border-[var(--spa-border)] tabular-nums">
                                {{ $rule->calculation_type === 'percentage' ? rtrim(rtrim(number_format((float) $rule->value, 2, ',', '.'), '0'), ',') . '%' : number_format((float) $rule->value, 0, ',', '.') . ' ₫' }}
                            </span>
                        </td>
                        <td data-label="Hiệu lực áp dụng">
                            <span class="text-[12px] text-slate-600">
                                {{ $rule->starts_on?->format('d/m/Y') }} – {{ $rule->ends_on ? $rule->ends_on->format('d/m/Y') : 'Vô thời hạn' }}
                            </span>
                        </td>
                        <td data-label="Trạng thái">
                            <x-ui.badge :variant="$rule->is_active ? 'success' : 'neutral'">
                                {{ $rule->is_active ? 'Đang áp dụng' : 'Tạm dừng' }}
                            </x-ui.badge>
                        </td>
                        <td data-label="Thao tác" style="text-align:right">
                            <div class="spa-actions" style="justify-content:flex-end">
                                <x-ui.button size="sm" variant="secondary" wire:click="editCommission({{ $rule->id }})">Sửa</x-ui.button>
                                <x-ui.button size="sm" :variant="$rule->is_active ? 'secondary' : 'primary'" wire:click="toggleCommission({{ $rule->id }})">
                                    {{ $rule->is_active ? 'Tắt' : 'Bật' }}
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-ui.empty-state title="Chưa có quy tắc hoa hồng" description="Tạo quy tắc để tự động tính thưởng hoa hồng cho kỹ thuật viên theo ca." icon="percent" />
                        </td>
                    </tr>
                @endforelse
            </x-ui.table>

            <div class="spa-pagination">
                <span class="spa-pagination-info">Hiển thị <strong>{{ $commissionRules->firstItem() ?? 0 }}</strong>–<strong>{{ $commissionRules->lastItem() ?? 0 }}</strong> trên <strong>{{ $commissionRules->total() }}</strong> quy tắc</span>
                {{ $commissionRules->links(data: ['scrollTo' => false]) }}
            </div>
        </section>
    @endif

    {{-- MODAL 1: THÊM / SỬA DỊCH VỤ HOẶC GÓI LIỆU TRÌNH --}}
    @if ($showForm)
        <x-ui.modal :show="true" name="offering-modal" maxWidth="xl" :title="$editingId ? 'Chỉnh sửa dịch vụ / gói liệu trình' : ($type === 'package' ? 'Thêm mới gói liệu trình' : 'Thêm mới dịch vụ lẻ')">
            <form wire:submit="saveOffering" id="offering-form">
                <div class="spa-grid spa-grid-2 gap-4">
                    <x-ui.input label="Mã định danh dịch vụ" wire:model="code" placeholder="VD: FACIAL-01" required hint="Mã viết hoa, không trùng lặp" />

                    <x-ui.select label="Loại hình" wire:model="type" required>
                        <option value="service">Dịch vụ lẻ</option>
                        <option value="package">Gói liệu trình</option>
                    </x-ui.select>

                    <div class="col-span-2">
                        <x-ui.input label="Tên dịch vụ / gói liệu trình" wire:model="name" placeholder="VD: Massage thảo dược bấm huyệt" required />
                    </div>

                    <x-ui.select label="Phân loại chuyên môn" wire:model="categoryId" placeholder="Chưa phân loại">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="Thời lượng liệu trình (phút)" type="number" min="15" max="600" step="5" wire:model="durationMinutes" required />

                    <x-ui.input label="Giá niêm yết (₫)" type="number" min="0" step="1000" wire:model="price" placeholder="0" required />

                    @if ($type === 'package')
                        <x-ui.input label="Số buổi trong gói" type="number" min="1" max="1000" wire:model="sessionsTotal" required hint="Tổng số buổi khách hàng được hưởng" />
                    @else
                        <div class="flex items-center pt-6 text-[12px] text-slate-500">
                            <span class="material-symbols-outlined text-[18px] text-[var(--spa-brand)] mr-1" aria-hidden="true">info</span>
                            Dịch vụ lẻ mặc định tính 1 buổi thực hiện.
                        </div>
                    @endif

                    <div class="col-span-2">
                        <x-ui.textarea label="Mô tả liệu trình & công dụng" wire:model="description" placeholder="Ghi chú quy trình thực hiện, mỹ phẩm sử dụng hoặc lưu ý chăm sóc..." rows="3" />
                    </div>

                    <div class="col-span-2 pt-3 border-t border-[var(--spa-border)]">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-[13.5px] font-semibold text-slate-900">Hoa hồng KTV thực hiện</h4>
                                <p class="text-[11.5px] text-slate-500">Mức hoa hồng tự động ghi nhận cho kỹ thuật viên khi làm ca</p>
                            </div>
                            <x-ui.checkbox label="Áp dụng hoa hồng" wire:model.live="offeringCommissionEnabled" />
                        </div>

                        @if ($offeringCommissionEnabled)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 rounded-lg bg-[var(--spa-surface-muted)] border border-[var(--spa-border)]">
                                <x-ui.select label="Hình thức tính" wire:model.live="offeringCommissionType" required>
                                    <option value="percentage">Theo tỷ lệ % doanh thu / giá buổi</option>
                                    <option value="fixed">Số tiền cố định (₫ / buổi làm)</option>
                                </x-ui.select>

                                @if ($offeringCommissionType === 'percentage')
                                    <x-ui.input label="Tỷ lệ hoa hồng (%)" type="number" min="0" max="100" step="0.5" wire:model="offeringCommissionValue" placeholder="10" required hint="Ví dụ: 10 nghĩa là 10% doanh thu hoặc giá trị buổi" />
                                @else
                                    <x-ui.input label="Tiền hoa hồng (₫ / buổi)" type="number" min="0" step="1000" wire:model="offeringCommissionValue" placeholder="50000" required hint="Ví dụ: 50.000 ₫ mỗi ca làm (kể cả khi khách trừ buổi gói 0 ₫)" />
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </form>

            <x-slot:actions>
                <x-ui.button variant="secondary" type="button" wire:click="$set('showForm', false)">Hủy</x-ui.button>
                <x-ui.button type="submit" form="offering-form">{{ $editingId ? 'Lưu cập nhật' : 'Tạo dịch vụ' }}</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif

    {{-- MODAL 2: THÊM / SỬA QUY TẮC HOA HỒNG --}}
    @if ($showCommissionForm)
        <x-ui.modal :show="true" name="commission-modal" maxWidth="xl" :title="$editingCommissionId ? 'Chỉnh sửa quy tắc hoa hồng' : 'Thiết lập quy tắc hoa hồng mới'">
            <form wire:submit="saveCommission" id="commission-form">
                <div class="spa-grid spa-grid-2 gap-4">
                    <div class="col-span-2">
                        <x-ui.select label="Dịch vụ / Gói áp dụng" wire:model="commissionOfferingId" :selected="$commissionOfferingId" placeholder="Chọn dịch vụ áp dụng" required>
                            @foreach ($commissionOfferings as $offering)
                                <option value="{{ $offering->id }}">{{ $offering->name }} ({{ number_format($offering->price ?? 0, 0, ',', '.') }} ₫)</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.select label="Đối tượng hưởng hoa hồng" wire:model.live="beneficiaryType" :selected="$beneficiaryType" required>
                        <option value="primary_technician">KTV chính (trực tiếp thực hiện)</option>
                        <option value="support_technician">KTV hỗ trợ (phụ liệu trình)</option>
                        <option value="performer_employee">Mức riêng khi nhân viên thực hiện</option>
                        <option value="specific_employee">Chỉ định người nhận (kể cả khi người khác thực hiện)</option>
                    </x-ui.select>

                    @if (in_array($beneficiaryType, ['specific_employee', 'performer_employee'], true))
                        <div wire:key="commission-recipient-{{ $beneficiaryType }}">
                        <x-ui.select :label="$beneficiaryType === 'performer_employee' ? 'Nhân viên áp dụng mức riêng' : 'Chỉ định nhân viên nhận'"
                                     wire:model.live="beneficiaryEmployeeId" :selected="$beneficiaryEmployeeId"
                                     :options="$employees->mapWithKeys(fn ($employee) => [$employee->id => $employee->name.' · '.$employee->job_title])->all()"
                                     placeholder="Chọn nhân sự" searchable required />
                        </div>
                    @else
                        <div class="flex items-center pt-6 text-[12px] text-slate-500">
                            <span class="material-symbols-outlined text-[18px] text-[var(--spa-brand)] mr-1" aria-hidden="true">check_circle</span>
                            Tự động áp dụng cho KTV được phân công trong lịch hẹn.
                        </div>
                    @endif

                    @if ($beneficiaryType === 'performer_employee')
                        <p class="text-sm text-slate-500">Chỉ áp dụng khi nhân viên này trực tiếp thực hiện dịch vụ. Mức riêng thay mức KTV chính; các khoản cho người nhận khác vẫn được cộng.</p>
                    @endif
                    @error('commission') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

                    <x-ui.select label="Thời điểm tính hoa hồng" wire:model="basis" :selected="$basis" required>
                        <option value="payment">Khi khách thanh toán hóa đơn</option>
                        <option value="completion">Khi KTV hoàn thành buổi làm</option>
                    </x-ui.select>

                    <x-ui.select label="Hình thức tính thưởng" wire:model.live="calculationType" :selected="$calculationType" required>
                        <option value="percentage">Theo tỷ lệ phần trăm (%)</option>
                        <option value="fixed">Số tiền cố định (₫)</option>
                    </x-ui.select>

                    <x-ui.input label="Giá trị hoa hồng ({{ $calculationType === 'percentage' ? '%' : '₫' }})"
                                type="number"
                                min="0"
                                max="{{ $calculationType === 'percentage' ? '100' : '100000000' }}"
                                step="{{ $calculationType === 'percentage' ? '0.1' : '1000' }}"
                                wire:model="commissionValue" :value="$commissionValue"
                                required />

                    <x-ui.select label="Trạng thái quy tắc" wire:model="commissionActive" :selected="$commissionActive" required>
                        <option value="1">Đang kích hoạt áp dụng</option>
                        <option value="0">Tạm dừng áp dụng</option>
                    </x-ui.select>

                    <x-ui.input label="Ngày bắt đầu hiệu lực" type="date" wire:model="startsOn" :value="$startsOn" required />

                    <x-ui.input label="Ngày kết thúc hiệu lực" type="date" wire:model="endsOn" :value="$endsOn" hint="Bỏ trống nếu áp dụng vô thời hạn" />

                    <div class="col-span-2">
                        <x-ui.input label="Ghi chú điều kiện thưởng (tùy chọn)" wire:model="commissionNotes" :value="$commissionNotes" placeholder="VD: Áp dụng cho khách VIP, hoặc ca làm cuối tuần..." />
                    </div>
                </div>
            </form>

            <x-slot:actions>
                <x-ui.button variant="secondary" type="button" wire:click="$set('showCommissionForm', false)">Hủy</x-ui.button>
                <x-ui.button type="submit" form="commission-form">{{ $editingCommissionId ? 'Lưu cập nhật' : 'Tạo quy tắc' }}</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif
</div>
