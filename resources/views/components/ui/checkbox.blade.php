@props([
    'label' => null,
    'description' => null,
    'id' => null,
    'name' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'error' => null,
])

@php
$modelName = $attributes->whereStartsWith('wire:model')->first();
$effectiveName = $name ?? $modelName;
$checkboxId = $id ?? ($effectiveName ? 'checkbox-' . str_replace(['[', ']', '.', '$'], '-', $effectiveName) . ($value !== '1' ? '-' . \Illuminate\Support\Str::slug($value) : '') : 'spa-checkbox-' . \Illuminate\Support\Str::random(8));
$hasError = !empty($error) || ($effectiveName && isset($errors) && $errors->has($effectiveName));
$errorMessage = $error ?? ($effectiveName && isset($errors) ? $errors->first($effectiveName) : null);
$descId = $description ? "{$checkboxId}-desc" : null;
$errorId = $hasError ? "{$checkboxId}-error" : null;

$customDescribedBy = $attributes->get('aria-describedby');
$describedBy = trim(($descId ?? '') . ' ' . ($errorId ?? '') . ' ' . ($customDescribedBy ?? ''));

$isChecked = (bool) ($checked || ($effectiveName && old($effectiveName) == $value));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'spa-field flex flex-col gap-1']) }}>
    <div class="flex items-start gap-2.5">
        <input
            type="checkbox"
            id="{{ $checkboxId }}"
            @if ($name) name="{{ $name }}" @endif
            value="{{ $value }}"
            @checked($isChecked)
            @if ($disabled) disabled aria-disabled="true" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['class', 'aria-describedby'])->merge([
                'class' => 'mt-0.5 h-4 w-4 rounded border-[var(--spa-border-strong)] text-[var(--spa-brand)] accent-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand)] focus:ring-offset-1 focus:outline-none transition cursor-pointer ' .
                    ($hasError ? '!border-[var(--spa-danger)] focus:!ring-[var(--spa-danger)]' : 'border-[var(--spa-border-strong)]') .
                    ($disabled ? ' opacity-60 cursor-not-allowed' : '')
            ]) }}
        />

        @if ($label || $description || $slot->isNotEmpty())
            <label for="{{ $checkboxId }}" class="text-[13px] text-[var(--spa-text)] select-none {{ $disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}">
                @if ($label)
                    <span class="font-medium block leading-snug">{{ $label }}</span>
                @endif
                {{ $slot }}
                @if ($description)
                    <span id="{{ $descId }}" class="text-[11px] text-[var(--spa-text-muted)] block mt-0.5 leading-normal">{{ $description }}</span>
                @endif
            </label>
        @endif
    </div>

    @if ($hasError)
        <div id="{{ $errorId }}" class="spa-error flex items-center gap-1.5 text-[11px] font-medium text-[var(--spa-danger)] pl-6.5" role="alert">
            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif
</div>
