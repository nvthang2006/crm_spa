@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'prefix' => null,
    'suffix' => null,
])

@php
$modelName = $attributes->whereStartsWith('wire:model')->first();
$effectiveName = $name ?? $modelName;
$inputId = $id ?? ($effectiveName ? 'input-' . str_replace(['[', ']', '.', '$'], '-', $effectiveName) : 'spa-input-' . \Illuminate\Support\Str::random(8));
$hasError = !empty($error) || ($effectiveName && isset($errors) && $errors->has($effectiveName));
$errorMessage = $error ?? ($effectiveName && isset($errors) ? $errors->first($effectiveName) : null);
$hintId = $hint ? "{$inputId}-hint" : null;
$errorId = $hasError ? "{$inputId}-error" : null;

$customDescribedBy = $attributes->get('aria-describedby');
$describedBy = trim(($hintId ?? '') . ' ' . ($errorId ?? '') . ' ' . ($customDescribedBy ?? ''));

$inputValue = (string) ($value ?? ($effectiveName ? old($effectiveName) : ''));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'spa-field flex flex-col gap-1.5']) }}>
    @if ($label)
        <label for="{{ $inputId }}" class="text-[12px] font-semibold text-[var(--spa-text-secondary)] flex items-center gap-1">
            <span>{{ $label }}</span>
            @if ($required)
                <span class="text-[var(--spa-danger)] font-bold" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    @if ($type === 'date')
        {{-- CUSTOM DATE PICKER: Thẻ lịch tinh tế, chọn nhanh hôm nay, ngày mai, +7 ngày --}}
        <div
            x-data="{
                open: false,
                value: '{{ $inputValue }}',
                viewYear: 2026,
                viewMonth: 8,
                monthNames: ['Tháng 01', 'Tháng 02', 'Tháng 03', 'Tháng 04', 'Tháng 05', 'Tháng 06', 'Tháng 07', 'Tháng 08', 'Tháng 09', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
                init() {
                    if (this.value) {
                        const parts = this.value.split('-');
                        if (parts.length >= 3) {
                            this.viewYear = parseInt(parts[0], 10);
                            this.viewMonth = parseInt(parts[1], 10) - 1;
                        }
                    } else {
                        const now = new Date();
                        this.viewYear = now.getFullYear();
                        this.viewMonth = now.getMonth();
                    }
                },
                daysInMonth() {
                    return new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                },
                startDay() {
                    const day = new Date(this.viewYear, this.viewMonth, 1).getDay();
                    return day === 0 ? 6 : day - 1;
                },
                prevMonth() {
                    if (this.viewMonth === 0) {
                        this.viewMonth = 11;
                        this.viewYear--;
                    } else {
                        this.viewMonth--;
                    }
                },
                nextMonth() {
                    if (this.viewMonth === 11) {
                        this.viewMonth = 0;
                        this.viewYear++;
                    } else {
                        this.viewMonth++;
                    }
                },
                selectDay(d) {
                    const m = String(this.viewMonth + 1).padStart(2, '0');
                    const dayStr = String(d).padStart(2, '0');
                    this.value = `${this.viewYear}-${m}-${dayStr}`;
                    this.open = false;
                    this.sync();
                },
                setOffsetDays(offset) {
                    const target = new Date();
                    target.setDate(target.getDate() + offset);
                    this.viewYear = target.getFullYear();
                    this.viewMonth = target.getMonth();
                    this.selectDay(target.getDate());
                },
                setWeekend() {
                    const now = new Date();
                    const day = now.getDay();
                    const distToSat = (6 - day + 7) % 7 || 7;
                    this.setOffsetDays(distToSat);
                },
                clearValue() {
                    this.value = '';
                    this.open = false;
                    this.sync();
                },
                sync() {
                    if ($refs.inputEl) {
                        $refs.inputEl.value = this.value;
                        $refs.inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                        $refs.inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                },
                isSelected(d) {
                    if (!this.value) return false;
                    const m = String(this.viewMonth + 1).padStart(2, '0');
                    const dayStr = String(d).padStart(2, '0');
                    return this.value === `${this.viewYear}-${m}-${dayStr}`;
                },
                isToday(d) {
                    const now = new Date();
                    return this.viewYear === now.getFullYear() && this.viewMonth === now.getMonth() && d === now.getDate();
                }
            }"
            x-init="$watch('open', isOpen => { if (isOpen) $dispatch('spa-field-popover-opened', { id: '{{ $inputId }}' }); })"
            @spa-field-popover-opened.window="if ($event.detail.id !== '{{ $inputId }}') open = false"
            @click.window.capture="if (!$el.contains($event.target)) open = false"
            class="relative flex items-center w-full"
        >
            <input
                x-ref="inputEl"
                id="{{ $inputId }}"
                type="text"
                readonly
                @if ($name) name="{{ $name }}" @endif
                :value="value"
                @if ($disabled) disabled aria-disabled="true" @else @click="open = !open" @endif
                @if ($required) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif
                @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
                placeholder="YYYY-MM-DD"
                {{ $attributes->except(['class', 'aria-describedby'])->merge([
                    'class' => 'spa-input w-full h-9 rounded-[8px] border text-[12px] text-[var(--spa-text)] bg-white transition-all focus:outline-none !pl-3 !pr-9 cursor-pointer ' .
                        ($hasError
                            ? '!border-[var(--spa-danger)] !bg-[#FEF2F2]/30 focus:!border-[var(--spa-danger)] focus:!ring-2 focus:!ring-[var(--spa-danger-soft)]'
                            : 'border-[var(--spa-border-strong)] hover:border-[var(--spa-text-muted)] focus:border-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand-soft)]') .
                        ($disabled ? ' opacity-60 cursor-not-allowed bg-[var(--spa-surface-muted)]' : '')
                ]) }}
            />

            <button
                type="button"
                @if (!$disabled) @click="open = !open" @endif
                class="spa-date-trigger absolute right-0 top-0 h-full w-11 flex items-center justify-center text-[var(--spa-brand)] hover:text-[#0c5c56] transition cursor-pointer"
                aria-label="Chọn ngày trên lịch"
            >
                <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            </button>

            <!-- Custom Calendar Popover: Có dải chọn nhanh Hôm nay, Ngày mai, Cuối tuần, +7 ngày -->
            <div
                x-show="open"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-120"
                x-transition:enter-start="opacity-0 translate-y-0.5 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-80"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-0.5 scale-[0.98]"
                class="spa-date-popover absolute left-0 top-full mt-1 z-50 bg-white border border-slate-200/90 rounded-[10px] shadow-lg shadow-slate-900/10 p-2.5 w-[256px]"
                style="display: none;"
            >
                <!-- Dải nút chọn nhanh ngày -->
                <div class="flex items-center gap-1 mb-2 pb-2 border-b border-slate-100">
                    <button type="button" @click.stop="setOffsetDays(0)" class="flex-1 py-0.5 text-[10px] font-medium rounded-[4px] border border-slate-200 text-slate-600 hover:bg-[var(--spa-brand-subtle)] hover:text-[var(--spa-brand)] hover:border-[var(--spa-brand-soft)] transition cursor-pointer text-center">Hôm nay</button>
                    <button type="button" @click.stop="setOffsetDays(1)" class="flex-1 py-0.5 text-[10px] font-medium rounded-[4px] border border-slate-200 text-slate-600 hover:bg-[var(--spa-brand-subtle)] hover:text-[var(--spa-brand)] hover:border-[var(--spa-brand-soft)] transition cursor-pointer text-center">Ngày mai</button>
                    <button type="button" @click.stop="setWeekend()" class="flex-1 py-0.5 text-[10px] font-medium rounded-[4px] border border-slate-200 text-slate-600 hover:bg-[var(--spa-brand-subtle)] hover:text-[var(--spa-brand)] hover:border-[var(--spa-brand-soft)] transition cursor-pointer text-center">Cuối tuần</button>
                    <button type="button" @click.stop="setOffsetDays(7)" class="flex-1 py-0.5 text-[10px] font-medium rounded-[4px] border border-slate-200 text-slate-600 hover:bg-[var(--spa-brand-subtle)] hover:text-[var(--spa-brand)] hover:border-[var(--spa-brand-soft)] transition cursor-pointer text-center">+7 ngày</button>
                </div>

                <!-- Điều hướng Tháng & Năm -->
                <div class="flex items-center justify-between mb-2 pb-1 border-b border-slate-100">
                    <button
                        type="button"
                        @click.stop="prevMonth()"
                        class="w-6 h-6 rounded-md flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                    </button>
                    <span class="text-[12px] font-bold text-slate-800" x-text="`${monthNames[viewMonth]}, ${viewYear}`"></span>
                    <button
                        type="button"
                        @click.stop="nextMonth()"
                        class="w-6 h-6 rounded-md flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    </button>
                </div>

                <!-- Hàng thứ -->
                <div class="grid grid-cols-7 gap-0.5 text-center mb-1">
                    <span class="text-[10px] font-medium text-slate-400">T2</span>
                    <span class="text-[10px] font-medium text-slate-400">T3</span>
                    <span class="text-[10px] font-medium text-slate-400">T4</span>
                    <span class="text-[10px] font-medium text-slate-400">T5</span>
                    <span class="text-[10px] font-medium text-slate-400">T6</span>
                    <span class="text-[10px] font-medium text-slate-400">T7</span>
                    <span class="text-[10px] font-semibold text-[var(--spa-danger)]">CN</span>
                </div>

                <!-- Lưới ngày -->
                <div class="grid grid-cols-7 gap-0.5 text-center">
                    <template x-for="i in startDay()" :key="'empty-' + i">
                        <span class="w-7 h-7"></span>
                    </template>
                    <template x-for="d in daysInMonth()" :key="'day-' + d">
                        <button
                            type="button"
                            @click.stop="selectDay(d)"
                            class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-normal transition cursor-pointer"
                            :class="{
                                'bg-[var(--spa-brand)] text-white font-bold shadow-sm shadow-[var(--spa-brand)]/30': isSelected(d),
                                'border border-[var(--spa-brand)] text-[var(--spa-brand)] font-semibold hover:bg-[var(--spa-brand-soft)]': isToday(d) && !isSelected(d),
                                'text-slate-700 hover:bg-slate-100 hover:text-slate-900': !isSelected(d) && !isToday(d)
                            }"
                            x-text="d"
                        ></button>
                    </template>
                </div>

                <!-- Chân lịch -->
                <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-slate-100 text-[11px]">
                    <button
                        type="button"
                        @click.stop="clearValue()"
                        class="text-slate-400 hover:text-[var(--spa-danger)] font-normal transition cursor-pointer"
                    >
                        Xóa
                    </button>
                    <button
                        type="button"
                        @click.stop="open = false"
                        class="text-[var(--spa-brand)] font-semibold hover:underline transition cursor-pointer"
                    >
                        Đóng
                    </button>
                </div>
            </div>
        </div>

    @elseif ($type === 'time')
        {{-- CUSTOM TIME PICKER: CÓ KÉO THẢ SLIDER VÀ CÁC NÚT CHỌN NHANH THEO CA --}}
        <div
            x-data="{
                open: false,
                value: '{{ $inputValue ?: '14:30' }}',
                sliderMins: 870, {{-- 14:30 = 14*60 + 30 = 870 --}}
                init() {
                    if (this.value) {
                        const parts = this.value.split(':').map(Number);
                        if (parts.length >= 2) {
                            this.sliderMins = (parts[0] || 14) * 60 + (parts[1] || 0);
                        }
                    } else {
                        this.value = '14:30';
                        this.sliderMins = 870;
                    }
                },
                onSlider(val) {
                    this.sliderMins = parseInt(val, 10);
                    const h = String(Math.floor(this.sliderMins / 60)).padStart(2, '0');
                    const m = String(this.sliderMins % 60).padStart(2, '0');
                    this.value = `${h}:${m}`;
                    this.sync();
                },
                selectQuick(slot) {
                    this.value = slot;
                    const parts = slot.split(':').map(Number);
                    this.sliderMins = (parts[0] || 14) * 60 + (parts[1] || 0);
                    this.sync();
                },
                selectNow() {
                    const now = new Date();
                    const h = String(now.getHours()).padStart(2, '0');
                    const m = String(Math.round(now.getMinutes() / 5) * 5 % 60).padStart(2, '0');
                    this.selectQuick(`${h}:${m}`);
                },
                clearValue() {
                    this.value = '';
                    this.open = false;
                    this.sync();
                },
                sync() {
                    if ($refs.inputEl) {
                        $refs.inputEl.value = this.value;
                        $refs.inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                        $refs.inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }"
            x-init="$watch('open', isOpen => { if (isOpen) $dispatch('spa-field-popover-opened', { id: '{{ $inputId }}' }); })"
            @spa-field-popover-opened.window="if ($event.detail.id !== '{{ $inputId }}') open = false"
            @click.window.capture="if (!$el.contains($event.target)) open = false"
            class="relative flex items-center w-full"
        >
            <input
                x-ref="inputEl"
                id="{{ $inputId }}"
                type="text"
                readonly
                @if ($name) name="{{ $name }}" @endif
                :value="value"
                @if ($disabled) disabled aria-disabled="true" @else @click="open = !open" @endif
                @if ($required) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif
                @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
                placeholder="HH:mm"
                {{ $attributes->except(['class', 'aria-describedby'])->merge([
                    'class' => 'spa-input w-full h-9 rounded-[8px] border text-[12px] text-[var(--spa-text)] bg-white transition-all focus:outline-none !pl-3 !pr-9 cursor-pointer font-medium ' .
                        ($hasError
                            ? '!border-[var(--spa-danger)] !bg-[#FEF2F2]/30 focus:!border-[var(--spa-danger)] focus:!ring-2 focus:!ring-[var(--spa-danger-soft)]'
                            : 'border-[var(--spa-border-strong)] hover:border-[var(--spa-text-muted)] focus:border-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand-soft)]') .
                        ($disabled ? ' opacity-60 cursor-not-allowed bg-[var(--spa-surface-muted)]' : '')
                ]) }}
            />

            <button
                type="button"
                @if (!$disabled) @click="open = !open" @endif
                class="spa-time-trigger absolute right-0 top-0 h-full w-11 flex items-center justify-center text-[var(--spa-brand)] hover:text-[#0c5c56] transition cursor-pointer"
                aria-label="Chọn giờ"
            >
                <span class="material-symbols-outlined text-[18px]">schedule</span>
            </button>

            <!-- Custom Clock Popover: Có Thanh Trượt Kéo Thả + Chọn Nhanh -->
            <div
                x-show="open"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-120"
                x-transition:enter-start="opacity-0 translate-y-0.5 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-80"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-0.5 scale-[0.98]"
                class="spa-time-popover absolute left-0 top-full mt-1 z-50 bg-white border border-slate-200/90 rounded-[12px] shadow-xl shadow-slate-900/10 p-3.5 w-[275px]"
                style="display: none;"
            >
                <!-- Hiển thị đồng hồ số to rõ nét -->
                <div class="text-center mb-2.5 bg-[var(--spa-brand-subtle)] py-2 rounded-[8px] border border-[var(--spa-brand-soft)]">
                    <span class="text-[10px] font-semibold text-[var(--spa-brand)] uppercase tracking-wider block mb-0.5">Giờ đã chọn</span>
                    <span class="text-[24px] font-extrabold text-[var(--spa-brand)] tracking-wider leading-none" x-text="value || '--:--'"></span>
                </div>

                <!-- THANH TRƯỢT KÉO THẢ (Interactive Drag Slider) -->
                <div class="mb-3 px-1">
                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium mb-1">
                        <span>07:00</span>
                        <span class="text-[var(--spa-brand)] font-semibold">◀ Kéo để chọn giờ ▶</span>
                        <span>22:00</span>
                    </div>
                    <input
                        type="range"
                        min="420"
                        max="1320"
                        step="15"
                        :value="sliderMins"
                        @input="onSlider($event.target.value)"
                        class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[var(--spa-brand)]"
                    />
                </div>

                <!-- CÁC NÚT CHỌN NHANH THEO CA (Quick Slots) -->
                <div class="border-t border-slate-100 pt-2 mb-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                        <span>Chọn nhanh theo ca:</span>
                        <button type="button" @click.stop="selectNow()" class="text-[var(--spa-brand)] hover:underline font-semibold cursor-pointer">Bây giờ</button>
                    </div>

                    <!-- Ca Sáng -->
                    <div class="mb-1.5">
                        <span class="text-[9px] font-semibold text-amber-600 block mb-0.5">☀️ Ca Sáng:</span>
                        <div class="grid grid-cols-4 gap-1">
                            <template x-for="slot in ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30']" :key="slot">
                                <button
                                    type="button"
                                    @click.stop="selectQuick(slot)"
                                    class="py-0.5 text-[10px] rounded-[4px] border transition cursor-pointer text-center"
                                    :class="value === slot ? 'bg-[var(--spa-brand)] text-white border-[var(--spa-brand)] font-bold shadow-xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    x-text="slot"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <!-- Ca Chiều & Tối -->
                    <div>
                        <span class="text-[9px] font-semibold text-teal-700 block mb-0.5">🌤️ Ca Chiều & Tối:</span>
                        <div class="grid grid-cols-4 gap-1">
                            <template x-for="slot in ['13:30', '14:00', '14:30', '15:00', '15:30', '16:30', '17:00', '18:30']" :key="slot">
                                <button
                                    type="button"
                                    @click.stop="selectQuick(slot)"
                                    class="py-0.5 text-[10px] rounded-[4px] border transition cursor-pointer text-center"
                                    :class="value === slot ? 'bg-[var(--spa-brand)] text-white border-[var(--spa-brand)] font-bold shadow-xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    x-text="slot"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Chân Popup -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px]">
                    <button
                        type="button"
                        @click.stop="clearValue()"
                        class="text-slate-400 hover:text-[var(--spa-danger)] font-normal transition cursor-pointer"
                    >
                        Xóa
                    </button>
                    <button
                        type="button"
                        @click.stop="open = false"
                        class="text-white bg-[var(--spa-brand)] hover:bg-[#0c5c56] px-3 py-1 rounded-[6px] font-semibold transition cursor-pointer text-[11px] shadow-xs"
                    >
                        Xác nhận
                    </button>
                </div>
            </div>
        </div>

    @elseif ($type === 'datetime-local')
        {{-- CUSTOM DATETIME PICKER: Kết hợp Lịch + Kéo thả / Chọn nhanh giờ --}}
        <div
            x-data="{
                open: false,
                value: '{{ $inputValue }}',
                datePart: '2026-09-09',
                timePart: '14:30',
                activeTab: 'date',
                sliderMins: 870,
                init() {
                    if (this.value && this.value.includes('T')) {
                        const [d, t] = this.value.split('T');
                        this.datePart = d;
                        this.timePart = t;
                        const parts = t.split(':').map(Number);
                        this.sliderMins = (parts[0] || 14) * 60 + (parts[1] || 0);
                    }
                },
                onSlider(val) {
                    this.sliderMins = parseInt(val, 10);
                    const h = String(Math.floor(this.sliderMins / 60)).padStart(2, '0');
                    const m = String(this.sliderMins % 60).padStart(2, '0');
                    this.timePart = `${h}:${m}`;
                    this.updateValue();
                },
                selectTime(t) {
                    this.timePart = t;
                    const parts = t.split(':').map(Number);
                    this.sliderMins = (parts[0] || 14) * 60 + (parts[1] || 0);
                    this.updateValue();
                },
                updateValue() {
                    this.value = `${this.datePart}T${this.timePart}`;
                    if ($refs.inputEl) {
                        $refs.inputEl.value = this.value;
                        $refs.inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                        $refs.inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }"
            x-init="$watch('open', isOpen => { if (isOpen) $dispatch('spa-field-popover-opened', { id: '{{ $inputId }}' }); })"
            @spa-field-popover-opened.window="if ($event.detail.id !== '{{ $inputId }}') open = false"
            @click.window.capture="if (!$el.contains($event.target)) open = false"
            class="relative flex items-center w-full"
        >
            <input
                x-ref="inputEl"
                id="{{ $inputId }}"
                type="text"
                readonly
                @if ($name) name="{{ $name }}" @endif
                :value="value"
                @if ($disabled) disabled aria-disabled="true" @else @click="open = !open" @endif
                @if ($required) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif
                @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
                placeholder="YYYY-MM-DDTHH:mm"
                {{ $attributes->except(['class', 'aria-describedby'])->merge([
                    'class' => 'spa-input w-full h-9 rounded-[8px] border text-[12px] text-[var(--spa-text)] bg-white transition-all focus:outline-none !pl-3 !pr-9 cursor-pointer font-medium ' .
                        ($hasError
                            ? '!border-[var(--spa-danger)] !bg-[#FEF2F2]/30 focus:!border-[var(--spa-danger)] focus:!ring-2 focus:!ring-[var(--spa-danger-soft)]'
                            : 'border-[var(--spa-border-strong)] hover:border-[var(--spa-text-muted)] focus:border-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand-soft)]') .
                        ($disabled ? ' opacity-60 cursor-not-allowed bg-[var(--spa-surface-muted)]' : '')
                ]) }}
            />

            <button
                type="button"
                @if (!$disabled) @click="open = !open" @endif
                class="absolute right-2.5 flex items-center text-[var(--spa-brand)] hover:text-[#0c5c56] transition cursor-pointer"
                aria-label="Chọn ngày và giờ"
            >
                <span class="material-symbols-outlined text-[18px]">event</span>
            </button>

            <!-- Datetime popover gọn gàng 275px -->
            <div
                x-show="open"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-120"
                x-transition:enter-start="opacity-0 translate-y-0.5 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                class="absolute left-0 top-full mt-1 z-50 bg-white border border-slate-200/90 rounded-[12px] shadow-xl shadow-slate-900/10 p-3 w-[275px]"
                style="display: none;"
            >
                <div class="flex gap-1 mb-2.5 pb-1.5 border-b border-slate-100">
                    <button
                        type="button"
                        @click="activeTab = 'date'"
                        class="flex-1 py-1 text-[11px] rounded-[5px] font-bold transition cursor-pointer"
                        :class="activeTab === 'date' ? 'bg-[var(--spa-brand)] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    >
                        1. Ngày (<span x-text="datePart"></span>)
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'time'"
                        class="flex-1 py-1 text-[11px] rounded-[5px] font-bold transition cursor-pointer"
                        :class="activeTab === 'time' ? 'bg-[var(--spa-brand)] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    >
                        2. Giờ (<span x-text="timePart"></span>)
                    </button>
                </div>

                <!-- Tab 1: Chọn ngày -->
                <div x-show="activeTab === 'date'" class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                        <span>Nhập ngày:</span>
                        <div class="flex gap-1">
                            <button type="button" @click="datePart = new Date().toISOString().split('T')[0]; updateValue();" class="text-[10px] text-[var(--spa-brand)] font-medium hover:underline cursor-pointer">Hôm nay</button>
                        </div>
                    </div>
                    <input
                        type="date"
                        x-model="datePart"
                        @change="updateValue()"
                        class="w-full border border-slate-200 rounded-[6px] px-2.5 py-1.5 text-xs text-[var(--spa-text)] focus:border-[var(--spa-brand)] focus:outline-none bg-slate-50"
                    />
                    <button
                        type="button"
                        @click="activeTab = 'time'"
                        class="w-full mt-2 py-1 bg-teal-50 text-[var(--spa-brand)] border border-[var(--spa-brand-soft)] text-xs font-semibold rounded-[6px] hover:bg-teal-100 transition cursor-pointer"
                    >
                        Tiếp tục: Chọn giờ ➔
                    </button>
                </div>

                <!-- Tab 2: Chọn giờ (Có Slider Kéo thả + Chọn nhanh) -->
                <div x-show="activeTab === 'time'" class="space-y-2">
                    <div class="text-center py-1 bg-[var(--spa-brand-subtle)] rounded-[6px] border border-[var(--spa-brand-soft)]">
                        <span class="text-[16px] font-extrabold text-[var(--spa-brand)]" x-text="timePart"></span>
                    </div>

                    <!-- Slider -->
                    <div>
                        <input
                            type="range"
                            min="420"
                            max="1320"
                            step="15"
                            :value="sliderMins"
                            @input="onSlider($event.target.value)"
                            class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[var(--spa-brand)]"
                        />
                    </div>

                    <!-- Quick slots -->
                    <div class="grid grid-cols-4 gap-1 pt-1">
                        <template x-for="slot in ['08:30','09:00','10:00','11:30','14:00','15:30','17:00','18:30']" :key="slot">
                            <button
                                type="button"
                                @click="selectTime(slot)"
                                class="py-0.5 text-[10px] rounded border transition text-center cursor-pointer"
                                :class="timePart === slot ? 'bg-[var(--spa-brand)] text-white border-[var(--spa-brand)] font-semibold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                x-text="slot"
                            ></button>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end mt-2.5 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-3 py-1 bg-[var(--spa-brand)] text-white text-xs rounded-[6px] font-medium hover:bg-[#0c5c56] transition cursor-pointer"
                    >
                        Xác nhận
                    </button>
                </div>
            </div>
        </div>

    @else
        {{-- STANDARD INPUT (Text, Email, Tel, Number, Password, Search, v.v.) --}}
        <div class="relative flex items-center w-full">
            @if ($prefix)
                <div class="absolute left-3 flex items-center pointer-events-none text-[var(--spa-text-muted)]" aria-hidden="true">
                    @if (is_string($prefix) && strlen($prefix) < 30 && !str_contains($prefix, '<'))
                        <span class="material-symbols-outlined text-[18px]">{{ $prefix }}</span>
                    @else
                        {{ $prefix }}
                    @endif
                </div>
            @endif

            <input
                id="{{ $inputId }}"
                type="{{ $type }}"
                @if ($name) name="{{ $name }}" @endif
                @if ($inputValue !== '') value="{{ $inputValue }}" @endif
                @if ($required) required aria-required="true" @endif
                @if ($disabled) disabled aria-disabled="true" @endif
                @if ($readonly) readonly @endif
                @if ($hasError) aria-invalid="true" @endif
                @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
                {{ $attributes->except(['class', 'aria-describedby'])->merge([
                    'class' => 'spa-input w-full h-9 rounded-[8px] border text-[12px] text-[var(--spa-text)] bg-white transition-all focus:outline-none ' .
                        ($prefix ? '!pl-9 ' : '!pl-3 ') .
                        ($suffix ? '!pr-9 ' : '!pr-3 ') .
                        ($hasError
                            ? '!border-[var(--spa-danger)] !bg-[#FEF2F2]/30 focus:!border-[var(--spa-danger)] focus:!ring-2 focus:!ring-[var(--spa-danger-soft)]'
                            : 'border-[var(--spa-border-strong)] hover:border-[var(--spa-text-muted)] focus:border-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand-soft)]') .
                        ($disabled ? ' opacity-60 cursor-not-allowed bg-[var(--spa-surface-muted)]' : '') .
                        ($readonly ? ' bg-[var(--spa-surface-muted)]' : '')
                ]) }}
            />

            @if ($suffix)
                <div class="absolute right-3 flex items-center pointer-events-none text-[var(--spa-text-muted)]" aria-hidden="true">
                    @if (is_string($suffix) && strlen($suffix) < 30 && !str_contains($suffix, '<'))
                        <span class="material-symbols-outlined text-[18px]">{{ $suffix }}</span>
                    @else
                        {{ $suffix }}
                    @endif
                </div>
            @endif
        </div>
    @endif

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
