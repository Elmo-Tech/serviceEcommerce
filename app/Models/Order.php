<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\Orders\DiscountType;
use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address_id',
        'address_province',
        'address_city',
        'address_text',
        'status',
        'order_place',
        'customer_note',
        'admin_note',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'discount_reason',
        'total',
        'payment_status',
        'paid_amount',
        'cancellation_reason',
        'cancelled_at',
        'completed_at',
        'cancelled_by_admin_id',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'customer_address_id' => 'integer',
            'status' => OrderStatus::class,
            'order_place' => OrderPlace::class,
            'subtotal' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'paid_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'completed_at' => UtcDateTime::class,
            'cancelled_by_admin_id' => 'integer',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function cancelledByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_admin_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function idempotencyKey(): HasOne
    {
        return $this->hasOne(OrderIdempotencyKey::class);
    }

    public function scopeOrderedLatest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED,
            OrderStatus::IN_PROGRESS,
        ], true);
    }
}
