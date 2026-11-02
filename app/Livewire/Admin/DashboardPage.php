<?php

namespace App\Livewire\Admin;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\ResourceArea;
use App\Models\ServiceResource;
use Illuminate\Support\Carbon;
use Livewire\Component;

class DashboardPage extends Component
{
    public string $selectedDate;

    public int $trendDays = 7;

    public function mount(): void
    {
        $this->selectedDate = today()->toDateString();
    }

    public function render()
    {
        $date = Carbon::parse($this->selectedDate)->locale('vi');
        $paidStatuses = ['paid'];
        $revenueToday = Invoice::query()->whereIn('status', $paidStatuses)->whereDate('paid_at', $date)->sum('total_received');
        $commissionToday = CommissionEntry::query()->whereDate('occurred_at', $date)->where('status', '!=', 'reversed')->sum('amount');
        $appointments = Appointment::query()->whereDate('appointment_date', $date)->with(['customer', 'offering', 'primaryEmployee', 'resource'])->orderBy('start_time')->get();
        $resources = config('spa.resource_management_enabled') ? ServiceResource::query()->get() : collect();
        $customersToday = Invoice::query()->where('status', 'paid')->whereDate('paid_at', $date)->distinct()->count('customer_id');
        $areas = config('spa.resource_management_enabled')
            ? ResourceArea::query()->with(['resources' => fn ($query) => $query->orderBy('name')])->orderBy('id')->get()
            : collect();
        $monthStart = $date->copy()->startOfMonth();
        $commissionMonth = CommissionEntry::query()->whereBetween('occurred_at', [$monthStart, $date->copy()->endOfDay()])->where('status', '!=', 'reversed')->sum('amount');
        $trend = collect(range($this->trendDays - 1, 0))->mapWithKeys(function (int $days) use ($date): array {
            $day = $date->copy()->subDays($days);

            return [$day->format('d/m') => (int) Invoice::query()->where('status', 'paid')->whereDate('paid_at', $day)->sum('total_received')];
        });
        $topEmployees = Employee::query()->withSum(['commissionEntries as month_commission' => fn ($query) => $query->whereBetween('occurred_at', [$monthStart, $date->copy()->endOfDay()])->where('status', '!=', 'reversed')], 'amount')->orderByDesc('month_commission')->limit(3)->get();
        $alerts = [
            'packages' => CustomerPackage::query()->where('status', 'active')->where(function ($query): void {
                $query->whereColumn('sessions_used', '>=', 'sessions_total')
                    ->orWhereRaw('sessions_total - sessions_used <= 2');
            })->where(function ($query): void {
                $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
            })->with('customer')->limit(5)->get(),
            'unassigned' => config('spa.resource_management_enabled') ? Appointment::query()->whereDate('appointment_date', $date)->whereNull('service_resource_id')->whereNotIn('status', ['cancelled', 'paid', 'package_used'])->count() : 0,
        ];

        $invoicesToday = Invoice::query()
            ->whereIn('status', $paidStatuses)
            ->whereDate('paid_at', $date)
            ->with(['customer', 'items.offering', 'items.employee', 'commissionEntries'])
            ->latest('paid_at')
            ->get();

        $sessionsPackageToday = $invoicesToday->filter(fn ($inv) => $inv->customer_package_id !== null || str_contains($inv->notes ?? '', 'làm gói'))->count();
        $sessionsRetailToday = $invoicesToday->count() - $sessionsPackageToday;

        $activeEmployees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $staffPerformanceToday = $activeEmployees->map(function ($emp) use ($date, $invoicesToday) {
            $empInvoices = $invoicesToday->filter(fn ($inv) => $inv->items->contains('employee_id', $emp->id));
            $packageCount = $empInvoices->filter(fn ($inv) => $inv->customer_package_id !== null || str_contains($inv->notes ?? '', 'làm gói'))->count();
            $retailCount = $empInvoices->count() - $packageCount;
            $revenue = $empInvoices->sum('total_received');
            $commission = (int) CommissionEntry::query()
                ->where('employee_id', $emp->id)
                ->whereDate('occurred_at', $date)
                ->where('status', '!=', 'reversed')
                ->sum('amount');

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'job_title' => $emp->job_title,
                'total_sessions' => $empInvoices->count(),
                'retail_sessions' => $retailCount,
                'package_sessions' => $packageCount,
                'revenue' => $revenue,
                'commission' => $commission,
            ];
        })->filter(fn ($s) => $s['total_sessions'] > 0 || $s['commission'] > 0)->sortByDesc('commission')->values();

        $recentShiftLogs = $invoicesToday->take(10)->map(function ($invoice) {
            $item = $invoice->items->first();
            $isPackage = $invoice->customer_package_id !== null || str_contains($invoice->notes ?? '', 'làm gói');
            $commission = (int) $invoice->commissionEntries->where('status', '!=', 'reversed')->sum('amount');

            return [
                'code' => $invoice->code,
                'time' => $invoice->paid_at?->format('H:i') ?? $invoice->issued_at?->format('H:i') ?? '--:--',
                'customer_name' => $invoice->customer?->name ?? 'Khách vãng lai',
                'customer_phone' => $invoice->customer?->phone ?? '',
                'is_package' => $isPackage,
                'offering_name' => $item?->offering?->name ?? $item?->description ?? 'Dịch vụ',
                'offering_type' => $item?->offering?->type ?? 'service',
                'amount' => $invoice->total_received,
                'employee_name' => $item?->employee?->name ?? 'KTV',
                'commission' => $commission,
            ];
        });

        return view('livewire.admin.dashboard-page', compact(
            'date', 'customersToday', 'revenueToday', 'commissionToday',
            'sessionsRetailToday', 'sessionsPackageToday', 'staffPerformanceToday', 'recentShiftLogs',
            'appointments', 'resources', 'areas', 'commissionMonth', 'trend', 'topEmployees', 'alerts'
        ))->layout('layouts.admin', ['title' => 'Tổng quan vận hành']);
    }
}
