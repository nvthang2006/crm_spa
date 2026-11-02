@props([
    'icon',
    'label',
    'type' => 'button',
    'variant' => 'default',
    'disabled' => false,
])

@php
    $variantClasses = match ($variant) {
        'danger' => 'text-[var(--spa-danger)] hover:bg-[#FEF2F2] hover:text-[var(--spa-danger)]',
        default => 'text-[var(--spa-text-muted)] hover:bg-[var(--spa-surface-muted)] hover:text-[var(--spa-text)]',
    };
@endphp

<button
    type="{{ $type }}"
    @disabled($disabled)
    aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => "spa-icon-button focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-50 {$variantClasses}"]) }}
>
    <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
</button>
