<?php

namespace App\Models;

use Database\Factories\CustomerHealthProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerHealthProfile extends Model
{
    /** @use HasFactory<CustomerHealthProfileFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'preferred_employee_id', 'last_updated_by', 'allergy_notes', 'current_condition', 'contraindications', 'care_notes'];

    protected function casts(): array
    {
        return [
            'allergy_notes' => 'encrypted',
            'current_condition' => 'encrypted',
            'contraindications' => 'encrypted',
            'care_notes' => 'encrypted',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function preferredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'preferred_employee_id');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
