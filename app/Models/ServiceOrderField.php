<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServiceOrderFieldType;
use Database\Factories\ServiceOrderFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderField extends Model
{
    /** @use HasFactory<ServiceOrderFieldFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'service_id',
        'label_ar',
        'label_en',
        'field_type',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'field_type' => ServiceOrderFieldType::class,
            'is_required' => 'boolean',
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
