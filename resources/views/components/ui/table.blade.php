@props([
    'striped' => false,
    'mobileCards' => false,
])

<div class="spa-table-wrap w-full overflow-x-auto rounded-[10px] border border-[var(--spa-border)] bg-white">
    <table {{ $attributes->merge(['class' => 'spa-table w-full border-collapse text-left text-[12px] ' . ($striped ? 'spa-table-striped' : '') . ($mobileCards ? ' spa-table-mobile-cards' : '')]) }}>
        @if (isset($header))
            <thead class="bg-[var(--spa-surface-muted)] border-b border-[var(--spa-border)] text-[var(--spa-text-muted)] font-semibold text-[10px] uppercase tracking-wider">
                @if (str_contains($header, '<tr'))
                    {!! $header !!}
                @else
                    <tr>
                        {{ $header }}
                    </tr>
                @endif
            </thead>
        @endif

        <tbody class="divide-y divide-[var(--spa-surface-muted)] text-[var(--spa-text)]">
            {{ $slot }}

            @if (isset($empty) && trim($empty) !== '')
                <tr class="spa-table-empty">
                    <td colspan="100%" class="!p-0 !border-0">
                        {{ $empty }}
                    </td>
                </tr>
            @endif
        </tbody>

        @if (isset($footer))
            <tfoot class="border-t border-[var(--spa-border)] bg-[var(--spa-surface-muted)]">
                {{ $footer }}
            </tfoot>
        @endif
    </table>
</div>
