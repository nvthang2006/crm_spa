<div class="spa-rooms-page">
    @if (session()->has('success'))
        <x-ui.alert variant="success" class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif

    @if (session()->has('error'))
        <x-ui.alert variant="danger" class="mb-4">{{ session('error') }}</x-ui.alert>
    @endif

    @error('resource')
        <x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <!-- TIÊU ĐỀ TRANG VÀ HÀNH ĐỘNG -->
    <x-ui.page-heading
        title="Sơ đồ phòng & giường vận hành"
        :description="'Theo dõi trạng thái không gian vật lý và công suất theo thời gian thực tại ' . config('spa.address') . '.'"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="refresh" wire:click="$refresh">
                Làm mới sơ đồ
            </x-ui.button>
            <a href="{{ route('appointments') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-white bg-[var(--spa-brand)] hover:bg-[var(--spa-brand-strong)] shadow-xs transition-colors">
                <span class="material-symbols-outlined text-[16px]">calendar_month</span>
                <span>Điều phối lịch hẹn</span>
            </a>
        </x-slot:actions>
    </x-ui.page-heading>

    <!-- 4 THẺ KPI NĂNG LỰC PHỤC VỤ (ĐỘ TƯƠNG PHẢN CAO, SẮC NÉT, CLICK 1 CHẠM ĐỂ LỌC) -->
    <div class="grid spa-summary-strip spa-summary-strip--4 spa-rooms-summary grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
        <!-- Thẻ 1: Tổng công suất -->
        <button
            type="button"
            wire:click="filterByStatus('all')"
            aria-pressed="{{ $statusFilter === 'all' ? 'true' : 'false' }}"
            class="spa-stat w-full text-left bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all cursor-pointer relative focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-2 {{ $statusFilter === 'all' ? 'ring-2 ring-[var(--spa-brand)] border-[var(--spa-brand)]' : '' }}"
            title="Nhấn để xem toàn bộ phòng/giường"
        >
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[12px] font-semibold text-slate-500 uppercase tracking-wider">Tổng năng lực cơ sở</span>
                <span class="w-7 h-7 rounded-lg bg-[var(--spa-surface-muted)] text-[var(--spa-text-secondary)] flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">domain</span>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-[24px] font-extrabold text-slate-900 tracking-tight">{{ $stats['total'] }}</div>
                <span class="text-[12px] font-medium text-slate-500">phòng & giường</span>
            </div>
            <div class="text-[11.5px] text-slate-500 flex items-center justify-between mt-2.5 pt-2 border-t border-slate-100">
                <span>Công suất sử dụng</span>
                <strong class="text-[var(--spa-brand)] font-bold text-[12px]">{{ $stats['occupancy_rate'] }}%</strong>
            </div>
        </button>

        <!-- Thẻ 2: Trống sẵn sàng -->
        <button
            type="button"
            wire:click="filterByStatus('ready')"
            aria-pressed="{{ $statusFilter === 'ready' ? 'true' : 'false' }}"
            class="spa-stat w-full text-left bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all cursor-pointer relative focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-2 {{ $statusFilter === 'ready' ? 'ring-2 ring-[var(--spa-brand)] border-[var(--spa-brand)]' : '' }}"
            title="Nhấn để lọc các giường sẵn sàng đón khách"
        >
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[12px] font-semibold text-slate-500 uppercase tracking-wider">Trống sạch sẵn sàng</span>
                <span class="w-7 h-7 rounded-lg bg-[var(--spa-surface-muted)] text-[var(--spa-text-secondary)] flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-[24px] font-extrabold text-[var(--spa-text)] tracking-tight">{{ $stats['ready'] }}</div>
                <span class="text-[12px] font-medium text-[var(--spa-text-secondary)]">giường đón khách</span>
            </div>
            <div class="text-[11.5px] text-slate-500 mt-2.5 pt-2 border-t border-slate-100">
                Sẵn sàng tiếp đón khách mới ngay
            </div>
        </button>

        <!-- Thẻ 3: Đang phục vụ -->
        <button
            type="button"
            wire:click="filterByStatus('busy')"
            aria-pressed="{{ $statusFilter === 'busy' ? 'true' : 'false' }}"
            class="spa-stat w-full text-left bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all cursor-pointer relative focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-2 {{ $statusFilter === 'busy' ? 'ring-2 ring-[var(--spa-brand)] border-[var(--spa-brand)]' : '' }}"
            title="Nhấn để lọc các giường đang có khách"
        >
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[12px] font-semibold text-slate-500 uppercase tracking-wider">Đang phục vụ</span>
                <span class="w-7 h-7 rounded-lg bg-[var(--spa-surface-muted)] text-[var(--spa-text-secondary)] flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">person_play</span>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-[24px] font-extrabold text-[var(--spa-text)] tracking-tight">{{ $stats['busy'] }}</div>
                <span class="text-[12px] font-medium text-[var(--spa-text-secondary)]">giường có khách</span>
            </div>
            <div class="text-[11.5px] text-slate-500 mt-2.5 pt-2 border-t border-slate-100">
                Đang trong ca dịch vụ liệu trình
            </div>
        </button>

        <!-- Thẻ 4: Cần xử lý -->
        <button
            type="button"
            wire:click="filterByStatus('cleaning')"
            aria-pressed="{{ in_array($statusFilter, ['cleaning', 'maintenance']) ? 'true' : 'false' }}"
            class="spa-stat w-full text-left bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all cursor-pointer relative focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-2 {{ in_array($statusFilter, ['cleaning', 'maintenance']) ? 'ring-2 ring-[var(--spa-brand)] border-[var(--spa-brand)]' : '' }}"
            title="Nhấn để lọc các giường cần dọn dẹp hoặc bảo trì"
        >
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[12px] font-semibold text-slate-500 uppercase tracking-wider">Cần xử lý & Chuẩn bị</span>
                <span class="w-7 h-7 rounded-lg bg-[var(--spa-surface-muted)] text-[var(--spa-text-secondary)] flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">cleaning_services</span>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-[24px] font-extrabold text-[var(--spa-text)] tracking-tight">{{ $stats['cleaning'] + $stats['maintenance'] }}</div>
                <span class="text-[12px] font-medium text-[var(--spa-text-secondary)]">vị trí</span>
            </div>
            <div class="text-[11.5px] text-slate-500 mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between">
                <span>{{ $stats['cleaning'] }} chờ dọn dẹp</span>
                <span class="text-slate-300">·</span>
                <span>{{ $stats['maintenance'] }} bảo trì</span>
            </div>
        </button>
    </div>

    <!-- TOOLBAR ĐIỀU KHIỂN & BỘ LỌC ĐA CHIỀU -->
    <div class="spa-rooms-toolbar bg-white p-3 rounded-xl border border-slate-200/90 shadow-xs mb-5 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Segmented Tabs lọc trạng thái -->
        <div class="spa-room-status-tabs flex items-center gap-1 bg-slate-100/90 p-1 rounded-lg overflow-x-auto text-[12px] font-medium">
            <button
                type="button"
                wire:click="$set('statusFilter', 'all')"
                class="px-3 py-1.5 rounded-md transition-all whitespace-nowrap cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 {{ $statusFilter === 'all' ? 'bg-white text-[var(--spa-brand)] font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
            >
                Tất cả ({{ $stats['total'] }})
            </button>
            <button
                type="button"
                wire:click="$set('statusFilter', 'ready')"
                class="px-3 py-1.5 rounded-md transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 {{ $statusFilter === 'ready' ? 'bg-[var(--spa-success)] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $statusFilter === 'ready' ? 'bg-white' : 'bg-[var(--spa-success)]' }}"></span>
                Trống sạch ({{ $stats['ready'] }})
            </button>
            <button
                type="button"
                wire:click="$set('statusFilter', 'busy')"
                class="px-3 py-1.5 rounded-md transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 {{ $statusFilter === 'busy' ? 'bg-[var(--spa-info)] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $statusFilter === 'busy' ? 'bg-white' : 'bg-[var(--spa-info)]' }}"></span>
                Đang phục vụ ({{ $stats['busy'] }})
            </button>
            <button
                type="button"
                wire:click="$set('statusFilter', 'cleaning')"
                class="px-3 py-1.5 rounded-md transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 {{ $statusFilter === 'cleaning' ? 'bg-[var(--spa-warning)] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $statusFilter === 'cleaning' ? 'bg-white' : 'bg-[var(--spa-warning)]' }}"></span>
                Chờ dọn ({{ $stats['cleaning'] }})
            </button>
            <button
                type="button"
                wire:click="$set('statusFilter', 'maintenance')"
                class="px-3 py-1.5 rounded-md transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 {{ $statusFilter === 'maintenance' ? 'bg-[var(--spa-danger)] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $statusFilter === 'maintenance' ? 'bg-white' : 'bg-[var(--spa-danger)]' }}"></span>
                Bảo trì ({{ $stats['maintenance'] }})
            </button>
        </div>

        <!-- Bộ lọc Khu vực/Tầng và Tìm kiếm -->
        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
            <!-- Tìm kiếm nhanh -->
            <div class="spa-room-search relative min-w-[200px] flex-1">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[17px] text-slate-400 pointer-events-none">search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    aria-label="Tìm phòng hoặc giường"
                    placeholder="Tìm tên hoặc mã giường..."
                    class="spa-room-search-input w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[12px] focus:outline-none focus:border-[var(--spa-brand)] focus:bg-white focus:ring-1 focus:ring-[var(--spa-brand-soft)] transition-all"
                />
            </div>

            <!-- Dropdown chọn Khu vực / Tầng -->
            <div class="min-w-[190px]">
                <x-ui.select
                    wire:model.live="areaFilter"
                    aria-label="Lọc theo tầng hoặc khu vực"
                    icon="meeting_room"
                >
                    <option value="all">Tất cả khu vực</option>
                    @foreach ($allAreas as $a)
                        <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->floor }})</option>
                    @endforeach
                </x-ui.select>
            </div>

            @if ($statusFilter !== 'all' || $areaFilter !== 'all' || $search !== '')
                <button
                    type="button"
                    wire:click="resetFilters"
                    title="Đặt lại bộ lọc"
                    class="spa-room-reset p-2 text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors flex items-center justify-center shrink-0 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[17px]">filter_alt_off</span>
                </button>
            @endif
        </div>
    </div>

    <!-- DANH SÁCH KHU VỰC PHÒNG & GIƯỜNG (SPATIAL BOARDS) -->
    <div class="spa-rooms-board space-y-6">
        @forelse ($areas as $area)
            @php
                $areaResources = $area->resources;
                $areaTotal = $areaResources->count();
                $areaBusy = $areaResources->where('status', 'busy')->count();
                $areaReady = $areaResources->where('status', 'ready')->count();
                $areaCleaning = $areaResources->where('status', 'cleaning')->count();
                $areaMaintenance = $areaResources->where('status', 'maintenance')->count();

                // Xác định icon khu vực theo tên hoặc công năng
                $areaIcon = 'spa';
                if (str_contains(mb_strtolower($area->name), 'body') || str_contains(mb_strtolower($area->name), 'massage')) {
                    $areaIcon = 'self_improvement';
                } elseif (str_contains(mb_strtolower($area->name), 'gội') || str_contains(mb_strtolower($area->name), 'dưỡng sinh')) {
                    $areaIcon = 'wash';
                } elseif (str_contains(mb_strtolower($area->name), 'vip')) {
                    $areaIcon = 'workspace_premium';
                }
            @endphp

            <section class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <!-- Header Khu vực với Capacity Bar -->
                <div class="bg-slate-50/70 px-5 py-3.5 border-b border-slate-200/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[var(--spa-brand)]/10 text-[var(--spa-brand)] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">{{ $areaIcon }}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-[14px] font-bold text-slate-800">{{ $area->name }}</h2>
                                <span class="text-[11px] font-semibold text-[var(--spa-brand)] bg-[var(--spa-brand-soft)] px-2 py-0.5 rounded-full">{{ $area->floor }}</span>
                            </div>
                            <p class="text-[11.5px] text-slate-500 mt-0.5">{{ $area->description }}</p>
                        </div>
                    </div>

                    <!-- Thanh tỷ lệ công suất và chỉ số khu vực -->
                    <div class="flex items-center gap-4 self-end md:self-auto">
                        @if ($areaTotal > 0)
                            <div class="flex flex-col items-end gap-1">
                                <div class="flex items-center gap-2 text-[11.5px] text-slate-600 font-medium">
                                    <span class="text-[var(--spa-info)] font-bold">{{ $areaBusy }} bận</span>
                                    <span class="text-slate-300">·</span>
                                    <span class="text-[var(--spa-success)] font-bold">{{ $areaReady }} trống</span>
                                    @if ($areaCleaning > 0)
                                        <span class="text-slate-300">·</span>
                                        <span class="text-[var(--spa-warning)] font-bold">{{ $areaCleaning }} dọn</span>
                                    @endif
                                </div>

                                <!-- Thanh đo năng lực màu sắc trực quan -->
                                <div class="spa-capacity-bar" title="Công suất lấp đầy: {{ $areaBusy }}/{{ $areaTotal }}">
                                    @if ($areaBusy > 0)
                                        <div style="width: {{ ($areaBusy / $areaTotal) * 100 }}%" class="bg-[var(--spa-info)] h-full"></div>
                                    @endif
                                    @if ($areaReady > 0)
                                        <div style="width: {{ ($areaReady / $areaTotal) * 100 }}%" class="bg-[var(--spa-success)] h-full"></div>
                                    @endif
                                    @if ($areaCleaning > 0)
                                        <div style="width: {{ ($areaCleaning / $areaTotal) * 100 }}%" class="bg-[var(--spa-warning)] h-full"></div>
                                    @endif
                                    @if ($areaMaintenance > 0)
                                        <div style="width: {{ ($areaMaintenance / $areaTotal) * 100 }}%" class="bg-[var(--spa-danger)] h-full"></div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <span class="text-[11.5px] font-semibold text-slate-600 bg-white px-2.5 py-1 rounded-lg border border-slate-200">
                            {{ $areaTotal }} tài nguyên
                        </span>
                    </div>
                </div>

                <!-- Lưới hiển thị các thẻ Giường/Phòng (Sleek Spa Spatial Tiles) -->
                @if ($areaResources->count() > 0)
                    <div class="spa-room-grid">
                        @foreach ($areaResources as $resource)
                            <article class="spa-room {{ $resource->status }}">
                                <!-- TẦNG 1: HEADER THẺ (TÊN, MÃ SỐ & TRẠNG THÁI) -->
                                <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $resource->status === 'busy' ? 'bg-[var(--spa-info-soft)] text-[var(--spa-info)]' : ($resource->status === 'ready' ? 'bg-[var(--spa-success-soft)] text-[var(--spa-success)]' : ($resource->status === 'cleaning' ? 'bg-[var(--spa-warning-soft)] text-[var(--spa-warning)]' : 'bg-[var(--spa-danger-soft)] text-[var(--spa-danger)]')) }}">
                                            <span class="material-symbols-outlined text-[18px]">
                                                {{ $resource->resource_type === 'room' ? 'meeting_room' : 'bed' }}
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="truncate">{{ $resource->name }}</h3>
                                            <span class="text-[10.5px] font-bold text-slate-400 tracking-wider uppercase">{{ $resource->code }}</span>
                                        </div>
                                    </div>

                                    <!-- Status badge thanh lịch có chỉ báo trạng thái -->
                                    @if ($resource->status === 'busy')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[var(--spa-info-soft)] text-[var(--spa-info)] border border-[var(--spa-info-border)]/80">
                                            <span class="w-2 h-2 rounded-full bg-[var(--spa-info)] "></span>
                                            Đang có khách
                                        </span>
                                    @elseif ($resource->status === 'ready')
                                        <div class="flex items-center gap-1">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[var(--spa-success-soft)] text-[var(--spa-success)] border border-[var(--spa-success-border)]/80">
                                                <span class="w-2 h-2 rounded-full bg-[var(--spa-success)]"></span>
                                                Sẵn sàng
                                            </span>
                                            <button
                                                type="button"
                                                title="Đưa giường vào bảo trì"
                                                wire:click="openMaintenance({{ $resource->id }})"
                                                class="p-1 text-slate-400 hover:text-[var(--spa-danger)] hover:bg-[var(--spa-danger-soft)] rounded-md transition-colors cursor-pointer"
                                            >
                                                <span class="material-symbols-outlined text-[15px]">build</span>
                                            </button>
                                        </div>
                                    @elseif ($resource->status === 'cleaning')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[var(--spa-warning-soft)] text-[var(--spa-warning)] border border-[var(--spa-warning-border)]/80">
                                            <span class="w-2 h-2 rounded-full bg-[var(--spa-warning)]"></span>
                                            Cần dọn dẹp
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[var(--spa-danger-soft)] text-[var(--spa-danger)] border border-[var(--spa-danger-border)]/80">
                                            <span class="w-2 h-2 rounded-full bg-[var(--spa-danger)]"></span>
                                            Đang bảo trì
                                        </span>
                                    @endif
                                </div>

                                <!-- TẦNG 2: THÂN THẺ (BODY - CÂN ĐỐI, ĐẦY ĐỦ THÔNG TIN, KHÔNG KHOẢNG TRẮNG RỖNG) -->
                                <div class="py-3 flex-1 flex flex-col justify-between gap-2.5">
                                    @if ($resource->status === 'busy')
                                        <!-- TRẠNG THÁI: ĐANG PHỤC VỤ (SESSION TRỰC TIẾP) -->
                                        @if ($resource->active_appointment)
                                            @php
                                                $custName = $resource->active_appointment->customer->name ?? 'Khách vãng lai';
                                                $words = explode(' ', trim($custName));
                                                $initials = mb_substr(end($words) ?: 'K', 0, 1);
                                            @endphp
                                            <!-- Khách hàng kèm Avatar Initials -->
                                            <div class="flex items-center gap-2.5 bg-slate-50/80 p-2 rounded-xl border border-slate-100">
                                                <div class="w-8 h-8 rounded-full bg-[var(--spa-info-soft)] text-[var(--spa-info)] font-bold text-xs flex items-center justify-center shrink-0">
                                                    {{ $initials }}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-[12.5px] font-bold text-slate-800 truncate">{{ $custName }}</span>
                                                        <span class="text-[10.5px] font-semibold text-[var(--spa-info)] bg-[var(--spa-info-soft)]/70 px-1.5 py-0.5 rounded">
                                                            {{ $resource->active_appointment->start_time }} - {{ $resource->active_appointment->end_time }}
                                                        </span>
                                                    </div>
                                                    <span class="text-[11px] text-slate-500">{{ $resource->active_appointment->customer->phone ?? 'Chưa có SĐT' }}</span>
                                                </div>
                                            </div>

                                            <!-- Dịch vụ đang làm -->
                                            <div class="flex items-center gap-2 text-[12px] px-1 text-[var(--spa-info)] font-medium truncate">
                                                <span class="material-symbols-outlined text-[16px] text-[var(--spa-info)] shrink-0">spa</span>
                                                <span class="truncate">{{ $resource->active_appointment->offering->name ?? 'Dịch vụ liệu trình' }}</span>
                                            </div>

                                            <!-- KTV & Khung giờ -->
                                            <div class="flex items-center justify-between text-[11.5px] text-slate-500 pt-1.5 border-t border-slate-100 px-1">
                                                <span class="flex items-center gap-1 truncate">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">badge</span>
                                                    <span>KTV: <strong class="text-slate-800">{{ $resource->active_appointment->primaryEmployee->name ?? 'Chưa gán' }}</strong></span>
                                                </span>
                                                <span class="text-slate-400 text-[10.5px] shrink-0">Đang thực hiện</span>
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center justify-center py-4 text-slate-400 text-[12px]">
                                                <span class="material-symbols-outlined text-[24px] text-[var(--spa-info)] mb-1">schedule</span>
                                                <span class="font-semibold text-slate-700">Đang có ca phục vụ</span>
                                                <span class="text-[11px] text-slate-400">Xem chi tiết ca trong lịch hẹn</span>
                                            </div>
                                        @endif

                                    @elseif ($resource->status === 'ready')
                                        <!-- TRẠNG THÁI: TRỐNG SẠCH (THÔNG TIN PHÒNG SẴN SÀNG) -->
                                        <div class="bg-[var(--spa-success-soft)]/40 border border-[var(--spa-success-border)]/80 rounded-xl p-2.5 flex items-start gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-[var(--spa-success-soft)] text-[var(--spa-success)] flex items-center justify-center shrink-0 mt-0.5">
                                                <span class="material-symbols-outlined text-[16px]">verified</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[12px] font-bold text-[var(--spa-success)] leading-tight">Phòng đã tiệt trùng</p>
                                                <p class="text-[11px] text-[var(--spa-success)]/80 mt-1 leading-normal">Ga trải & khăn mới đã chuẩn bị sẵn sàng</p>
                                            </div>
                                        </div>

                                        <!-- Tiện ích sẵn sàng -->
                                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1.5 border-t border-slate-100 px-1">
                                            <span class="flex items-center gap-1 text-[var(--spa-success)] font-medium">
                                                <span class="material-symbols-outlined text-[14px]">check</span>
                                                <span>Khăn sạch & máy sẵn sàng</span>
                                            </span>
                                            <span class="text-[var(--spa-success)] font-bold">100%</span>
                                        </div>

                                    @elseif ($resource->status === 'cleaning')
                                        <!-- TRẠNG THÁI: CHỜ DỌN DẸP -->
                                        <div class="bg-[var(--spa-warning-soft)]/50 border border-[var(--spa-warning-border)]/70 rounded-xl p-2.5 flex items-start gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-[var(--spa-warning-soft)] text-[var(--spa-warning)] flex items-center justify-center shrink-0 mt-0.5">
                                                <span class="material-symbols-outlined text-[16px]">cleaning_services</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[12px] font-bold text-[var(--spa-warning)] leading-tight">Cần thay drap & khử khuẩn</p>
                                                <p class="text-[11px] text-[var(--spa-warning)]/80 mt-1 leading-normal">Ca phục vụ vừa kết thúc. Vui lòng tiệt trùng giường</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between text-[11px] text-[var(--spa-warning)] font-medium pt-1.5 border-t border-slate-100 px-1">
                                            <span class="flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                                <span>Chờ buồng phòng dọn</span>
                                            </span>
                                            <span class="text-[var(--spa-warning)] font-bold">Ưu tiên cao</span>
                                        </div>

                                    @else
                                        <!-- TRẠNG THÁI: ĐANG BẢO TRÌ -->
                                        <div class="bg-[var(--spa-danger-soft)]/50 border border-[var(--spa-danger-border)]/70 rounded-xl p-2.5 flex items-start gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-[var(--spa-danger-soft)] text-[var(--spa-danger)] flex items-center justify-center shrink-0 mt-0.5">
                                                <span class="material-symbols-outlined text-[16px]">build</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[12px] font-bold text-[var(--spa-danger)] leading-tight">Tạm ngưng phục vụ</p>
                                                <p class="text-[11px] text-[var(--spa-danger)]/80 mt-1 leading-normal italic line-clamp-2">
                                                    {{ $resource->maintenance_note ?: 'Đang bảo dưỡng định kỳ cơ sở vật chất.' }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1.5 border-t border-slate-100 px-1">
                                            <span>Dự kiến hoàn thành:</span>
                                            <strong class="text-[var(--spa-danger)] font-semibold">{{ $resource->maintenance_until ? $resource->maintenance_until->format('H:i d/m') : 'Chưa định' }}</strong>
                                        </div>
                                    @endif
                                </div>

                                <!-- TẦNG 3: FOOTER HÀNH ĐỘNG (ĐỒNG BỘ CHIỀU CAO CHUẨN 36PX) -->
                                <div class="pt-3 border-t border-slate-100 mt-auto">
                                    @if ($resource->status === 'busy')
                                        <a
                                            href="{{ route('appointments') }}"
                                            class="w-full h-9 inline-flex items-center justify-center gap-1.5 text-[12px] font-semibold text-[var(--spa-info)] bg-[var(--spa-info-soft)] hover:bg-[var(--spa-info-soft)] border border-[var(--spa-info-border)]/70 rounded-lg transition-colors cursor-pointer"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                                            <span>Xem lịch hẹn chi tiết</span>
                                        </a>
                                    @elseif ($resource->status === 'ready')
                                        <a
                                            href="{{ route('appointments') }}?resource_id={{ $resource->id }}"
                                            class="w-full h-9 inline-flex items-center justify-center gap-1.5 text-[12px] font-semibold text-white bg-[var(--spa-brand)] hover:bg-[var(--spa-brand-strong)] rounded-lg shadow-xs transition-colors cursor-pointer"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">add_circle</span>
                                            <span>Xếp lịch đón khách</span>
                                        </a>
                                    @elseif ($resource->status === 'cleaning')
                                        <button
                                            type="button"
                                            wire:click="setReady({{ $resource->id }})"
                                            class="w-full h-9 inline-flex items-center justify-center gap-1.5 text-[12px] font-semibold text-[var(--spa-warning)] bg-[var(--spa-warning-soft)] hover:bg-[var(--spa-warning-border)] border border-[var(--spa-warning-border)]/80 rounded-lg transition-colors cursor-pointer shadow-xs"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">cleaning_services</span>
                                            <span>Xác nhận đã dọn sạch</span>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="setReady({{ $resource->id }})"
                                            class="w-full h-9 inline-flex items-center justify-center gap-1.5 text-[12px] font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300/70 rounded-lg transition-colors cursor-pointer"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">check</span>
                                            <span>Kết thúc bảo trì</span>
                                        </button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center text-slate-400 text-[12.5px]">
                        <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">meeting_room</span>
                        <p>Không có phòng hoặc giường nào phù hợp với bộ lọc hiện tại.</p>
                    </div>
                @endif
            </section>
        @empty
            <x-ui.empty-state
                title="Không tìm thấy khu vực hoặc phòng/giường phù hợp"
                description="Thử thay đổi bộ lọc trạng thái hoặc từ khóa tìm kiếm để xem kết quả."
                icon="meeting_room"
            />
        @endforelse
    </div>

    <!-- MODAL DIALOG BẢO TRÌ PHÒNG/GIƯỜNG (<X-UI.MODAL>) -->
    <x-ui.modal
        wire:model="showMaintenance"
        title="Đưa phòng/giường vào bảo trì"
        max-width="md"
    >
        <form wire:submit="saveMaintenance" class="space-y-4">
            <div class="p-3 bg-[var(--spa-danger-soft)]/80 border border-[var(--spa-danger-border)] rounded-lg flex items-center gap-2.5 text-[var(--spa-danger)] text-[12px]">
                <span class="material-symbols-outlined text-[20px] text-[var(--spa-danger)] shrink-0">build</span>
                <div>
                    <span class="font-bold">Đang cấu hình:</span>
                    <span class="font-semibold text-slate-800">{{ $maintenanceResourceName }}</span>
                </div>
            </div>

            <x-ui.textarea
                label="Nội dung / Lý do bảo trì"
                wire:model="maintenanceNote"
                placeholder="Ví dụ: Thay đệm mới, sửa hệ thống đèn sưởi ấm, bảo dưỡng máy..."
                required
            />

            <x-ui.input
                label="Dự kiến hoàn thành (Tùy chọn)"
                type="datetime-local"
                wire:model="maintenanceUntil"
                hint="Hệ thống sẽ nhắc nhở khi đến thời gian dự kiến kết thúc bảo trì."
            />

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    wire:click="$set('showMaintenance', false)"
                >
                    Hủy bỏ
                </x-ui.button>
                <x-ui.button
                    type="submit"
                    variant="danger"
                    icon="build"
                >
                    Xác nhận bảo trì
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
