<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Categories\LocalizedSlugService;
use Illuminate\Support\Facades\DB;

class CreateCategoryAction
{
    public function __construct(
        private readonly LocalizedSlugService $localizedSlugService,
    ) {}

    public function execute(array $payload): Category
    {
        $nameAr = $this->normalizeName((string) $payload['nameAr']);
        $nameEn = $this->normalizeName((string) $payload['nameEn']);
        $descriptions = $this->normalizeDescriptions($payload);
        $slugs = $this->localizedSlugService->generatePairFromNames(
            $nameAr,
            $nameEn,
            $payload['slugAr'] ?? null,
            $payload['slugEn'] ?? null,
        );

        return DB::transaction(function () use ($nameAr, $nameEn, $descriptions, $slugs, $payload): Category {
            return Category::query()->create([
                'parent_id' => null,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'description_ar' => $descriptions['description_ar'],
                'description_en' => $descriptions['description_en'],
                'slug_ar' => $slugs['slugAr'],
                'slug_en' => $slugs['slugEn'],
                'sort_order' => (int) ($payload['sortOrder'] ?? 0),
                'is_active' => (bool) ($payload['isActive'] ?? true),
            ]);
        });
    }

    private function normalizeName(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    private function normalizeDescriptions(array $payload): array
    {
        return [
            'description_ar' => $payload['descriptionAr'] ?? null,
            'description_en' => $payload['descriptionEn'] ?? null,
        ];
    }
}
