<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalAlert extends Model
{
    protected $fillable = ['alert_key', 'type', 'severity', 'title', 'message', 'action_url', 'context', 'is_active', 'resolved_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'is_active' => 'boolean', 'resolved_at' => 'datetime'];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(OperationalAlertRead::class);
    }
}
