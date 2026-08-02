<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemSelectedOptionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemSelectedOptionValue extends Model
{
    /** @use HasFactory<OrderItemSelectedOptionValueFactory> */
    use HasFactory;

    protected $fillable = [
        'order_item_selected_option_id',
        'pricing_option_value_id',
        'value_label_ar',
        'value_label_en',
        'price_adjustment',
    ];

    protected function casts(): array
    {
        return [
            'order_item_selected_option_id' => 'integer',
            'pricing_option_value_id' => 'integer',
            'price_adjustment' => 'decimal:2',
        ];
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(OrderItemSelectedOption::class, 'order_item_selected_option_id');
    }

    public function pricingOptionValue(): BelongsTo
    {
        return $this->belongsTo(ServicePricingOptionValue::class, 'pricing_option_value_id');
    }
}
