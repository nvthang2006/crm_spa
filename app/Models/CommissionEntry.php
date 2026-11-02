<?php

namespace App\Models;

use Database\Factories\CommissionEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEntry extends Model
{
    /** @use HasFactory<CommissionEntryFactory> */
    use HasFactory;

    protected $fillable = ['employee_id', 'invoice_id', 'appointment_id', 'offering_id', 'commission_rule_id', 'basis', 'calculation_type', 'rate', 'amount', 'status', 'occurred_at'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'amount' => 'integer', 'occurred_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}
