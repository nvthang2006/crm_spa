<?php

namespace App\Models;

use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    protected $fillable = ['code', 'period_start', 'period_end', 'status', 'total_base_salary', 'total_commission', 'total_net_pay', 'locked_by', 'locked_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'total_base_salary' => 'integer', 'total_commission' => 'integer', 'total_net_pay' => 'integer', 'locked_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
