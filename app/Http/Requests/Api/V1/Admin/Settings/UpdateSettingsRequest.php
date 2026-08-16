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
            'publicEmail' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255'],
            'addressAr' => ['sometimes', 'nullable', 'string'],
            'addressEn' => ['sometimes', 'nullable', 'string'],
            'googleMapsUrl' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'phones' => ['sometimes', 'array', 'max:3'],
            'phones.*.number' => ['required_with:phones', 'string', 'regex:/^[0-9]{10,11}$/'],
            'phones.*.hasWhats' => ['required_with:phones', 'integer', Rule::in([0, 1])],
            'socialLinks' => ['sometimes', 'array', 'max:9'],
            'socialLinks.*.platform' => ['required_with:socialLinks', 'string', Rule::in(SocialPlatform::keys())],
            'socialLinks.*.url' => ['required_with:socialLinks', 'string', 'url:http,https'],
            'logo' => ['sometimes', 'nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,svg'],
            'footerLogo' => ['sometimes', 'nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,svg'],
            'favicon' => ['sometimes', 'nullable', 'file', 'max:1024', 'mimes:png,ico,svg'],
            'clearPhones' => ['sometimes', 'boolean'],
            'clearSocialLinks' => ['sometimes', 'boolean'],
            'removeLogo' => ['sometimes', 'boolean'],
            'removeFooterLogo' => ['sometimes', 'boolean'],
            'removeFavicon' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'publicEmail.string' => __('settings.validation.public_email_string'),
            'publicEmail.required' => __('settings.validation.public_email_required'),
            'publicEmail.email' => __('settings.validation.public_email_email'),
            'publicEmail.max' => __('settings.validation.public_email_max'),
            'addressAr.string' => __('settings.validation.address_ar_string'),
            'addressEn.string' => __('settings.validation.address_en_string'),
            'googleMapsUrl.string' => __('settings.validation.google_maps_url_string'),
            'googleMapsUrl.url' => __('settings.validation.google_maps_url_url'),
            'googleMapsUrl.max' => __('settings.validation.google_maps_url_max'),
            'phones.array' => __('settings.validation.phones_array'),
            'phones.max' => __('settings.validation.phones_max'),
            'phones.*.number.required_with' => __('settings.validation.phone_number_required'),
            'phones.*.number.string' => __('settings.validation.phone_number_string'),
            'phones.*.number.regex' => __('settings.validation.phone_number_regex'),
            'phones.*.hasWhats.required_with' => __('settings.validation.phone_has_whats_required'),
            'phones.*.hasWhats.integer' => __('settings.validation.phone_has_whats_integer'),
            'phones.*.hasWhats.in' => __('settings.validation.phone_has_whats_in'),
            'socialLinks.array' => __('settings.validation.social_links_array'),
            'socialLinks.max' => __('settings.validation.social_links_max'),
            'socialLinks.*.platform.required_with' => __('settings.validation.social_platform_required'),
            'socialLinks.*.platform.string' => __('settings.validation.social_platform_string'),
            'socialLinks.*.platform.in' => __('settings.validation.social_platform_in'),
            'socialLinks.*.url.required_with' => __('settings.validation.social_url_required'),
            'socialLinks.*.url.string' => __('settings.validation.social_url_string'),
            'socialLinks.*.url.url' => __('settings.validation.social_url_url'),
            'logo.file' => __('settings.validation.logo_file'),
            'logo.max' => __('settings.validation.logo_max'),
            'logo.mimes' => __('settings.validation.logo_mimes'),
            'footerLogo.file' => __('settings.validation.footer_logo_file'),
            'footerLogo.max' => __('settings.validation.footer_logo_max'),
            'footerLogo.mimes' => __('settings.validation.footer_logo_mimes'),
            'favicon.file' => __('settings.validation.favicon_file'),
            'favicon.max' => __('settings.validation.favicon_max'),
            'favicon.mimes' => __('settings.validation.favicon_mimes'),
            'clearPhones.boolean' => __('settings.validation.clear_phones_boolean'),
            'clearSocialLinks.boolean' => __('settings.validation.clear_social_links_boolean'),
            'removeLogo.boolean' => __('settings.validation.remove_logo_boolean'),
            'removeFooterLogo.boolean' => __('settings.validation.remove_footer_logo_boolean'),
            'removeFavicon.boolean' => __('settings.validation.remove_favicon_boolean'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->all() === [] && $this->allFiles() === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $this->ensureApprovedFileExtensions($validator);
                $this->ensureSafeSvgFiles($validator);

                $this->ensureUniquePhones($validator);
                $this->ensureSingleWhatsapp($validator);
                $this->ensureUniqueSocialPlatforms($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['clearPhones', 'clearSocialLinks', 'removeLogo', 'removeFooterLogo', 'removeFavicon'] as $field) {
            if (array_key_exists($field, $this->all())) {
                $payload[$field] = $this->normalizeBooleanFlag($this->input($field));
            }
        }

        foreach (['phones', 'socialLinks'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) === '[]') {
                $payload[$field] = [];
            }
        }

        foreach ([
            'addressAr',
            'addressEn',
            'googleMapsUrl',
        ] as $field) {
            if (array_key_exists($field, $this->all())) {
                $payload[$field] = $this->normalizeOptionalString($this->input($field));
            }
        }

        if (array_key_exists('publicEmail', $this->all())) {
            $email = $this->normalizeOptionalString($this->input('publicEmail'));
            $payload['publicEmail'] = $email === null ? null : mb_strtolower($email);
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

        if (($payload['clearPhones'] ?? false) === true) {
            $payload['phones'] = [];
        }

        if (($payload['clearSocialLinks'] ?? false) === true) {
            $payload['socialLinks'] = [];
        }

        foreach ([
            'removeLogo' => 'logo',
            'removeFooterLogo' => 'footerLogo',
            'removeFavicon' => 'favicon',
        ] as $flag => $field) {
            if (($payload[$flag] ?? false) === true && ! $this->hasFile($field)) {
                $payload[$field] = null;
            }
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

    private function normalizeBooleanFlag(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return match (mb_strtolower(trim($value))) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => $value,
        };
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
                $validator->errors()->add($field, __("settings.validation.{$this->fileTranslationPrefix($field)}_mimes"));
            }
        }
    }

    private function fileTranslationPrefix(string $field): string
    {
        return match ($field) {
            'footerLogo' => 'footer_logo',
            default => $field,
        };
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
