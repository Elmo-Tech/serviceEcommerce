<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAttachment extends Model
{
    /** @use HasFactory<OrderItemAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'disk',
        'path',
        'stored_name',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
    ];

    protected $hidden = [
        'path',
    ];

    protected function casts(): array
    {
        return [
            'order_item_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
