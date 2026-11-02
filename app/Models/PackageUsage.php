<?php

namespace App\Models;

use Database\Factories\PackageUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageUsage extends Model
{
    /** @use HasFactory<PackageUsageFactory> */
    use HasFactory;

    protected $fillable = ['customer_package_id', 'appointment_id', 'performed_by', 'recorded_by', 'session_number', 'direction', 'verification_method', 'verification_note', 'used_at'];

    protected function casts(): array
    {
        return ['session_number' => 'integer', 'used_at' => 'datetime'];
    }

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'performed_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
