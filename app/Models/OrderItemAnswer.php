<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServiceOrderFieldType;
use Database\Factories\OrderItemAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAnswer extends Model
{
    /** @use HasFactory<OrderItemAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'service_order_field_id',
        'question_ar',
        'question_en',
        'field_type',
        'is_required',
        'answer',
    ];

    protected function casts(): array
    {
        return [
            'order_item_id' => 'integer',
            'service_order_field_id' => 'integer',
            'field_type' => ServiceOrderFieldType::class,
            'is_required' => 'boolean',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function serviceOrderField(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderField::class);
    }
}
