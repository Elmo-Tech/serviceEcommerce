<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderNumberSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderNumberSequence extends Model
{
    /** @use HasFactory<OrderNumberSequenceFactory> */
    use HasFactory;

    protected $fillable = [
        'business_date',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'last_sequence' => 'integer',
        ];
    }
}
