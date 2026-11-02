<?php

namespace App\Models;

use Database\Factories\ResourceAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceArea extends Model
{
    /** @use HasFactory<ResourceAreaFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'floor', 'description', 'status'];

    public function resources(): HasMany
    {
        return $this->hasMany(ServiceResource::class);
    }
}
