<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceSpecificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceSpecification extends Model
{
    /** @use HasFactory<ServiceSpecificationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'service_id',
        'label_ar',
        'label_en',
        'value_ar',
        'value_en',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id');
    }
}
