@props([
    'variant' => 'neutral',       // primary | success | warning | danger | info | neutral
    'shape' => 'pill',            // pill (trạng thái) | tag (danh mục) | badge (số đếm unread)
    'size' => 'md',               // sm | md
    'dot' => false,               // bool: hiển thị dấu chấm tròn thay vì icon
    'icon' => null,               // string|bool|null: tên icon Material Symbols hoặc true/null để tự nhận
    'dismissible' => false,       // bool: bật chip xóa được
    'dismissLabel' => 'Xóa nhãn', // string: aria-label bắt buộc cho nút xóa icon-only
])

@php
// Tự động chọn biểu tượng tương ứng với ngữ nghĩa trạng thái (như ✓ Active)
$defaultVariantIcon = match ($variant) {
    'success' => 'check',
    'warning' => 'schedule',
    'danger' => 'cancel',
    'info' => 'check',
    default => 'help',
};

$resolvedIcon = match (true) {
    is_string($icon) && !empty($icon) => $icon,
    $icon === true => $defaultVariantIcon,
    $icon === false => null,
    $dot === true => null, // Ưu tiên dot nếu truyền rõ ràng dot=true
    $shape === 'pill' => $defaultVariantIcon, // Mặc định pill dùng biểu tượng trực quan thay cho dấu chấm
    default => null,
};

$hasDot = ($dot === true) && empty($resolvedIcon);

$variantClasses = match ($variant) {
    'primary' => 'spa-badge-primary',
    'success' => 'spa-badge-success',
    'warning' => 'spa-badge-warning',
    'danger' => 'spa-badge-danger',
    'info' => 'spa-badge-info',
    default => 'spa-badge-neutral',
};

$shapeClasses = match ($shape) {
    'tag' => 'rounded-[6px] !gap-1 font-medium tracking-tight',
    'badge' => 'rounded-full min-w-[20px] h-5 !px-1.5 !py-0 text-[10px] font-bold justify-center',
    default => 'rounded-full font-semibold', // pill
};

$sizeClasses = match ($size) {
    'sm' => ($shape === 'badge' ? '!min-w-[16px] !h-4 !px-1 !text-[9px]' : '!px-2 !py-0.5 !text-[10px] !gap-1'),
    default => ($shape === 'badge' ? '' : ($shape === 'tag' ? '!px-2.5 !py-0.5 !text-[11px]' : '!px-2.5 !py-1 !text-[11px] !gap-1.5')),
};

$iconSize = match ($size) {
    'sm' => '!text-[12px]',
    default => '!text-[14px]',
};

$dotClasses = $hasDot ? '' : 'before:!hidden';

$baseClasses = "spa-badge {$variantClasses} {$shapeClasses} {$sizeClasses} {$dotClasses} inline-flex items-center border whitespace-nowrap select-none transition-all";
@endphp

@if ($dismissible)
    <span
        x-data="{ visible: true }"
        x-show="visible"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90"
        {{ $attributes->merge(['class' => trim("{$baseClasses} !pr-1.5")]) }}
    >
        @if ($resolvedIcon)
            <span class="material-symbols-outlined {{ $iconSize }} leading-none -ml-0.5" aria-hidden="true">{{ $resolvedIcon }}</span>
        @endif
        <span>{{ $slot }}</span>
        <button
            type="button"
            @click.stop="visible = false; $dispatch('dismiss')"
            class="inline-flex items-center justify-center w-3.5 h-3.5 ml-1 rounded-full text-current opacity-60 hover:opacity-100 hover:bg-black/10 transition-all focus:outline-none focus-visible:ring-1 focus-visible:ring-current cursor-pointer"
            aria-label="{{ $dismissLabel }}"
        >
            <span class="material-symbols-outlined !text-[12px] leading-none" aria-hidden="true">close</span>
        </button>
    </span>
@else
    <span {{ $attributes->merge(['class' => trim($baseClasses)]) }}>
        @if ($resolvedIcon)
            <span class="material-symbols-outlined {{ $iconSize }} leading-none -ml-0.5" aria-hidden="true">{{ $resolvedIcon }}</span>
        @endif
        <span>{{ $slot }}</span>
    </span>
@endif
