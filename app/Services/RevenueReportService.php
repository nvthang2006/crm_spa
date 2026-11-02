<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\Employee;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class RevenueReportService
{
    /** @return array{summary: array{customers: int, revenue: int, commission: int}, staff: array<int, array{name: string, customers: int, revenue: int, commission: int}>, days: array<string, array{customers: int, revenue: int, commission: int}>} */
    public function summarize(string $startsOn, string $endsOn): array
    {
        $invoices = Invoice::query()->where('status', 'paid')->whereBetween('paid_at', [$startsOn.' 00:00:00', $endsOn.' 23:59:59.999999'])->with('items')->get();
        $commissions = CommissionEntry::query()->whereIn('status', ['pending', 'locked'])
            ->whereBetween('occurred_at', [$startsOn.' 00:00:00', $endsOn.' 23:59:59.999999'])->get();
        $staff = [];
        $days = [];
        foreach ($invoices as $invoice) {
            $day = $invoice->paid_at->toDateString();
            $days[$day] ??= ['customers' => [], 'revenue' => 0, 'commission' => 0];
            $days[$day]['customers'][] = $invoice->customer_id;
            $days[$day]['revenue'] += $invoice->total_received;
            foreach ($this->allocateRevenue($invoice) as $itemId => $amount) {
                $id = $invoice->items->firstWhere('id', $itemId)->employee_id ?? 0;
                $staff[$id] ??= ['customers' => [], 'revenue' => 0, 'commission' => 0];
                $staff[$id]['customers'][] = $invoice->customer_id;
                $staff[$id]['revenue'] += $amount;
            }
        }
        foreach ($commissions as $commission) {
            $id = $commission->employee_id;
            $staff[$id] ??= ['customers' => [], 'revenue' => 0, 'commission' => 0];
            $staff[$id]['commission'] += $commission->amount;
            $day = $commission->occurred_at->toDateString();
            $days[$day] ??= ['customers' => [], 'revenue' => 0, 'commission' => 0];
            $days[$day]['commission'] += $commission->amount;
        }
        $names = Employee::withTrashed()->whereIn('id', array_keys($staff))->pluck('name', 'id');
        foreach ($staff as $id => &$row) {
            $row['name'] = $id === 0 ? 'Chưa gán nhân viên' : $names->get($id, 'Nhân viên đã lưu trữ');
            $row['customers'] = count(array_unique($row['customers']));
        }
        unset($row);
        foreach ($days as &$row) {
            $row['customers'] = count(array_unique($row['customers']));
        }
        unset($row);
        ksort($days);
        uasort($staff, fn (array $a, array $b): int => $b['commission'] <=> $a['commission'] ?: strcmp($a['name'], $b['name']));

        return [
            'summary' => ['customers' => $invoices->pluck('customer_id')->unique()->count(), 'revenue' => (int) $invoices->sum('total_received'), 'commission' => (int) $commissions->sum('amount')],
            'staff' => $staff, 'days' => $days,
        ];
    }

    /** @return array<int, int> */
    public function allocateRevenue(Invoice $invoice): array
    {
        $remaining = (int) $invoice->total_received;
        $lineTotal = max(1, (int) $invoice->items->sum('line_total'));
        $amounts = [];
        foreach ($invoice->items as $index => $item) {
            $allocated = $index === $invoice->items->count() - 1 ? $remaining : min($remaining, (int) floor($invoice->total_received * $item->line_total / $lineTotal));
            $amounts[$item->id] = $allocated;
            $remaining -= $allocated;
        }

        return $amounts;
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, array<string, mixed>>
     */
    public function details(Collection $invoices, string $employeeFilter): array
    {
        $rows = [];
        foreach ($invoices as $invoice) {
            $amounts = $this->allocateRevenue($invoice);
            $assignedOfferings = [];
            foreach ($invoice->items as $item) {
                $commissions = in_array($item->offering_id, $assignedOfferings, true) ? collect() : $invoice->commissionEntries->where('offering_id', $item->offering_id)->values();
                $assignedOfferings[] = $item->offering_id;
                if ($employeeFilter !== '' && (string) $item->employee_id !== $employeeFilter && ! $commissions->contains('employee_id', (int) $employeeFilter)) {
                    continue;
                }
                $rows[] = ['invoice' => $invoice, 'item' => $item, 'amount' => $amounts[$item->id], 'commissions' => $commissions];
            }
        }

        return $rows;
    }
}
