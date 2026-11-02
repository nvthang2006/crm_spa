@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'placeholder' => null,
    'options' => [],
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'value' => null,
    'selected' => null,
    'icon' => null,
    'searchable' => false,
    'clearable' => false,
])

@php
$modelName = $attributes->whereStartsWith('wire:model')->first();
$effectiveName = $name ?? $modelName;
$selectId = $id ?? ($effectiveName ? 'select-' . str_replace(['[', ']', '.', '$'], '-', $effectiveName) : 'spa-select-' . \Illuminate\Support\Str::random(8));
$hasError = !empty($error) || ($effectiveName && isset($errors) && $errors->has($effectiveName));
$errorMessage = $error ?? ($effectiveName && isset($errors) ? $errors->first($effectiveName) : null);
$hintId = $hint ? "{$selectId}-hint" : null;
$errorId = $hasError ? "{$selectId}-error" : null;

$customDescribedBy = $attributes->get('aria-describedby');
$customAriaLabel = $attributes->get('aria-label');
$describedBy = trim(($hintId ?? '') . ' ' . ($errorId ?? '') . ' ' . ($customDescribedBy ?? ''));

$selectedValue = (string) ($selected ?? $value ?? ($effectiveName ? old($effectiveName) : ''));

// Chuẩn hóa options thành định dạng giàu dữ liệu (label, icon, desc, badge)
$normalizedOptions = [];
foreach ($options as $val => $opt) {
    if (is_array($opt)) {
        $normalizedOptions[(string) $val] = [
            'label' => $opt['label'] ?? (string) $val,
            'icon' => $opt['icon'] ?? null,
            'desc' => $opt['desc'] ?? null,
            'badge' => $opt['badge'] ?? null,
        ];
    } else {
        $normalizedOptions[(string) $val] = [
            'label' => (string) $opt,
            'icon' => null,
            'desc' => null,
            'badge' => null,
        ];
    }
}

$initialLabel = $placeholder ?? '-- Chọn giá trị --';
$initialIcon = null;
if ($selectedValue !== '' && isset($normalizedOptions[$selectedValue])) {
    $initialLabel = $normalizedOptions[$selectedValue]['label'];
    $initialIcon = $normalizedOptions[$selectedValue]['icon'];
}
@endphp

