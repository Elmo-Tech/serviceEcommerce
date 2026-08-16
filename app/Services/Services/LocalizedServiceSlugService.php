<?php

declare(strict_types=1);

namespace App\Services\Services;

use Illuminate\Support\Str;

class LocalizedServiceSlugService
{
    public function normalize(string $value): string
    {
        return $this->normalizeArabic($value);
    }

    public function normalizeArabic(string $value): string
    {
        return $this->normalizeUnicodeSlug($value);
    }

    public function normalizeEnglish(string $value): string
    {
        return $this->normalizeUnicodeSlug(Str::lower(trim($value)));
    }

    public function generateFromName(string $name): string
    {
        return $this->generateFromArabicName($name);
    }

    public function generateFromArabicName(string $name): string
    {
        return $this->normalizeArabic($name);
    }

    public function generateFromEnglishName(string $name): string
    {
        return $this->normalizeEnglish($name);
    }

    private function normalizeUnicodeSlug(string $value): string
    {
        $slug = trim($value);
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
        $slug = preg_replace('/-+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return Str::limit(mb_strtolower($slug), 180, '');
    }
}
