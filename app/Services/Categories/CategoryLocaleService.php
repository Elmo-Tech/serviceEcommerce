<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;

class CategoryLocaleService
{
    public function resolvedLocale(?string $locale = null): string
    {
        $resolved = $locale ?? app()->getLocale();

        return $resolved === 'en' ? 'en' : 'ar';
    }

    public function field(string $baseField, ?string $locale = null): string
    {
        return sprintf('%s_%s', $baseField, $this->resolvedLocale($locale));
    }

    public function value(Category $category, string $baseField, ?string $locale = null): mixed
    {
        return $category->getAttribute($this->field($baseField, $locale));
    }

    public function direction(?string $locale = null): string
    {
        return $this->resolvedLocale($locale) === 'ar' ? 'rtl' : 'ltr';
    }
}
