@props([
    'variant' => 'primary', // primary | secondary | danger | ghost
    'size' => 'md',        // sm | md | lg
    'type' => 'button',    // button | submit | reset
    'href' => null,        // render as <a> if present
    'icon' => null,        // Material Symbols Outlined icon name
    'iconPosition' => 'left', // left | right
    'loading' => null,     // string (wire:target action name) or boolean
    'disabled' => false,
])

@php
$baseClasses = 'spa-button inline-flex items-center justify-center font-semibold select-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none';

$variantClasses = match ($variant) {
    'primary' => 'spa-button-primary',
    'secondary' => 'spa-button-secondary',
    'danger' => 'spa-button-danger',
    'ghost' => 'spa-button-ghost',
    default => 'spa-button-primary',
};

$sizeClasses = match ($size) {
    'sm' => '!px-2.5 !py-1.5 !text-[11px] !gap-1.5 rounded-[6px]',
    'lg' => '!px-4 !py-2.5 !text-[14px] !gap-2.5 rounded-[12px]',
    default => '!px-3.5 !py-2 !text-[12px] !gap-2 rounded-[8px]',
};

$iconSize = match ($size) {
    'sm' => 'text-[15px]',
    'lg' => 'text-[20px]',
    default => 'text-[18px]',
};

$allClasses = trim("{$baseClasses} {$variantClasses} {$sizeClasses}");
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $allClasses]) }}
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
    >
        @if ($icon && $iconPosition === 'left')
            <span class="material-symbols-outlined {{ $iconSize }}" aria-hidden="true">{{ $icon }}</span>
        @endif

        <span>{{ $slot }}</span>

        @if ($icon && $iconPosition === 'right')
            <span class="material-symbols-outlined {{ $iconSize }}" aria-hidden="true">{{ $icon }}</span>
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $disabled ? 'disabled' : '' }}
        @if ($loading) wire:loading.attr="disabled" @endif
        {{ $attributes->merge(['class' => $allClasses]) }}
    >
        @if ($loading)
            <span
                wire:loading
                @if (is_string($loading) && $loading !== '1') wire:target="{{ $loading }}" @endif
                class="inline-flex items-center"
                aria-hidden="true"
            >
                <span class="material-symbols-outlined {{ $iconSize }} animate-spin">progress_activity</span>
            </span>
        @endif

        @if ($icon && $iconPosition === 'left')
            <span
                @if ($loading)
                    wire:loading.remove
                    @if (is_string($loading) && $loading !== '1') wire:target="{{ $loading }}" @endif
                @endif
                class="material-symbols-outlined {{ $iconSize }}"
                aria-hidden="true"
            >{{ $icon }}</span>
        @endif

        <span>{{ $slot }}</span>

        @if ($icon && $iconPosition === 'right')
            <span
                @if ($loading)
                    wire:loading.remove
                    @if (is_string($loading) && $loading !== '1') wire:target="{{ $loading }}" @endif
                @endif
                class="material-symbols-outlined {{ $iconSize }}"
                aria-hidden="true"
            >{{ $icon }}</span>
        @endif
    </button>
@endif
