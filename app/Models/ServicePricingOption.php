<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServicePricingInputType;
use App\Enums\Services\ServicePricingOptionType;
use Database\Factories\ServicePricingOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePricingOption extends Model
{
    /** @use HasFactory<ServicePricingOptionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'service_id',
        'name_ar',
        'name_en',
        'option_type',
        'input_type',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'option_type' => ServicePricingOptionType::class,
            'input_type' => ServicePricingInputType::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ServicePricingOptionValue::class, 'service_pricing_option_id');
    }

    public function activeValues(): HasMany
    {
        return $this->values()->whereNull('deleted_at')->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id');
    }
}
