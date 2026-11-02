<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function generate(string $periodStart, string $periodEnd): PayrollPeriod
    {
        $validated = validator(
            ['periodStart' => $periodStart, 'periodEnd' => $periodEnd],
            [
                'periodStart' => ['required', 'date_format:Y-m-d'],
                'periodEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:periodStart'],
            ],
        )->validate();
        $periodStart = $validated['periodStart'];
        $periodEnd = $validated['periodEnd'];

        return DB::transaction(function () use ($periodStart, $periodEnd): PayrollPeriod {
            $periodCode = 'BL-'.str_replace('-', '', $periodStart);
            $period = PayrollPeriod::query()
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->first();

            if ($period === null) {
                if (PayrollPeriod::query()->where('code', $periodCode)->exists()) {
                    throw ValidationException::withMessages(['period' => 'Mã kỳ lương đã được dùng cho khoảng ngày khác.']);
                }
                $period = PayrollPeriod::query()->firstOrCreate(
                    ['code' => $periodCode],
                    ['period_start' => $periodStart, 'period_end' => $periodEnd, 'status' => 'draft'],
                );
            }
            if ($period->status === 'locked') {
                throw ValidationException::withMessages(['period' => 'Kỳ lương đã khóa, không thể tính lại.']);
            }
            $period->lines()->delete();
            $totalBase = 0;
            $totalCommission = 0;
            $totalNet = 0;
            Employee::query()->where('status', 'active')->each(function (Employee $employee) use ($period, $periodStart, $periodEnd, &$totalBase, &$totalCommission, &$totalNet): void {
                $baseSalary = (int) $employee->base_salary;
                $workDays = $employee->attendanceRecords()->whereBetween('attendance_date', [$periodStart, $periodEnd])->whereIn('status', ['present', 'late'])->count();
                $commission = (int) $employee->commissionEntries()->whereBetween('occurred_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])->where('status', 'pending')->sum('amount');
                $netPay = $baseSalary + $commission;
                $period->lines()->create(['employee_id' => $employee->id, 'base_salary' => $baseSalary, 'work_days' => $workDays, 'commission_amount' => $commission, 'net_pay' => $netPay, 'status' => 'draft']);
                $totalBase += $baseSalary;
                $totalCommission += $commission;
                $totalNet += $netPay;
            });
            $period->update(['total_base_salary' => $totalBase, 'total_commission' => $totalCommission, 'total_net_pay' => $totalNet]);
            $this->recalculate($period);
            app(AuditService::class)->record('payroll.generated', $period, null, $period->fresh()->toArray());

            return $period->fresh();
        });
    }

    public function lock(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status === 'locked') {
            return $period;
        }
        if ($period->lines()->doesntExist()) {
            throw ValidationException::withMessages(['period' => 'Chưa có dòng lương để chốt.']);
        }
        if ($period->adjustments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['period' => 'Cần duyệt hoặc từ chối toàn bộ phiếu điều chỉnh trước khi chốt.']);
        }
        DB::transaction(function () use ($period): void {
            $period->update(['status' => 'locked', 'locked_by' => auth()->id(), 'locked_at' => now()]);
            $period->lines()->update(['status' => 'locked']);
            $period->loadMissing('lines');
            $employeeIds = $period->lines->pluck('employee_id');
            CommissionEntry::query()->whereIn('employee_id', $employeeIds)->where('status', 'pending')->whereBetween('occurred_at', [$period->period_start.' 00:00:00', $period->period_end.' 23:59:59'])->update(['status' => 'locked']);
        });
        app(AuditService::class)->record('payroll.locked', $period, null, $period->fresh()->toArray());

        return $period->fresh();
    }

    public function createAdjustment(PayrollPeriod $period, int $employeeId, string $type, int $amount, string $reason): PayrollAdjustment
    {
        if ($period->status === 'locked') {
            throw ValidationException::withMessages(['period' => 'Kỳ lương đã khóa, không thể tạo phiếu điều chỉnh.']);
        }
        if (! in_array($type, ['bonus', 'deduction'], true) || $amount <= 0 || blank($reason)) {
            throw ValidationException::withMessages(['adjustment' => 'Phiếu điều chỉnh cần loại, số tiền dương và lý do.']);
        }
        $adjustment = PayrollAdjustment::query()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employeeId,
            'created_by' => auth()->id(),
            'code' => 'DC-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'type' => $type,
            'amount' => $amount,
            'status' => 'pending',
            'reason' => trim($reason),
        ]);
        app(AuditService::class)->record('payroll.adjustment_created', $adjustment, null, $adjustment->toArray(), $reason);

        return $adjustment;
    }

    public function reviewAdjustment(PayrollAdjustment $adjustment, bool $approve, ?string $reason = null): PayrollAdjustment
    {
        $adjustment->load('period');
        if ($adjustment->period->status === 'locked' || $adjustment->status !== 'pending') {
            throw ValidationException::withMessages(['adjustment' => 'Phiếu điều chỉnh không còn ở trạng thái chờ duyệt.']);
        }
        $before = $adjustment->getOriginal();
        $adjustment->update([
            'status' => $approve ? 'approved' : 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        if ($approve) {
            $this->recalculate($adjustment->period);
        }
        app(AuditService::class)->record($approve ? 'payroll.adjustment_approved' : 'payroll.adjustment_rejected', $adjustment, $before, $adjustment->fresh()->toArray(), $reason);

        return $adjustment->fresh();
    }

    public function recalculate(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status === 'locked') {
            throw ValidationException::withMessages(['period' => 'Kỳ lương đã khóa, không thể tính lại.']);
        }
        $period->loadMissing('lines', 'adjustments');
        $approved = $period->adjustments->where('status', 'approved')->groupBy('employee_id');
        $totalNet = 0;
        foreach ($period->lines as $line) {
            $employeeAdjustments = $approved->get($line->employee_id, collect());
            $tips = (int) $employeeAdjustments->whereIn('type', ['bonus', 'tip'])->sum('amount');
            $deductions = (int) $employeeAdjustments->where('type', 'deduction')->sum('amount');
            $netPay = max(0, (int) $line->base_salary + (int) $line->commission_amount + $tips - $deductions);
            $line->update(['tips' => $tips, 'deductions' => $deductions, 'net_pay' => $netPay]);
            $totalNet += $netPay;
        }
        $period->update(['total_net_pay' => $totalNet]);

        return $period->fresh();
    }
}
