<?php

declare(strict_types=1);

namespace App\Services\Categories;

use Illuminate\Support\Str;

class LocalizedSlugService
{
    public function normalizeArabic(string $value): string
    {
        $trimmed = trim($value);

        $normalized = preg_replace('/[\s\-_]+/u', '-', $trimmed) ?? $trimmed;
        $normalized = preg_replace('/[^\p{L}\p{N}\-]+/u', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/-+/u', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return $normalized;
    }

    public function normalizeEnglish(string $value): string
    {
        $trimmed = Str::lower(trim($value));

        $normalized = preg_replace('/[\s\-_]+/u', '-', $trimmed) ?? $trimmed;
        $normalized = preg_replace('/[^\p{L}\p{N}\-]+/u', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/-+/u', '-', $normalized) ?? $normalized;

        return trim($normalized, '-');
    }

    public function normalizeForLocale(string $locale, string $value): string
    {
        return $locale === 'ar'
            ? $this->normalizeArabic($value)
            : $this->normalizeEnglish($value);
    }

    public function generatePairFromNames(string $nameAr, string $nameEn, ?string $slugAr = null, ?string $slugEn = null): array
    {
        $resolvedSlugAr = $this->normalizeArabic($slugAr !== null && trim($slugAr) !== '' ? $slugAr : $nameAr);
        $resolvedSlugEn = $this->normalizeEnglish($slugEn !== null && trim($slugEn) !== '' ? $slugEn : $nameEn);

        return [
            'slugAr' => $resolvedSlugAr,
            'slugEn' => $resolvedSlugEn,
        ];
    }
}