<div
    x-data="{
        open: false,
        value: '{{ $selectedValue }}',
        options: {{ json_encode($normalizedOptions, JSON_UNESCAPED_UNICODE) }},
        selectedLabel: '{{ addslashes($initialLabel) }}',
        selectedIcon: '{{ addslashes($initialIcon ?? '') }}',
        searchQuery: '',
        placement: 'below',
        reposition() {
            const trigger = $el.querySelector('[role=combobox]');
            if (!trigger) return;
            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            this.placement = spaceBelow < 280 && rect.top > 280 ? 'above' : 'below';
        },
        init() {
            if ($refs.nativeSelect) {
                const parsed = {};
                Array.from($refs.nativeSelect.options).forEach(opt => {
                    if (opt.value) {
                        parsed[opt.value] = {
                            label: opt.text,
                            icon: opt.dataset.icon || null,
                            desc: opt.dataset.desc || null,
                            badge: opt.dataset.badge || null,
                        };
                    }
                });
                if (Object.keys(this.options).length === 0) {
                    this.options = parsed;
                }
                if (this.value && this.options[this.value]) {
                    this.selectedLabel = this.options[this.value].label;
                    this.selectedIcon = this.options[this.value].icon || '';
                }
            }
        },
        select(val) {
            this.value = val;
            if (val && this.options[val]) {
                this.selectedLabel = this.options[val].label;
                this.selectedIcon = this.options[val].icon || '';
            } else {
                this.selectedLabel = '{{ addslashes($placeholder ?? '-- Chọn giá trị --') }}';
                this.selectedIcon = '';
            }
            this.open = false;
            this.searchQuery = '';
            if ($refs.nativeSelect) {
                $refs.nativeSelect.value = val;
                $refs.nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                $refs.nativeSelect.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
        clear() {
            this.select('');
        },
        hasMatchingOptions() {
            if (!this.searchQuery) return true;
            const query = this.searchQuery.toLowerCase();
            return Object.values(this.options).some(item =>
                item.label.toLowerCase().includes(query) ||
                (item.desc && item.desc.toLowerCase().includes(query))
            );
        }
    }"
    @click.window.capture="if (!$el.contains($event.target)) open = false"
    @spa-field-popover-opened.window="if ($event.detail.id !== '{{ $selectId }}') open = false"
    {{ $attributes->only('class')->merge(['class' => 'spa-field flex flex-col gap-1.5 relative']) }}
>
    @if ($label)
        <label for="{{ $selectId }}-trigger" class="text-[12px] font-semibold text-[var(--spa-text-secondary)] flex items-center gap-1">
            <span>{{ $label }}</span>
            @if ($required)
                <span class="text-[var(--spa-danger)] font-bold" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative w-full">
        <!-- Native Select ẩn để phục vụ form submit, Livewire & PHPUnit tests -->
        <select
            x-ref="nativeSelect"
            id="{{ $selectId }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($disabled) disabled aria-disabled="true" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
            tabindex="-1"
            aria-hidden="true"
            style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0;"
            {{ $attributes->except(['class', 'aria-describedby', 'aria-label']) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            @if (!empty($options))
                @foreach ($options as $val => $opt)
                    @php
                        $optLabel = is_array($opt) ? ($opt['label'] ?? $val) : $opt;
                    @endphp
                    <option value="{{ $val }}" @selected((string) $val === $selectedValue && $selectedValue !== '')>{{ $optLabel }}</option>
                @endforeach
            @endif

            {{ $slot }}
        </select>

        <!-- Custom Trigger Button: thiết kế cao cấp, có chiều sâu & hỗ trợ Prefix Icon -->
        <button
            type="button"
            id="{{ $selectId }}-trigger"
            @if ($disabled) disabled aria-disabled="true" @else @click="open = !open; if(open) { $dispatch('spa-field-popover-opened', { id: '{{ $selectId }}' }); $nextTick(() => { reposition(); if({{ $searchable ? 'true' : 'false' }}) { $refs.searchInput?.focus() } }) }" @endif
            @keydown.escape.window="open = false"
            @resize.window="if (open) reposition()"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @if ($customAriaLabel) aria-label="{{ $customAriaLabel }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
            class="spa-select-trigger w-full min-h-[38px] rounded-[10px] border text-[13px] text-[var(--spa-text)] bg-white px-3 py-1.5 transition-all flex items-center justify-between cursor-pointer text-left focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 shadow-xs hover:shadow-sm {{ $hasError ? '!border-[var(--spa-danger)] !bg-[#FEF2F2]/30 focus:!ring-2 focus:!ring-[var(--spa-danger-soft)]' : 'border-[var(--spa-border-strong)] hover:border-[var(--spa-brand)]/50 focus:border-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand-soft)]' }} {{ $disabled ? 'opacity-60 cursor-not-allowed bg-[var(--spa-surface-muted)]' : '' }}"
            :class="open ? 'border-[var(--spa-brand)] ring-2 ring-[var(--spa-brand-soft)]' : ''"
        >
            <div class="flex items-center gap-2 min-w-0 flex-1 mr-1">
                @if ($icon)
                    <span class="material-symbols-outlined text-[18px] text-[var(--spa-brand)] shrink-0 select-none" aria-hidden="true">{{ $icon }}</span>
                @else
                    <span x-show="selectedIcon" class="material-symbols-outlined text-[18px] text-[var(--spa-brand)] shrink-0 select-none" x-text="selectedIcon" aria-hidden="true"></span>
                @endif

                <span
                    class="truncate select-none leading-normal"
                    :class="!value ? 'text-[var(--spa-text-muted)] font-normal' : 'text-[var(--spa-text)] font-medium'"
                    x-text="selectedLabel"
                >
                    {{ $initialLabel }}
                </span>
            </div>

            <div class="flex items-center gap-1 shrink-0 ml-1">
                @if ($clearable)
                    <span
                        x-show="value"
                        @click.stop="clear()"
                        title="Xóa lựa chọn"
                        class="p-0.5 rounded-full text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors flex items-center justify-center cursor-pointer"
                        aria-label="Xóa giá trị đã chọn"
                    >
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">close</span>
                    </span>
                @endif

                <span
                    class="material-symbols-outlined text-[18px] text-[var(--spa-text-muted)] transition-transform duration-200 pointer-events-none"
                    :class="open ? 'rotate-180 text-[var(--spa-brand)]' : ''"
                    aria-hidden="true"
                >
                    expand_more
                </span>
            </div>
        </button>

        <!-- Custom Dropdown Menu Popover: Floating Luxury Popover bo góc 12px, backdrop-blur, shadow-xl -->
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]"
            class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/98 backdrop-blur-md border border-slate-200/90 rounded-[12px] shadow-xl shadow-slate-900/10 p-1.5 max-h-64 overflow-y-auto ring-1 ring-black/5"
            :style="placement === 'above' ? 'top:auto; bottom:100%; margin-top:0; margin-bottom:.375rem;' : ''"
            style="display: none;"
            role="listbox"
        >
            @if ($searchable)
                <!-- Ô tìm kiếm thông minh nhanh trong Popover -->
                <div class="p-1 pb-1.5 border-b border-slate-100 mb-1 sticky top-0 bg-white/95 backdrop-blur z-10">
                    <div class="relative flex items-center">
                        <span class="material-symbols-outlined absolute left-2.5 text-[16px] text-slate-400 pointer-events-none" aria-hidden="true">search</span>
                        <input
                            type="text"
                            x-ref="searchInput"
                            x-model="searchQuery"
                            @click.stop
                            placeholder="Tìm kiếm lựa chọn..."
                            class="w-full pl-8 pr-2.5 py-1.5 text-[12px] bg-slate-50 border border-slate-200 rounded-[8px] focus:outline-none focus:border-[var(--spa-brand)] focus:bg-white focus:ring-1 focus:ring-[var(--spa-brand-soft)] transition-all"
                        />
                    </div>
                </div>
            @endif

            @if ($placeholder)
                <button
                    type="button"
                    @click="select('')"
                    x-show="!searchQuery || '{{ addslashes($placeholder) }}'.toLowerCase().includes(searchQuery.toLowerCase())"
                            class="spa-select-option w-full text-left px-2.5 py-1.5 text-[11px] font-medium text-slate-400 hover:bg-slate-50 hover:text-slate-600 transition-colors rounded-[8px] flex items-center justify-between mb-0.5 cursor-pointer"
                    role="option"
                    :aria-selected="!value"
                >
                    <span class="italic">{{ $placeholder }}</span>
                    <span x-show="!value" class="material-symbols-outlined text-[15px] text-slate-400" aria-hidden="true">check</span>
                </button>
            @endif

            <template x-for="(item, key) in options" :key="key">
                <button
                    type="button"
                    @click="select(key)"
                    x-show="!searchQuery || item.label.toLowerCase().includes(searchQuery.toLowerCase()) || (item.desc && item.desc.toLowerCase().includes(searchQuery.toLowerCase()))"
                    class="spa-select-option w-full text-left px-3 py-2 text-[12.5px] rounded-[8px] transition-all duration-150 flex items-center justify-between gap-2.5 cursor-pointer leading-tight group mb-0.5"
                    :class="value === String(key) ? 'bg-[var(--spa-brand-subtle)] text-[var(--spa-brand)] font-semibold border-l-[3px] border-[var(--spa-brand)] pl-[9px]' : 'text-slate-700 hover:bg-[var(--spa-brand-subtle)]/60 hover:text-[var(--spa-brand)] hover:translate-x-0.5'"
                    role="option"
                    :aria-selected="value === String(key)"
                >
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <!-- Icon đại diện của option -->
                        <template x-if="item.icon">
                            <span
                                class="material-symbols-outlined text-[18px] shrink-0 transition-colors"
                                :class="value === String(key) ? 'text-[var(--spa-brand)]' : 'text-slate-400 group-hover:text-[var(--spa-brand)]'"
                                x-text="item.icon"
                                aria-hidden="true"
                            ></span>
                        </template>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span x-text="item.label" class="spa-select-option-label truncate font-medium"></span>
                                <template x-if="item.badge">
                                    <span
                                        x-text="item.badge"
                                        class="spa-select-option-badge text-[10px] px-1.5 py-0.5 rounded-full font-medium shrink-0"
                                        :class="value === String(key) ? 'bg-[var(--spa-brand-soft)] text-[var(--spa-brand)]' : 'bg-slate-100 text-slate-600 group-hover:bg-[var(--spa-brand-soft)]/60 group-hover:text-[var(--spa-brand)]'"
                                    ></span>
                                </template>
                            </div>
                            <template x-if="item.desc">
                                <p x-text="item.desc" class="spa-select-option-description text-[11px] text-slate-400 group-hover:text-slate-500 mt-0.5 font-normal"></p>
                            </template>
                        </div>
                    </div>

                    <!-- Icon check khi được chọn -->
                    <span
                        x-show="value === String(key)"
                        class="material-symbols-outlined text-[16px] text-[var(--spa-brand)] shrink-0"
                        aria-hidden="true"
                    >
                        check_circle
                    </span>
                </button>
            </template>

            <!-- Trạng thái tìm kiếm không có kết quả -->
            <div
                x-show="searchQuery && !hasMatchingOptions()"
                class="px-3 py-4 text-center text-slate-400 text-[12px] flex flex-col items-center gap-1"
            >
                <span class="material-symbols-outlined text-[20px] text-slate-300" aria-hidden="true">search_off</span>
                <span>Không tìm thấy kết quả phù hợp</span>
            </div>
        </div>
    </div>

    @if ($hint && !$hasError)
        <p id="{{ $hintId }}" class="text-[11px] text-[var(--spa-text-muted)] m-0">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <div id="{{ $errorId }}" class="spa-error flex items-center gap-1.5 text-[11px] font-medium text-[var(--spa-danger)]" role="alert">
            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif
</div>
