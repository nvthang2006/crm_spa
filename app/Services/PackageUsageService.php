<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageUsageService
{
    public function consume(CustomerPackage $package, ?Appointment $appointment = null, ?int $employeeId = null, ?string $note = null): PackageUsage
    {
        return DB::transaction(function () use ($package, $appointment, $employeeId, $note): PackageUsage {
            $lockedPackage = CustomerPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($appointment !== null) {
                $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
                if ($lockedAppointment->status !== 'completed_pending_payment' || $lockedAppointment->payment_type !== 'package' || (int) $lockedAppointment->customer_package_id !== (int) $lockedPackage->id || (int) $lockedAppointment->customer_id !== (int) $lockedPackage->customer_id || (int) $lockedAppointment->offering_id !== (int) $lockedPackage->offering_id) {
                    throw ValidationException::withMessages(['package' => 'Lịch hẹn không hợp lệ để trừ thẻ liệu trình.']);
                }
                if (PackageUsage::query()->where('appointment_id', $lockedAppointment->id)->where('direction', 'use')->exists()) {
                    throw ValidationException::withMessages(['package' => 'Lịch hẹn này đã được trừ buổi trước đó.']);
                }
                $appointment = $lockedAppointment;
            }
            if ($lockedPackage->status === 'active' && $lockedPackage->expires_on?->isBefore(today())) {
                $lockedPackage->update(['status' => 'expired']);
            }
            if ($lockedPackage->status !== 'active' || $lockedPackage->sessions_used >= $lockedPackage->sessions_total) {
                throw ValidationException::withMessages(['package' => 'Thẻ liệu trình không còn buổi có thể sử dụng.']);
            }
            $sessionNumber = $lockedPackage->sessions_used + 1;
            $usage = PackageUsage::query()->create([
                'customer_package_id' => $lockedPackage->id,
                'appointment_id' => $appointment?->id,
                'performed_by' => $employeeId,
                'recorded_by' => auth()->id(),
                'session_number' => $sessionNumber,
                'direction' => 'use',
                'verification_method' => 'admin_confirmation',
                'verification_note' => $note,
                'used_at' => now(),
            ]);
            $lockedPackage->increment('sessions_used');
            $lockedPackage->refresh();
            if ($lockedPackage->sessions_used >= $lockedPackage->sessions_total) {
                $lockedPackage->update(['status' => 'completed']);
            }
            app(AuditService::class)->record('package.session_used', $usage, null, $usage->toArray(), $note);

            return $usage;
        });
    }

    public function restore(CustomerPackage $package, int $sessionNumber, ?string $reason = null): PackageUsage
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['package' => 'Cần nhập lý do khi hoàn lại buổi.']);
        }

        return DB::transaction(function () use ($package, $sessionNumber, $reason): PackageUsage {
            $lockedPackage = CustomerPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($lockedPackage->status === 'refunded') {
                throw ValidationException::withMessages(['package' => 'Thẻ đã hoàn tiền, không thể hoàn lại buổi.']);
            }
            $usage = $lockedPackage->usages()->where('direction', 'use')->where('session_number', $sessionNumber)->lockForUpdate()->firstOrFail();
            if ($lockedPackage->usages()->where('direction', 'restore')->where('session_number', $sessionNumber)->exists()) {
                throw ValidationException::withMessages(['package' => 'Buổi này đã được hoàn lại trước đó.']);
            }
            if ($lockedPackage->sessions_used < 1) {
                throw ValidationException::withMessages(['package' => 'Thẻ không có buổi đã sử dụng để hoàn lại.']);
            }
            $restore = PackageUsage::query()->create([
                'customer_package_id' => $lockedPackage->id,
                'session_number' => $sessionNumber,
                'direction' => 'restore',
                'verification_method' => 'admin_confirmation',
                'verification_note' => $reason,
                'recorded_by' => auth()->id(),
                'used_at' => now(),
            ]);
            $lockedPackage->decrement('sessions_used');
            $lockedPackage->update(['status' => $lockedPackage->expires_on?->isBefore(today()) ? 'expired' : 'active']);
            app(AuditService::class)->record('package.session_restored', $restore, null, $restore->toArray(), $reason);

            return $restore;
        });
    }
}
