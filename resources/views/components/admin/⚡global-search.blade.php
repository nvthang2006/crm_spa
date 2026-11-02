<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use Livewire\Component;

new class extends Component
{
    public string $query = '';

    public function clear(): void
    {
        $this->query = '';
    }

    /**
     * @return array<int, array{type: string, title: string, meta: string, url: string}>
     */
    public function getResultsProperty(): array
    {
        $term = trim($this->query);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        $results = [];

        Customer::query()
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like))
            ->orderBy('name')->limit(5)->get()
            ->each(function (Customer $customer) use (&$results): void {
                $results[] = ['type' => 'Khách hàng', 'title' => $customer->name, 'meta' => $customer->code.' · '.$customer->phone, 'url' => route('customers', ['search' => $customer->name])];
            });

        Appointment::query()
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like)->orWhere('phone', 'like', $like)))
            ->with('customer')->latest('appointment_date')->limit(5)->get()
            ->each(function (Appointment $appointment) use (&$results): void {
                $results[] = ['type' => 'Lịch hẹn', 'title' => $appointment->code, 'meta' => $appointment->customer->name.' · '.$appointment->appointment_date->format('d/m/Y'), 'url' => route('appointments', ['date' => $appointment->appointment_date->toDateString(), 'search' => $appointment->code])];
            });

        Invoice::query()
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like)->orWhere('phone', 'like', $like)))
            ->with('customer')->latest('issued_at')->limit(5)->get()
            ->each(function (Invoice $invoice) use (&$results): void {
                $results[] = ['type' => 'Hóa đơn', 'title' => $invoice->code, 'meta' => $invoice->customer->name.' · '.number_format($invoice->subtotal - $invoice->discount_amount, 0, ',', '.').' ₫', 'url' => route('invoices', ['search' => $invoice->code])];
            });

        return array_slice($results, 0, 12);
    }
};
?>

<div class="spa-search" wire:keydown.escape="clear">
    <span class="material-symbols-outlined" aria-hidden="true">search</span>
    <x-ui.input
        class="spa-search-field"
        type="search"
        wire:model.live.debounce.300ms="query"
        placeholder="Tìm khách hàng, mã lịch hẹn, phiếu thu…"
        aria-label="Tìm kiếm nhanh"
        autocomplete="off"
    />
    @if(mb_strlen(trim($query)) >= 2)
        <div class="spa-search-results" role="listbox" style="position:absolute;z-index:20;top:calc(100% + 8px);left:0;right:0;background:#fff;border:1px solid var(--spa-border);border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.14);padding:6px">
            @forelse($this->results as $result)
                <a href="{{ $result['url'] }}" class="spa-search-result" role="option" style="display:block;padding:9px 10px;border-radius:7px;text-decoration:none;color:inherit" wire:navigate>
                    <strong style="display:block;font-size:13px">{{ $result['title'] }}</strong><span class="spa-muted spa-small">{{ $result['type'] }} · {{ $result['meta'] }}</span>
                </a>
            @empty
                <div class="spa-muted spa-small" style="padding:10px">Không tìm thấy dữ liệu phù hợp.</div>
            @endforelse
        </div>
    @endif
</div>
