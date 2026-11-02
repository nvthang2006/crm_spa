@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Điều hướng phân trang" class="spa-pagination-controls">
            {{-- Nút Trang trước --}}
            @if ($paginator->onFirstPage())
                <span class="spa-pagination-btn spa-pagination-btn-disabled" aria-disabled="true" aria-label="Trang trước">
                    <span class="material-symbols-outlined">chevron_left</span>
                </span>
            @else
                <button type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="spa-pagination-btn"
                        aria-label="Trang trước">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            @endif

            {{-- Nút Trang sau --}}
            @if ($paginator->hasMorePages())
                <button type="button"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="spa-pagination-btn"
                        aria-label="Trang sau">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            @else
                <span class="spa-pagination-btn spa-pagination-btn-disabled" aria-disabled="true" aria-label="Trang sau">
                    <span class="material-symbols-outlined">chevron_right</span>
                </span>
            @endif
        </nav>
    @endif
</div>
