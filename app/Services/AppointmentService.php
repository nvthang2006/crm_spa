<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ServiceResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function assertNoConflict(Appointment $appointment, ?string $overrideReason = null): void
    {
        $employeeIds = array_values(array_filter([$appointment->primary_employee_id, $appointment->support_employee_id]));
        $conflicts = Appointment::query()
            ->whereDate('appointment_date', $appointment->appointment_date)
            ->where($appointment->getKeyName(), '!=', $appointment->getKey())
            ->whereNotIn('status', ['cancelled', 'no_show', 'paid', 'refunded', 'package_used'])
            ->where(function (Builder $query) use ($employeeIds, $appointment): void {
                $query->where(function (Builder $employeeQuery) use ($employeeIds): void {
                    $employeeQuery
                        ->whereIn('primary_employee_id', $employeeIds)
                        ->orWhereIn('support_employee_id', $employeeIds);
                });
                if (config('spa.resource_management_enabled')) {
                    $query->orWhere(function (Builder $resourceQuery) use ($appointment): void {
                        $resourceQuery->whereNotNull('service_resource_id')->where('service_resource_id', $appointment->service_resource_id);
                    });
                }
            })
            ->where('start_time', '<', $appointment->end_time)
            ->where('end_time', '>', $appointment->start_time)
            ->exists();

        if ($conflicts && blank($overrideReason)) {
            throw ValidationException::withMessages([
                'appointment' => config('spa.resource_management_enabled')
                    ? 'Lịch hẹn đang trùng KTV hoặc phòng/giường. Hãy nhập lý do nếu cần ghi đè.'
                    : 'Lịch hẹn đang trùng giờ nhân viên. Hãy nhập lý do nếu cần ghi đè.',
            ]);
        }
    }

    public function save(Appointment $appointment, ?string $overrideReason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $overrideReason): Appointment {
            $this->assertNoConflict($appointment, $overrideReason);
            $appointment->override_conflict_reason = $overrideReason;
            $appointment->save();
            app(AuditService::class)->record('appointment.saved', $appointment, null, $appointment->fresh()->toArray(), $overrideReason);

            return $appointment->fresh();
        });
    }

    public function transition(Appointment $appointment, string $status, ?string $reason = null): Appointment
    {
        $allowed = [
            'scheduled' => ['arrived', 'cancelled', 'no_show'],
            'arrived' => ['in_service', 'cancelled', 'no_show'],
            'in_service' => ['completed_pending_payment', 'cancelled'],
            'completed_pending_payment' => ['paid', 'package_used', 'cancelled'],
            'paid' => ['refunded'],
            'refunded' => [],
            'package_used' => [],
            'cancelled' => [],
            'no_show' => [],
        ];

        if (! in_array($status, $allowed[$appointment->status] ?? [], true)) {
            throw ValidationException::withMessages(['appointment' => 'Không thể chuyển lịch hẹn sang trạng thái này.']);
        }

        return DB::transaction(function () use ($appointment, $status, $reason): Appointment {
            $before = $appointment->getOriginal();
            $appointment->status = $status;
            if ($status === 'arrived') {
                $appointment->checked_in_at = now();
            }
            if ($status === 'in_service') {
                if (config('spa.resource_management_enabled') && $appointment->resource !== null && $appointment->resource->status !== 'ready') {
                    throw ValidationException::withMessages(['appointment' => 'Phòng/giường chưa sẵn sàng để bắt đầu phục vụ.']);
                }
                $appointment->started_at = now();
                if (config('spa.resource_management_enabled')) {
                    $appointment->resource?->update(['status' => 'busy']);
                }
            }
            if ($status === 'completed_pending_payment') {
                $appointment->completed_at = now();
                if (config('spa.resource_management_enabled')) {
                    $appointment->resource?->update(['status' => 'cleaning']);
                }
            }
            if ($status === 'cancelled') {
                $appointment->cancelled_at = now();
                $appointment->cancel_reason = $reason;
                if (config('spa.resource_management_enabled')) {
                    $appointment->resource?->update(['status' => 'ready']);
                }
                $pendingInvoice = $appointment->invoice()->lockForUpdate()->where('status', 'pending')->first();
                if ($pendingInvoice !== null) {
                    $invoiceBefore = $pendingInvoice->getOriginal();
                    $invoiceReason = trim('Tự động hủy theo lịch hẹn'.($reason ? ': '.$reason : '.'));
                    $pendingInvoice->update(['status' => 'void', 'voided_at' => now(), 'notes' => $invoiceReason]);
                    app(AuditService::class)->record('invoice.voided', $pendingInvoice, $invoiceBefore, $pendingInvoice->fresh()->toArray(), $invoiceReason);
                }
            }
            $appointment->save();
            app(AuditService::class)->record('appointment.status_changed', $appointment, $before, $appointment->fresh()->toArray(), $reason);

            return $appointment->fresh();
        });
    }

    public function markResourceReady(ServiceResource $resource): ServiceResource
    {
        if ($resource->status === 'busy') {
            throw ValidationException::withMessages(['resource' => 'Không thể đánh dấu sẵn sàng khi tài nguyên đang phục vụ.']);
        }
        $before = $resource->getOriginal();
        $resource->update(['status' => 'ready', 'maintenance_note' => null, 'maintenance_until' => null]);
        app(AuditService::class)->record('resource.cleaned', $resource, $before, $resource->fresh()->toArray());

        return $resource->fresh();
    }
}
