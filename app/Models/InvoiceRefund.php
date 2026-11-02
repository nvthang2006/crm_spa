<?php

namespace App\Models;

use Database\Factories\InvoiceRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceRefund extends Model
{
    /** @use HasFactory<InvoiceRefundFactory> */
    use HasFactory;

    protected $fillable = ['invoice_id', 'processed_by', 'code', 'method', 'amount', 'reference', 'reason', 'refunded_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
