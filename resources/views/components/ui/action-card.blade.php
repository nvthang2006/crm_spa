@props([
    'type' => 'button',
    'active' => false,
    'disabled' => false,
])

<button
    type="{{ $type }}"
    @disabled($disabled)
    aria-pressed="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->merge(['class' => 'spa-card spa-stat spa-stat-clickable focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-2']) }}
>
    {{ $slot }}
</button>
