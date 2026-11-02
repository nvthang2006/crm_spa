<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'phone', 'email', 'membership_tier', 'birthday', 'address', 'total_spend', 'last_visit_at', 'status', 'notes'];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'last_visit_at' => 'datetime', 'total_spend' => 'integer'];
    }

    public function healthProfile(): HasOne
    {
        return $this->hasOne(CustomerHealthProfile::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
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
