@props([
    'active' => false,
    'type' => 'button',
    'disabled' => false,
])

<button
    type="{{ $type }}"
    @disabled($disabled)
    {{ $attributes->merge(['class' => 'spa-tab-item focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--spa-brand)] focus-visible:ring-offset-1' . ($active ? ' active' : '')]) }}
>
    {{ $slot }}
</button>
