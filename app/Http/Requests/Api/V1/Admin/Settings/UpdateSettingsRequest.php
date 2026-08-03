<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Settings;

use App\Enums\Settings\SocialPlatform;
use App\Services\Settings\EgyptianPhoneNormalizer;
use App\Services\Settings\SvgSafetyInspector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siteNameAr' => ['sometimes', 'string', 'min:1'],
            'siteNameEn' => ['sometimes', 'string', 'min:1'],
            'siteDescriptionAr' => ['sometimes', 'nullable', 'string', 'max:500'],
            'siteDescriptionEn' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sloganAr' => ['sometimes', 'nullable', 'string'],
            'sloganEn' => ['sometimes', 'nullable', 'string'],
            'publicEmail' => ['sometimes', 'string', 'email:rfc', 'min:1', 'max:255'],
            'addressAr' => ['sometimes', 'nullable', 'string'],
            'addressEn' => ['sometimes', 'nullable', 'string'],
            'googleMapsUrl' => ['sometimes', 'nullable', 'url:http,https'],
            'latitude' => ['sometimes', 'nullable', 'regex:/^-?(?:90(?:\.0{1,7})?|(?:[0-8]?\d)(?:\.\d{1,7})?)$/'],
            'longitude' => ['sometimes', 'nullable', 'regex:/^-?(?:180(?:\.0{1,7})?|(?:1[0-7]\d|[0-9]?\d)(?:\.\d{1,7})?)$/'],
            'defaultSeoTitleAr' => ['sometimes', 'nullable', 'string'],
            'defaultSeoTitleEn' => ['sometimes', 'nullable', 'string'],
            'defaultSeoDescriptionAr' => ['sometimes', 'nullable', 'string'],
            'defaultSeoDescriptionEn' => ['sometimes', 'nullable', 'string'],
            'defaultSeoKeywordsAr' => ['sometimes', 'array'],
            'defaultSeoKeywordsAr.*' => ['string', 'min:1'],
            'defaultSeoKeywordsEn' => ['sometimes', 'array'],
            'defaultSeoKeywordsEn.*' => ['string', 'min:1'],
            'phones' => ['sometimes', 'array', 'max:3'],
            'phones.*.number' => ['required_with:phones', 'string', 'regex:/^01[0125][0-9]{8}$/'],
            'phones.*.hasWhats' => ['required_with:phones', 'integer', Rule::in([0, 1])],
            'socialLinks' => ['sometimes', 'array', 'max:9'],
            'socialLinks.*.platform' => ['required_with:socialLinks', 'string', Rule::in(SocialPlatform::keys())],
            'socialLinks.*.url' => ['required_with:socialLinks', 'string', 'url:http,https'],
            'logo' => ['sometimes', 'nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,svg'],
            'footerLogo' => ['sometimes', 'nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,svg'],
            'favicon' => ['sometimes', 'nullable', 'file', 'max:1024', 'mimes:png,ico,svg'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->guardUnexpectedKeys($validator);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->all() === [] && $this->allFiles() === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $this->ensureApprovedFileExtensions($validator);
                $this->ensureSafeSvgFiles($validator);

                $latitudeExists = array_key_exists('latitude', $this->all());
                $longitudeExists = array_key_exists('longitude', $this->all());

                if ($latitudeExists xor $longitudeExists) {
                    $validator->errors()->add('coordinates', __('validation.invalid_payload'));
                }

                if ($latitudeExists && $longitudeExists) {
                    $latitude = $this->input('latitude');
                    $longitude = $this->input('longitude');

                    if (($latitude === null) xor ($longitude === null)) {
                        $validator->errors()->add('coordinates', __('validation.invalid_payload'));
                    }
                }

                $this->ensureUniqueKeywordArray($validator, 'defaultSeoKeywordsAr');
                $this->ensureUniqueKeywordArray($validator, 'defaultSeoKeywordsEn');
                $this->ensureUniquePhones($validator);
                $this->ensureSingleWhatsapp($validator);
                $this->ensureUniqueSocialPlatforms($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['phones', 'socialLinks'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) === '[]') {
                $payload[$field] = [];
            }
        }

        foreach ([
            'siteNameAr',
            'siteNameEn',
            'siteDescriptionAr',
            'siteDescriptionEn',
            'sloganAr',
            'sloganEn',
            'addressAr',
            'addressEn',
            'defaultSeoTitleAr',
            'defaultSeoTitleEn',
            'defaultSeoDescriptionAr',
            'defaultSeoDescriptionEn',
        ] as $field) {
            if (array_key_exists($field, $this->all())) {
                $payload[$field] = $this->normalizeOptionalString($this->input($field));
            }
        }

        if (array_key_exists('publicEmail', $this->all())) {
            $email = $this->normalizeOptionalString($this->input('publicEmail'));
            $payload['publicEmail'] = $email === null ? null : mb_strtolower($email);
        }

        if (array_key_exists('googleMapsUrl', $this->all())) {
            $payload['googleMapsUrl'] = $this->normalizeOptionalString($this->input('googleMapsUrl'));
        }

        if (array_key_exists('latitude', $this->all())) {
            $payload['latitude'] = $this->normalizeOptionalString($this->input('latitude'));
        }

        if (array_key_exists('longitude', $this->all())) {
            $payload['longitude'] = $this->normalizeOptionalString($this->input('longitude'));
        }

        foreach (['defaultSeoKeywordsAr', 'defaultSeoKeywordsEn'] as $field) {
            if (is_array($this->input($field))) {
                $payload[$field] = array_map(
                    fn ($value) => $this->normalizeOptionalString($value),
                    (array) $this->input($field),
                );
            }
        }

        if (is_array($this->input('phones'))) {
            $normalizer = app(EgyptianPhoneNormalizer::class);
            $phones = [];

            foreach ((array) $this->input('phones') as $phone) {
                if (! is_array($phone)) {
                    $phones[] = $phone;

                    continue;
                }

                $normalized = $normalizer->normalize((string) ($phone['number'] ?? ''));
                $phones[] = [
                    'number' => $normalized ?? $this->normalizeOptionalString($phone['number'] ?? null),
                    'hasWhats' => array_key_exists('hasWhats', $phone) ? (int) $phone['hasWhats'] : null,
                ];
            }

            $payload['phones'] = $phones;
        }

        if (is_array($this->input('socialLinks'))) {
            $socialLinks = [];

            foreach ((array) $this->input('socialLinks') as $link) {
                if (! is_array($link)) {
                    $socialLinks[] = $link;

                    continue;
                }

                $socialLinks[] = [
                    'platform' => mb_strtolower((string) ($link['platform'] ?? '')),
                    'url' => $this->normalizeOptionalString($link['url'] ?? null),
                ];
            }

            $payload['socialLinks'] = $socialLinks;
        }

        foreach (['logo', 'footerLogo', 'favicon'] as $field) {
            if (! $this->hasFile($field) && array_key_exists($field, $this->all())) {
                $value = $this->input($field);

                if ($value === null || is_string($value) && trim($value) === '') {
                    $payload[$field] = null;
                }
            }
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = $this->validated();

        foreach (['logo', 'footerLogo', 'favicon'] as $field) {
            $file = $this->file($field);
            if ($file instanceof UploadedFile) {
                $payload[$field] = $file;
            }
        }

        return $payload;
    }

    private function guardUnexpectedKeys(Validator $validator): void
    {
        $allowedTopLevel = [
            'siteNameAr', 'siteNameEn', 'siteDescriptionAr', 'siteDescriptionEn',
            'sloganAr', 'sloganEn', 'publicEmail', 'addressAr', 'addressEn',
            'googleMapsUrl', 'latitude', 'longitude', 'defaultSeoTitleAr',
            'defaultSeoTitleEn', 'defaultSeoDescriptionAr', 'defaultSeoDescriptionEn',
            'defaultSeoKeywordsAr', 'defaultSeoKeywordsEn', 'phones', 'socialLinks',
            'logo', 'footerLogo', 'favicon',
        ];

        if (array_diff(array_keys($this->all()), $allowedTopLevel) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        foreach ((array) $this->input('phones', []) as $phone) {
            if (is_array($phone) && array_diff(array_keys($phone), ['number', 'hasWhats']) !== []) {
                $validator->errors()->add('payload', __('validation.invalid_payload'));
            }
        }

        foreach ((array) $this->input('socialLinks', []) as $link) {
            if (is_array($link) && array_diff(array_keys($link), ['platform', 'url']) !== []) {
                $validator->errors()->add('payload', __('validation.invalid_payload'));
            }
        }
    }

    private function ensureUniqueKeywordArray(Validator $validator, string $field): void
    {
        $keywords = $this->input($field);

        if (! is_array($keywords)) {
            return;
        }

        $seen = [];

        foreach ($keywords as $keyword) {
            $normalized = mb_strtolower((string) $this->normalizeOptionalString($keyword));

            if ($normalized === '') {
                $validator->errors()->add($field, __('validation.invalid_payload'));

                continue;
            }

            if (in_array($normalized, $seen, true)) {
                $validator->errors()->add($field, __('settings.duplicate_keywords'));

                return;
            }

            $seen[] = $normalized;
        }
    }

    private function ensureUniquePhones(Validator $validator): void
    {
        $phones = $this->input('phones');

        if (! is_array($phones)) {
            return;
        }

        $numbers = [];

        foreach ($phones as $phone) {
            if (! is_array($phone) || ! isset($phone['number'])) {
                continue;
            }

            $number = (string) $phone['number'];

            if (in_array($number, $numbers, true)) {
                $validator->errors()->add('phones', __('settings.duplicate_phones'));

                return;
            }

            $numbers[] = $number;
        }
    }

    private function ensureSingleWhatsapp(Validator $validator): void
    {
        $phones = $this->input('phones');

        if (! is_array($phones)) {
            return;
        }

        $count = 0;

        foreach ($phones as $phone) {
            if (is_array($phone) && (int) ($phone['hasWhats'] ?? 0) === 1) {
                $count++;
            }
        }

        if ($count > 1) {
            $validator->errors()->add('phones', __('settings.multiple_whatsapp_numbers'));
        }
    }

    private function ensureUniqueSocialPlatforms(Validator $validator): void
    {
        $links = $this->input('socialLinks');

        if (! is_array($links)) {
            return;
        }

        $platforms = [];

        foreach ($links as $link) {
            if (! is_array($link) || ! isset($link['platform'])) {
                continue;
            }

            $platform = (string) $link['platform'];

            if (in_array($platform, $platforms, true)) {
                $validator->errors()->add('socialLinks', __('settings.duplicate_social_platforms'));

                return;
            }

            $platforms[] = $platform;
        }
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    private function ensureApprovedFileExtensions(Validator $validator): void
    {
        foreach ([
            'logo' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'footerLogo' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'favicon' => ['png', 'ico', 'svg'],
        ] as $field => $allowedExtensions) {
            $file = $this->file($field);

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $extension = mb_strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, $allowedExtensions, true)) {
                $validator->errors()->add($field, __('validation.mimes', [
                    'attribute' => $field,
                    'values' => implode(', ', $allowedExtensions),
                ]));
            }
        }
    }

    private function ensureSafeSvgFiles(Validator $validator): void
    {
        $inspector = app(SvgSafetyInspector::class);

        foreach (['logo', 'footerLogo', 'favicon'] as $field) {
            $file = $this->file($field);

            if ($file instanceof UploadedFile && ! $inspector->isSafe($file)) {
                $validator->errors()->add($field, __('settings.invalid_svg'));
            }
        }
    }
}
