<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'offering_id', 'primary_employee_id', 'support_employee_id', 'service_resource_id', 'customer_package_id', 'created_by', 'code', 'appointment_date', 'start_time', 'end_time', 'status', 'source', 'payment_type', 'checked_in_at', 'started_at', 'completed_at', 'cancelled_at', 'cancel_reason', 'override_conflict_reason', 'notes'];

    protected function casts(): array
    {
        return ['appointment_date' => 'date', 'checked_in_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function primaryEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'primary_employee_id');
    }

    public function supportEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'support_employee_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'service_resource_id');
    }

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
