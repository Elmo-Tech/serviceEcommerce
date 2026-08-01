<?php

declare(strict_types=1);

namespace App\Services\Services;

use Illuminate\Support\Str;

class LocalizedServiceSlugService
{
    public function normalize(string $value): string
    {
        $value = trim($value);
        $asciiSlug = Str::slug($value, '-');

        if ($asciiSlug !== '') {
            return Str::limit($asciiSlug, 180, '');
        }

        $slug = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '-', $value) ?? '';
        $slug = preg_replace('/-+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return Str::limit(mb_strtolower($slug), 180, '');
    }

    public function generateFromName(string $name): string
    {
        return $this->normalize($name);
    }
}
