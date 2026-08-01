<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServicePricingOptionValueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePricingOptionValue extends Model
{
    /** @use HasFactory<ServicePricingOptionValueFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'service_pricing_option_id',
        'label_ar',
        'label_en',
        'price_adjustment',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_pricing_option_id' => 'integer',
            'price_adjustment' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function pricingOption(): BelongsTo
    {
        return $this->belongsTo(ServicePricingOption::class, 'service_pricing_option_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id');
    }
}
