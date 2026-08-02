<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingPhone extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_id',
        'number',
        'has_whats',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'has_whats' => 'int',
            'position' => 'int',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }
}
