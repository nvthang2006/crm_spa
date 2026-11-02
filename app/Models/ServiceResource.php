<?php

namespace App\Models;

use Database\Factories\ServiceResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceResource extends Model
{
    /** @use HasFactory<ServiceResourceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['resource_area_id', 'code', 'name', 'resource_type', 'status', 'maintenance_note', 'maintenance_until'];

    protected function casts(): array
    {
        return ['maintenance_until' => 'datetime'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ResourceArea::class, 'resource_area_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            'busy' => 'spa-bed-busy',
            'cleaning' => 'spa-bed-cleaning',
            'maintenance' => 'spa-bed-maintenance',
            default => 'spa-bed-ready',
        };
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'busy' => 'Đang phục vụ',
            'cleaning' => 'Chờ dọn dẹp',
            'maintenance' => 'Đang bảo trì',
            default => 'Trống sạch (sẵn sàng)',
        };
    }

    public function getShortCodeAttribute(): string
    {
        $label = preg_replace('/^([A-Z]{1,4})0*(\d+)$/', '$1$2', $this->code) ?: $this->name;
        if (str_starts_with($label, 'BODY')) {
            return 'B'.substr($label, 4);
        }

        return $label;
    }
}
