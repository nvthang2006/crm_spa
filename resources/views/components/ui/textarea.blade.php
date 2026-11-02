@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'rows' => 3,
    'placeholder' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'maxlength' => null,
    'value' => null,
])

@php
$modelName = $attributes->whereStartsWith('wire:model')->first();
$effectiveName = $name ?? $modelName;
$textareaId = $id ?? ($effectiveName ? 'textarea-' . str_replace(['[', ']', '.', '$'], '-', $effectiveName) : 'spa-textarea-' . \Illuminate\Support\Str::random(8));
$hasError = !empty($error) || ($effectiveName && isset($errors) && $errors->has($effectiveName));
$errorMessage = $error ?? ($effectiveName && isset($errors) ? $errors->first($effectiveName) : null);
$hintId = $hint ? "{$textareaId}-hint" : null;
$errorId = $hasError ? "{$textareaId}-error" : null;

$customDescribedBy = $attributes->get('aria-describedby');
$describedBy = trim(($hintId ?? '') . ' ' . ($errorId ?? '') . ' ' . ($customDescribedBy ?? ''));

$textValue = $value ?? ($effectiveName ? old($effectiveName) : null);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'spa-field flex flex-col gap-1.5']) }}>
    @if ($label)
        <label for="{{ $textareaId }}" class="text-[12px] font-semibold text-[var(--spa-text-secondary)] flex items-center gap-1">
            <span>{{ $label }}</span>
            @if ($required)
                <span class="text-[var(--spa-danger)] font-bold" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $textareaId }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($disabled) disabled aria-disabled="true" @endif
        @if ($readonly) readonly @endif
        @if ($hasError) aria-invalid="true" @endif
        @if (!empty($describedBy)) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['class', 'aria-describedby'])->merge([
            'class' => 'spa-textarea w-full rounded-[8px] border text-[13px] text-[var(--spa-text)] bg-white px-3.5 py-2 transition-all focus:outline-none ' .
                ($hasError
                    ? '!border-[var(--spa-danger)] !bg-[#FEF2F2]/30 focus:!border-[var(--spa-danger)] focus:!ring-2 focus:!ring-[var(--spa-danger-soft)]'
                    : 'border-[var(--spa-border-strong)] hover:border-[var(--spa-text-muted)] focus:border-[var(--spa-brand)] focus:ring-2 focus:ring-[var(--spa-brand-soft)]') .
                ($disabled ? ' opacity-60 cursor-not-allowed bg-[var(--spa-surface-muted)]' : '') .
                ($readonly ? ' bg-[var(--spa-surface-muted)]' : '')
        ]) }}
    >{{ $textValue ?? $slot }}</textarea>

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
