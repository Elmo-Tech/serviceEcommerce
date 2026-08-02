<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setting extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'site_name_ar',
        'site_name_en',
        'site_description_ar',
        'site_description_en',
        'slogan_ar',
        'slogan_en',
        'address_ar',
        'address_en',
        'public_email',
        'logo_path',
        'footer_logo_path',
        'favicon_path',
        'google_maps_url',
        'latitude',
        'longitude',
        'default_seo_title_ar',
        'default_seo_title_en',
        'default_seo_description_ar',
        'default_seo_description_en',
        'default_seo_keywords_ar',
        'default_seo_keywords_en',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'default_seo_keywords_ar' => 'array',
            'default_seo_keywords_en' => 'array',
        ];
    }

    public function phones(): HasMany
    {
        return $this->hasMany(SettingPhone::class)->orderBy('position');
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(SettingSocialLink::class)->orderBy('position');
    }

    public function logoUrl(): ?string
    {
        return $this->storageUrl($this->logo_path);
    }

    public function footerLogoUrl(): ?string
    {
        return $this->storageUrl($this->footer_logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->storageUrl($this->favicon_path);
    }

    private function storageUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $url = \Storage::disk((string) config('filesystems.default', 'public'))->url($path);

        return parse_url($url, PHP_URL_HOST) !== null ? $url : url($url);
    }
}
