@props([
    'name' => null,
    'title' => null,
    'maxWidth' => 'md',
    'show' => false,
])

@php
$modalId = $name ?? 'modal-' . \Illuminate\Support\Str::random(8);
$maxWidthClass = match ($maxWidth) {
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    default => 'max-w-md',
};
@endphp

<div
    x-data="{
        open: @js($show),
        close() {
            this.open = false;
            $dispatch('modal-closed', '{{ $modalId }}');
        }
    }"
    x-init="
        $watch('open', value => {
            if (value) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        });
    "
    @open-modal.window="
        if ($event.detail === '{{ $modalId }}' || $event.detail?.name === '{{ $modalId }}') {
            open = true;
        }
    "
    @close-modal.window="
        if ($event.detail === '{{ $modalId }}' || $event.detail?.name === '{{ $modalId }}' || !$event.detail) {
            close();
        }
    "
    @keydown.escape.window="if (open) close()"
    x-show="open"
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $modalId }}-title"
>
    <!-- Lớp nền làm mờ (Backdrop) -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-[var(--spa-text)]/60 backdrop-blur-sm transition-opacity"
        @click="close()"
        aria-hidden="true"
    ></div>

    <!-- Hộp thoại Modal chính -->
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="spa-modal-panel relative transform overflow-hidden rounded-[12px] bg-white text-left shadow-2xl border border-[var(--spa-border)] transition-all w-full {{ $maxWidthClass }} my-8"
            @click.stop
        >
            <!-- Header Modal -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--spa-border)] bg-white">
                <div class="min-w-0 flex-1 pr-3">
                    @if (isset($titleSlot))
                        {{ $titleSlot }}
                    @elseif ($title)
                        <h3 id="{{ $modalId }}-title" class="text-[15px] font-bold text-[var(--spa-text)] m-0 leading-tight">
                            {{ $title }}
                        </h3>
                    @endif
                </div>

                <button
                    type="button"
                    @click="close()"
                    class="spa-icon-button -mr-2 text-[var(--spa-text-muted)] hover:text-[var(--spa-text)] hover:bg-[var(--spa-surface-muted)] transition-colors rounded-full p-1.5 inline-flex items-center justify-center"
                    aria-label="Đóng"
                >
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                </button>
            </div>

            <!-- Body Modal -->
            <div class="spa-modal-body px-6 py-5 text-[13px] text-[var(--spa-text-secondary)] leading-relaxed">
                {{ $slot }}
            </div>

            <!-- Footer / Actions Modal -->
            @if (isset($actions))
                <div class="spa-modal-actions px-6 py-4 bg-[var(--spa-surface-muted)] border-t border-[var(--spa-border)] flex items-center justify-end gap-2.5">
                    {{ $actions }}
                </div>
            @endif
        </div>
    </div>
</div>
