<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Quản trị '.config('app.name') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $routeName = request()->route()?->getName() ?? 'dashboard';
    $routeClass = str_replace('.', '-', $routeName);
    $navigationGroups = [
        'Trong ngày' => [
            ['route' => 'dashboard', 'label' => 'Nhịp vận hành', 'icon' => 'space_dashboard'],
            ['route' => 'daily-revenue', 'label' => 'Nhập ca làm cuối ngày', 'icon' => 'edit_note'],
            ['route' => 'appointments', 'label' => 'Lịch hẹn & tiếp đón', 'icon' => 'calendar_month'],
            ['route' => 'rooms', 'label' => 'Phòng & giường', 'icon' => 'meeting_room'],
        ],
        'Khách hàng & giao dịch' => [
            ['route' => 'customers', 'label' => 'Khách hàng & CRM', 'icon' => 'group'],
            ['route' => 'services', 'label' => 'Dịch vụ & liệu trình', 'icon' => 'spa'],
            ['route' => 'invoices', 'label' => 'Hóa đơn & doanh thu', 'icon' => 'receipt_long'],
        ],
        'Đội ngũ' => [
            ['route' => 'employees', 'label' => 'Nhân viên & chấm công', 'icon' => 'badge'],
            ['route' => 'payroll', 'label' => 'Bảng lương & hoa hồng', 'icon' => 'payments'],
        ],
    ];
@endphp
<body class="spa-app" data-page="{{ $routeClass }}">
<div class="spa-layout">
    <a class="spa-skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <aside class="spa-sidebar">
        <a href="{{ route('dashboard') }}" class="spa-brand" style="color:inherit;text-decoration:none">
            <span class="spa-brand-mark"><span class="material-symbols-outlined" aria-hidden="true">spa</span></span>
            <span><strong>{{ config('app.name') }}</strong><small>Bàn điều hành spa</small></span>
        </a>
        <nav
            class="spa-nav"
            aria-label="Điều hướng chính"
            x-data
            x-init="$nextTick(() => { const active = $el.querySelector('[aria-current=page]'); active?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'auto' }); })"
        >
            @foreach ($navigationGroups as $group => $links)
                <div class="spa-nav-group">
                    <span class="spa-nav-label">{{ $group }}</span>
                    @foreach ($links as $link)
                        @continue($link['route'] === 'rooms' && ! config('spa.resource_management_enabled'))
                        <a href="{{ route($link['route']) }}" aria-current="{{ request()->routeIs($link['route']) ? 'page' : 'false' }}">
                            <span class="material-symbols-outlined" aria-hidden="true">{{ $link['icon'] }}</span>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="spa-sidebar-footer">
            <span class="spa-sidebar-status"><span aria-hidden="true"></span>Đang vận hành</span>
            <small>{{ config('spa.address') }}</small>
        </div>
    </aside>
    <main id="main-content" class="spa-main" tabindex="-1">
        <header class="spa-header">
            <livewire:admin.global-search />
            <div class="spa-header-actions">
                <div class="spa-shift-context" aria-label="Ngày vận hành hiện tại">
                    <span class="material-symbols-outlined" aria-hidden="true">today</span>
                    <span><small>Ngày vận hành</small><strong>{{ now()->format('d/m/Y') }}</strong></span>
                </div>
                <livewire:admin.notification-bell />
                <div class="spa-user">
                    <span class="spa-avatar">{{ str(auth()->user()?->name ?? 'QT')->substr(0, 2) }}</span>
                    <div><strong>{{ auth()->user()?->name ?? 'Quản trị viên' }}</strong><small>Quản trị hệ thống</small></div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.icon-button type="submit" icon="logout" label="Đăng xuất" />
                </form>
            </div>
        </header>
        <section class="spa-content spa-content--{{ $routeClass }}">
            @if (session('success'))
                <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
            @endif
            @if (session('error'))
                <x-ui.alert variant="danger">{{ session('error') }}</x-ui.alert>
            @endif
            {{ $slot }}
        </section>
    </main>
</div>
@livewireScripts
</body>
</html>
