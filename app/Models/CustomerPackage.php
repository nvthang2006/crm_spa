<?php

namespace App\Models;

use Database\Factories\CustomerPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPackage extends Model
{
    /** @use HasFactory<CustomerPackageFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'offering_id', 'code', 'purchased_on', 'starts_on', 'expires_on', 'sessions_total', 'sessions_used', 'unit_price', 'status', 'notes'];

    protected function casts(): array
    {
        return ['purchased_on' => 'date', 'starts_on' => 'date', 'expires_on' => 'date', 'sessions_total' => 'integer', 'sessions_used' => 'integer', 'unit_price' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
