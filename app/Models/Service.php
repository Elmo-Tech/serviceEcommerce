<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServiceMediaType;
use App\Enums\Services\ServicePriceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name_ar',
        'name_en',
        'short_description_ar',
        'short_description_en',
        'description_ar',
        'description_en',
        'slug_ar',
        'slug_en',
        'production_time_ar',
        'production_time_en',
        'price_type',
        'base_price',
        'is_active',
        'is_available',
        'is_attachment_required',
        'seo_title_ar',
        'seo_title_en',
        'seo_description_ar',
        'seo_description_en',
        'seo_tags_ar',
        'seo_tags_en',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'price_type' => ServicePriceType::class,
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'is_attachment_required' => 'boolean',
            'seo_tags_ar' => 'array',
            'seo_tags_en' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function slugReservations(): HasMany
    {
        return $this->hasMany(ServiceSlugReservation::class);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ServiceSpecification::class);
    }

    public function orderFields(): HasMany
    {
        return $this->hasMany(ServiceOrderField::class);
    }

    public function pricingOptions(): HasMany
    {
        return $this->hasMany(ServicePricingOption::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ServiceMedia::class);
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ServiceMedia::class)
            ->where('type', ServiceMediaType::IMAGE)
            ->where('is_main', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeOrderedLatest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
