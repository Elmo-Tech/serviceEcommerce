<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Settings\SocialPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingSocialLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_id',
        'platform',
        'url',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'position' => 'int',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }
}
