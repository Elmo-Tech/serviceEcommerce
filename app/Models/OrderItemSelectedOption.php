<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServicePricingInputType;
use Database\Factories\OrderItemSelectedOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItemSelectedOption extends Model
{
    /** @use HasFactory<OrderItemSelectedOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'pricing_option_id',
        'option_name_ar',
        'option_name_en',
        'input_type',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'order_item_id' => 'integer',
            'pricing_option_id' => 'integer',
            'input_type' => ServicePricingInputType::class,
            'is_required' => 'boolean',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function pricingOption(): BelongsTo
    {
        return $this->belongsTo(ServicePricingOption::class, 'pricing_option_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(OrderItemSelectedOptionValue::class);
    }
}
