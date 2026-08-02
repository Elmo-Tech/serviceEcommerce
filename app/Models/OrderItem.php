<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServicePriceType;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'service_id',
        'service_name_ar',
        'service_name_en',
        'service_slug_ar',
        'service_slug_en',
        'price_type',
        'base_price',
        'unit_price',
        'quantity',
        'item_total',
        'item_note',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'service_id' => 'integer',
            'price_type' => ServicePriceType::class,
            'base_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'item_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function selectedOptions(): HasMany
    {
        return $this->hasMany(OrderItemSelectedOption::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(OrderItemAnswer::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OrderItemAttachment::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('id');
    }
}
