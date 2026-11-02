@props([
    'variant' => 'info',          // success | warning | danger | info
    'title' => null,
    'icon' => null,
    'dismissible' => false,
    'toast' => false,             // bool: bật chế độ Toast (Snackbar)
    'duration' => 4000,           // thời gian tự tắt (ms)
    'autoDismiss' => null,        // bool|null: mặc định true cho success/info, false cho danger/warning
])

@php
$isToast = (bool) $toast;
$isDismissible = $dismissible || $isToast;

// Ngữ nghĩa Accessibility:
// - Thông điệp không khẩn cấp (success, info): role="status" và aria-live="polite"
// - Thông điệp quan trọng/khẩn cấp (danger, warning): role="alert" và aria-live="assertive"
$role = in_array($variant, ['danger', 'warning']) ? 'alert' : 'status';
$ariaLive = $isToast ? (in_array($variant, ['danger', 'warning']) ? 'assertive' : 'polite') : null;

// Chỉ tự động đóng với nonessential messages (success, info)
$shouldAutoDismiss = $isToast && ($autoDismiss ?? in_array($variant, ['success', 'info']));

$variantConfig = match ($variant) {
    'success' => [
        'classes' => 'spa-alert-success',
        'defaultIcon' => 'check_circle',
        'iconColor' => 'text-current',
    ],
    'warning' => [
        'classes' => 'spa-alert-warning',
        'defaultIcon' => 'warning',
        'iconColor' => 'text-current',
    ],
    'danger' => [
        'classes' => 'spa-alert-error',
        'defaultIcon' => 'error',
        'iconColor' => 'text-current',
    ],
    default => [
        'classes' => 'spa-alert-info',
        'defaultIcon' => 'info',
        'iconColor' => 'text-current',
    ],
};

$selectedIcon = $icon ?? $variantConfig['defaultIcon'];

$toastContainerClasses = $isToast
    ? 'pointer-events-auto shadow-xl !rounded-[12px] max-w-sm w-full backdrop-blur-md'
    : 'rounded-[9px]';
@endphp

@if ($isToast)
    <div
        role="{{ $role }}"
        aria-live="{{ $ariaLive }}"
        x-data="{
            open: true,
            autoDismiss: @js($shouldAutoDismiss),
            duration: @js($duration),
            remaining: @js($duration),
            timer: null,
            startedAt: null,
            startTimer() {
                if (!this.autoDismiss || this.remaining <= 0) return;
                this.startedAt = Date.now();
                this.timer = setTimeout(() => {
                    this.open = false;
                    $dispatch('toast-closed');
                }, this.remaining);
            },
            pause() {
                if (!this.autoDismiss || !this.timer) return;
                clearTimeout(this.timer);
                this.timer = null;
                const elapsed = Date.now() - this.startedAt;
                this.remaining = Math.max(0, this.remaining - elapsed);
            },
            resume() {
                if (!this.autoDismiss || this.timer || this.remaining <= 0) return;
                this.startTimer();
            }
        }"
        x-init="if (autoDismiss) startTimer()"
        @mouseenter="pause()"
        @mouseleave="resume()"
        @focusin="pause()"
        @focusout="resume()"
        x-show="open"
        x-transition:enter="transform ease-out duration-300 transition"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        {{ $attributes->merge([
            'class' => "spa-alert flex items-start gap-3 p-3.5 border text-[12px] leading-relaxed transition-all {$toastContainerClasses} {$variantConfig['classes']}"
        ]) }}
    >
        @if ($selectedIcon)
            <span class="material-symbols-outlined text-[20px] shrink-0 mt-0.5 {{ $variantConfig['iconColor'] }}" aria-hidden="true">
                {{ $selectedIcon }}
            </span>
        @endif

        <div class="flex-1 min-w-0">
            @if ($title)
                <h5 class="text-[13px] font-bold m-0 mb-1 leading-snug">{{ $title }}</h5>
            @endif

            <div class="text-[12px]">
                {{ $slot }}
            </div>

            @if (isset($action))
                <div class="mt-2.5 flex items-center gap-2">
                    {{ $action }}
                </div>
            @endif
        </div>

        @if ($isDismissible)
            <button
                type="button"
                @click="open = false; if (timer) clearTimeout(timer);"
                class="shrink-0 p-1 -mr-1 -mt-1 rounded-md text-current opacity-70 hover:opacity-100 hover:bg-black/5 transition-opacity focus:outline-none focus:ring-2 focus:ring-current cursor-pointer"
                aria-label="Đóng thông báo"
            >
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>
            </button>
        @endif
    </div>
@else
    <div
        role="{{ $role }}"
        @if ($dismissible)
            x-data="{ open: true }"
            x-show="open"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        @endif
        {{ $attributes->merge([
            'class' => "spa-alert flex items-start gap-3 p-3.5 border text-[12px] leading-relaxed transition-all {$toastContainerClasses} {$variantConfig['classes']}"
        ]) }}
    >
        @if ($selectedIcon)
            <span class="material-symbols-outlined text-[20px] shrink-0 mt-0.5 {{ $variantConfig['iconColor'] }}" aria-hidden="true">
                {{ $selectedIcon }}
            </span>
        @endif

        <div class="flex-1 min-w-0">
            @if ($title)
                <h5 class="text-[13px] font-bold m-0 mb-1 leading-snug">{{ $title }}</h5>
            @endif

            <div class="text-[12px]">
                {{ $slot }}
            </div>

            @if (isset($action))
                <div class="mt-2.5 flex items-center gap-2">
                    {{ $action }}
                </div>
            @endif
        </div>

        @if ($dismissible)
            <button
                type="button"
                @click="open = false"
                class="shrink-0 p-1 -mr-1 -mt-1 rounded-md text-current opacity-70 hover:opacity-100 hover:bg-black/5 transition-opacity focus:outline-none focus:ring-2 focus:ring-current cursor-pointer"
                aria-label="Đóng thông báo"
            >
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>
            </button>
        @endif
    </div>
@endif
