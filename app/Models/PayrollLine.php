<?php

namespace App\Models;

use Database\Factories\PayrollLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    /** @use HasFactory<PayrollLineFactory> */
    use HasFactory;

    protected $fillable = ['payroll_period_id', 'employee_id', 'base_salary', 'work_days', 'commission_amount', 'tips', 'deductions', 'net_pay', 'status'];

    protected function casts(): array
    {
        return ['base_salary' => 'integer', 'work_days' => 'integer', 'commission_amount' => 'integer', 'tips' => 'integer', 'deductions' => 'integer', 'net_pay' => 'integer'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
