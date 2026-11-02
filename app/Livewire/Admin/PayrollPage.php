<?php

namespace App\Livewire\Admin;

use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PayrollPage extends Component
{
    public string $periodStart;

    public string $periodEnd;

    public ?int $periodId = null;

    public bool $showAdjustmentForm = false;

    public string $adjustmentEmployeeId = '';

    public string $adjustmentType = 'bonus';

    public string $adjustmentAmount = '0';

    public string $adjustmentReason = '';

    public function mount(): void
    {
        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
        $this->periodId = PayrollPeriod::query()
            ->whereDate('period_start', $this->periodStart)
            ->whereDate('period_end', $this->periodEnd)
            ->value('id');
    }

    public function generatePayroll(): void
    {
        $period = app(PayrollService::class)->generate($this->periodStart, $this->periodEnd);
        $this->periodId = $period->id;
        session()->flash('success', 'Đã tính bảng lương kỳ '.Carbon::parse($this->periodStart)->format('m/Y').'.');
    }

    public function lockPayroll(): void
    {
        if ($this->periodId === null) {
            session()->flash('error', 'Hãy tính bảng lương trước khi chốt.');

            return;
        }
        app(PayrollService::class)->lock(PayrollPeriod::query()->findOrFail($this->periodId));
        session()->flash('success', 'Đã chốt và khóa kỳ lương.');
    }

    public function newAdjustment(): void
    {
        if ($this->periodId === null) {
            session()->flash('error', 'Hãy tính bảng lương trước khi tạo phiếu điều chỉnh.');

            return;
        }
        $this->reset(['adjustmentEmployeeId', 'adjustmentAmount', 'adjustmentReason']);
        $this->adjustmentType = 'bonus';
        $this->adjustmentAmount = '0';
        $this->showAdjustmentForm = true;
    }

    public function createAdjustment(): void
    {
        $validated = $this->validate([
            'adjustmentEmployeeId' => ['required', 'integer', 'exists:employees,id'],
            'adjustmentType' => ['required', 'in:bonus,deduction'],
            'adjustmentAmount' => ['required', 'integer', 'min:1'],
            'adjustmentReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        if ($this->periodId === null) {
            throw ValidationException::withMessages(['adjustment' => 'Chưa chọn kỳ lương.']);
        }
        app(PayrollService::class)->createAdjustment(
            PayrollPeriod::query()->findOrFail($this->periodId),
            (int) $validated['adjustmentEmployeeId'],
            $validated['adjustmentType'],
            (int) $validated['adjustmentAmount'],
            $validated['adjustmentReason'],
        );
        $this->showAdjustmentForm = false;
        session()->flash('success', 'Đã tạo phiếu điều chỉnh, chờ duyệt.');
    }

    public function reviewAdjustment(int $id, bool $approve): void
    {
        app(PayrollService::class)->reviewAdjustment(PayrollAdjustment::query()->findOrFail($id), $approve);
        session()->flash('success', $approve ? 'Đã duyệt phiếu điều chỉnh.' : 'Đã từ chối phiếu điều chỉnh.');
    }

    public function exportPayroll()
    {
        $period = $this->periodId ? PayrollPeriod::query()->with('lines.employee')->findOrFail($this->periodId) : null;
        if ($period === null) {
            session()->flash('error', 'Chưa có kỳ lương để xuất.');

            return null;
        }

        return response()->streamDownload(function () use ($period): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã NV', 'Nhân viên', 'Lương cứng', 'Ngày công', 'Hoa hồng', 'Thưởng', 'Khấu trừ', 'Thực lĩnh']);
            foreach ($period->lines as $line) {
                fputcsv($handle, [$line->employee->code, $line->employee->name, $line->base_salary, $line->work_days, $line->commission_amount, $line->tips, $line->deductions, $line->net_pay]);
            }
            fclose($handle);
        }, 'bang-luong-'.$period->code.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $period = $this->periodId ? PayrollPeriod::query()->with(['lines.employee', 'adjustments.employee'])->find($this->periodId) : null;
        $periods = PayrollPeriod::query()->latest('period_start')->limit(12)->get();
        $employees = Employee::query()->where('status', 'active')->orderBy('name')->get();
        $stats = ['base' => $period?->total_base_salary ?? 0, 'commission' => $period?->total_commission ?? 0, 'net' => $period?->total_net_pay ?? 0];

        return view('livewire.admin.payroll-page', compact('period', 'periods', 'employees', 'stats'))
            ->layout('layouts.admin', ['title' => 'Bảng lương & Hoa hồng']);
    }
}
