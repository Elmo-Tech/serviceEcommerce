<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Services\ServiceMediaType;
use Database\Factories\ServiceMediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMedia extends Model
{
    /** @use HasFactory<ServiceMediaFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'type',
        'disk',
        'path',
        'stored_name',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'alt_text_ar',
        'alt_text_en',
        'is_main',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'type' => ServiceMediaType::class,
            'size_bytes' => 'integer',
            'is_main' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('type', ServiceMediaType::IMAGE);
    }

    public function scopeVideo(Builder $query): Builder
    {
        return $query->where('type', ServiceMediaType::VIDEO);
    }
}
