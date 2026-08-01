<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceSlugReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSlugReservation extends Model
{
    /** @use HasFactory<ServiceSlugReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
