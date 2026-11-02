@props([
    'title',
    'description' => null,
    'id' => null,
    'context' => null,
    'icon' => null,
])

@php
    $headingId = $id ?? 'page-heading-' . \Illuminate\Support\Str::slug($title);
    $routeContext = match (request()->route()?->getName()) {
        'dashboard' => ['label' => 'Nhịp vận hành', 'icon' => 'monitoring'],
        'appointments' => ['label' => 'Điều phối trong ngày', 'icon' => 'calendar_clock'],
        'rooms' => ['label' => 'Năng lực phục vụ', 'icon' => 'meeting_room'],
        'customers' => ['label' => 'Quan hệ khách hàng', 'icon' => 'patient_list'],
        'services' => ['label' => 'Danh mục kinh doanh', 'icon' => 'spa'],
        'invoices' => ['label' => 'Thu ngân & đối soát', 'icon' => 'point_of_sale'],
        'employees' => ['label' => 'Vận hành đội ngũ', 'icon' => 'badge'],
        'payroll' => ['label' => 'Tài chính nhân sự', 'icon' => 'payments'],
        default => ['label' => 'Sunny Beauty', 'icon' => 'spa'],
    };
    $contextLabel = $context ?? $routeContext['label'];
    $contextIcon = $icon ?? $routeContext['icon'];
@endphp

<div {{ $attributes->merge(['class' => 'spa-page-heading']) }}>
    <div class="spa-page-heading-copy">
        <div class="spa-page-context">
            <span class="material-symbols-outlined" aria-hidden="true">{{ $contextIcon }}</span>
            <span>{{ $contextLabel }}</span>
        </div>
        <h1 id="{{ $headingId }}">{{ $title }}</h1>

        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @if (isset($actions) && ! $actions->isEmpty())
        <div class="spa-actions">
            {{ $actions }}
        </div>
    @endif
</div>
