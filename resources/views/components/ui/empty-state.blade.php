@props([
    'title' => 'Chưa có dữ liệu',
    'description' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'spa-empty flex flex-col items-center justify-center text-center py-10 px-4 text-[var(--spa-text-muted)] select-none']) }}>
    @if ($icon)
        <span class="material-symbols-outlined !text-[36px] text-[var(--spa-text-muted)] mb-2.5" aria-hidden="true">{{ $icon }}</span>
    @endif

    <h4 class="text-[14px] font-bold text-[var(--spa-text-secondary)] m-0">{{ $title }}</h4>

    @if ($description)
        <p class="text-[12px] text-[var(--spa-text-muted)] mt-1 mb-0 max-w-sm leading-relaxed">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="text-[12px] text-[var(--spa-text-muted)] mt-1">{{ $slot }}</div>
    @endif

    @if (isset($action))
        <div class="mt-4 flex items-center justify-center gap-2">
            {{ $action }}
        </div>
    @endif
</div>
