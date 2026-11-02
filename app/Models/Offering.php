<?php

namespace App\Models;

use Database\Factories\OfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offering extends Model
{
    /** @use HasFactory<OfferingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['service_category_id', 'code', 'name', 'type', 'duration_minutes', 'price', 'sessions_total', 'status', 'featured', 'description'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price' => 'integer', 'sessions_total' => 'integer', 'featured' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
