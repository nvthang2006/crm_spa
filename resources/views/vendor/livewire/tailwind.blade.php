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

            {{-- Các phần tử số trang --}}
            @foreach ($elements as $element)
                {{-- Dấu ba chấm phân tách --}}
                @if (is_string($element))
                    <span class="spa-pagination-ellipsis" aria-disabled="true">
                        <span>{{ $element }}</span>
                    </span>
                @endif

                {{-- Mảng các liên kết số trang --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                            @if ($page == $paginator->currentPage())
                                <span class="spa-pagination-btn spa-pagination-btn-active" aria-current="page">
                                    {{ $page }}
                                </span>
                            @else
                                <button type="button"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                        class="spa-pagination-btn"
                                        aria-label="Đi tới trang {{ $page }}">
                                    {{ $page }}
                                </button>
                            @endif
                        </span>
                    @endforeach
                @endif
            @endforeach

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
