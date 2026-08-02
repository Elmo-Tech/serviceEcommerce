<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderIdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIdempotencyKey extends Model
{
    /** @use HasFactory<OrderIdempotencyKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'request_fingerprint',
        'order_id',
        'reserved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'reserved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
