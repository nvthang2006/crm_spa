<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CustomerPackage;
use App\Models\Invoice;
use App\Models\OperationalAlert;
use App\Models\OperationalAlertRead;
use App\Models\ServiceResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class OperationalAlertService
{
    /**
     * @return Collection<int, OperationalAlert>
     */
    public function forUser(User $user): Collection
    {
        $this->sync();

        return OperationalAlert::query()
            ->where('is_active', true)
            ->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByRaw("CASE severity WHEN 'danger' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return $this->forUser($user)->filter(fn (OperationalAlert $alert): bool => $alert->reads->isEmpty())->count();
    }

    public function markRead(User $user, int $alertId): void
    {
        OperationalAlertRead::query()->updateOrCreate(
            ['operational_alert_id' => $alertId, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    public function markAllRead(User $user): void
    {
        $alerts = $this->forUser($user);
        foreach ($alerts as $alert) {
            $this->markRead($user, $alert->id);
        }
    }

    public function sync(): void
    {
        $now = CarbonImmutable::now();
        $alertDefinitions = [];

        $appointments = Appointment::query()
            ->where('status', 'scheduled')
            ->whereBetween('appointment_date', [$now->toDateString(), $now->addDay()->toDateString()])
            ->with('customer')
            ->get();
        foreach ($appointments as $appointment) {
            $startsAt = CarbonImmutable::parse($appointment->appointment_date->toDateString().' '.$appointment->start_time);
            if ($startsAt->isBefore($now) || $startsAt->isAfter($now->addMinutes(60))) {
                continue;
            }
            $alertDefinitions['appointment-'.$appointment->id] = [
                'type' => 'appointment_due',
                'severity' => 'warning',
                'title' => 'Lịch hẹn sắp đến',
                'message' => $appointment->code.' · '.$appointment->customer->name.' bắt đầu lúc '.$startsAt->format('H:i').'.',
                'action_url' => route('appointments', ['date' => $appointment->appointment_date->toDateString(), 'search' => $appointment->customer->name]),
                'context' => ['appointment_id' => $appointment->id],
            ];
        }

        $invoices = Invoice::query()->where('status', 'pending')->with('customer')->get();
        foreach ($invoices as $invoice) {
            $alertDefinitions['invoice-'.$invoice->id] = [
                'type' => 'invoice_pending',
                'severity' => 'danger',
                'title' => 'Phiếu thu đang chờ thanh toán',
                'message' => $invoice->code.' · '.$invoice->customer->name.' cần được xử lý tại thu ngân.',
                'action_url' => route('invoices', ['search' => $invoice->code, 'statusFilter' => 'pending']),
                'context' => ['invoice_id' => $invoice->id],
            ];
        }

        $resources = config('spa.resource_management_enabled')
            ? ServiceResource::query()->whereIn('status', ['cleaning', 'maintenance'])->get()
            : collect();
        foreach ($resources as $resource) {
            $isMaintenance = $resource->status === 'maintenance';
            $alertDefinitions['resource-'.$resource->id] = [
                'type' => $isMaintenance ? 'resource_maintenance' : 'resource_cleaning',
                'severity' => $isMaintenance ? 'danger' : 'warning',
                'title' => $isMaintenance ? 'Tài nguyên đang bảo trì' : 'Tài nguyên chờ dọn',
                'message' => $resource->name.' · '.$resource->status_text.'.',
                'action_url' => route('rooms'),
                'context' => ['resource_id' => $resource->id],
            ];
        }

        $packages = CustomerPackage::query()
            ->where('status', 'active')
            ->whereColumn('sessions_used', '<', 'sessions_total')
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [$now->toDateString(), $now->addDays(7)->toDateString()])
            ->with('customer')
            ->get();
        foreach ($packages as $package) {
            $alertDefinitions['package-'.$package->id] = [
                'type' => 'package_expiring',
                'severity' => 'warning',
                'title' => 'Thẻ liệu trình sắp hết hạn',
                'message' => $package->code.' · '.$package->customer->name.' hết hạn ngày '.$package->expires_on->format('d/m/Y').'.',
                'action_url' => route('customers', ['search' => $package->customer->name]),
                'context' => ['customer_package_id' => $package->id],
            ];
        }

        foreach ($alertDefinitions as $alertKey => $definition) {
            OperationalAlert::query()->updateOrCreate(
                ['alert_key' => $alertKey],
                $definition + ['is_active' => true, 'resolved_at' => null],
            );
        }

        OperationalAlert::query()
            ->where('is_active', true)
            ->when($alertDefinitions !== [], fn ($query) => $query->whereNotIn('alert_key', array_keys($alertDefinitions)))
            ->when($alertDefinitions === [], fn ($query) => $query)
            ->update(['is_active' => false, 'resolved_at' => now()]);
    }
}
