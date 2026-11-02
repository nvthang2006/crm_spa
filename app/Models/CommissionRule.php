<?php

namespace App\Models;

use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    protected $fillable = ['offering_id', 'beneficiary_employee_id', 'beneficiary_type', 'basis', 'calculation_type', 'value', 'starts_on', 'ends_on', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function beneficiaryEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'beneficiary_employee_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
