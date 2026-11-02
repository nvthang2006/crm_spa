<?php

use App\Services\OperationalAlertService;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markRead(int $id): void
    {
        app(OperationalAlertService::class)->markRead(auth()->user(), $id);
    }

    public function markAllRead(): void
    {
        app(OperationalAlertService::class)->markAllRead(auth()->user());
        session()->flash('success', 'Đã đánh dấu toàn bộ thông báo là đã đọc.');
    }
};
?>

<div style="position:relative" wire:poll.60s>
    @php($alerts = app(OperationalAlertService::class)->forUser(auth()->user()))
    @php($unreadCount = $alerts->filter(fn ($alert): bool => $alert->reads->isEmpty())->count())
    <x-ui.icon-button icon="notifications" label="Thông báo" aria-expanded="{{ $open ? 'true' : 'false' }}" wire:click="toggle" />
    @if ($unreadCount > 0)
        <x-ui.badge shape="badge" variant="danger" size="sm" class="spa-notification-count" aria-label="{{ $unreadCount }} thông báo chưa đọc">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</x-ui.badge>
    @endif
    @if($open)
        <div style="position:absolute;z-index:30;top:calc(100% + 10px);right:0;width:min(390px,calc(100vw - 32px));background:#fff;border:1px solid var(--spa-border);border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.14);overflow:hidden" role="dialog" aria-modal="true" aria-labelledby="notifications-title">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--spa-border)"><strong id="notifications-title">Thông báo vận hành</strong><x-ui.button size="sm" variant="secondary" wire:click="markAllRead">Đọc tất cả</x-ui.button></div>
            <div style="max-height:390px;overflow:auto">
                @forelse($alerts as $alert)
                    @php($isUnread = $alert->reads->isEmpty())
                    <div style="padding:12px 16px;border-bottom:1px solid var(--spa-surface-muted);background:{{ $isUnread ? 'var(--spa-surface-muted)' : '#fff' }}">
                        <div style="display:flex;gap:8px;justify-content:space-between"><strong style="font-size:13px">{{ $alert->title }}</strong>@if($isUnread)<x-ui.badge variant="warning">Mới</x-ui.badge>@endif</div>
                        <p class="spa-muted spa-small" style="margin:5px 0 9px">{{ $alert->message }}</p>
                        <div class="spa-actions">
                            @if($alert->action_url)<x-ui.button size="sm" variant="secondary" href="{{ $alert->action_url }}" wire:navigate>Xem xử lý</x-ui.button>@endif
                            @if($isUnread)<x-ui.button size="sm" variant="secondary" wire:click="markRead({{ $alert->id }})">Đã đọc</x-ui.button>@endif
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state title="Không có cảnh báo cần xử lý" icon="notifications_none" class="py-6 px-4" />
                @endforelse
            </div>
        </div>
    @endif
</div>
