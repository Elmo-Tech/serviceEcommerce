<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\Settings\SocialPlatform;
use App\Models\Setting;
use App\Services\Settings\BrandingFileService;
use App\Services\Settings\SettingsResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateSettingsAction
{
    public function __construct(
        private readonly SettingsResolver $settingsResolver,
        private readonly BrandingFileService $brandingFileService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): Setting
    {
        $storedFiles = [];
        $cleanupPaths = [];

        foreach ([
            'logo' => 'settings/logo',
            'footerLogo' => 'settings/footer-logo',
            'favicon' => 'settings/favicon',
        ] as $field => $directory) {
            $file = $payload[$field] ?? null;

            if ($file instanceof UploadedFile) {
                $storedFiles[$field] = $this->brandingFileService->store($file, $directory);
            }
        }

        try {
            $setting = DB::transaction(function () use ($payload, $storedFiles, &$cleanupPaths): Setting {
                $setting = $this->settingsResolver->resolveForAdmin(lockForUpdate: true);

                $fieldMap = [
                    'siteNameAr' => 'site_name_ar',
                    'siteNameEn' => 'site_name_en',
                    'siteDescriptionAr' => 'site_description_ar',
                    'siteDescriptionEn' => 'site_description_en',
                    'sloganAr' => 'slogan_ar',
                    'sloganEn' => 'slogan_en',
                    'publicEmail' => 'public_email',
                    'addressAr' => 'address_ar',
                    'addressEn' => 'address_en',
                    'googleMapsUrl' => 'google_maps_url',
                    'latitude' => 'latitude',
                    'longitude' => 'longitude',
                    'defaultSeoTitleAr' => 'default_seo_title_ar',
                    'defaultSeoTitleEn' => 'default_seo_title_en',
                    'defaultSeoDescriptionAr' => 'default_seo_description_ar',
                    'defaultSeoDescriptionEn' => 'default_seo_description_en',
                    'defaultSeoKeywordsAr' => 'default_seo_keywords_ar',
                    'defaultSeoKeywordsEn' => 'default_seo_keywords_en',
                ];

                foreach ($fieldMap as $input => $column) {
                    if (array_key_exists($input, $payload)) {
                        $setting->{$column} = $payload[$input];
                    }
                }

                foreach ([
                    'logo' => ['column' => 'logo_path', 'flag' => 'removeLogo'],
                    'footerLogo' => ['column' => 'footer_logo_path', 'flag' => 'removeFooterLogo'],
                    'favicon' => ['column' => 'favicon_path', 'flag' => 'removeFavicon'],
                ] as $field => $config) {
                    if (isset($storedFiles[$field])) {
                        $oldPath = $setting->{$config['column']};
                        $setting->{$config['column']} = $storedFiles[$field]['path'];
                        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $storedFiles[$field]['path']) {
                            $cleanupPaths[] = $oldPath;
                        }
                    } elseif ((int) ($payload[$config['flag']] ?? 0) === 1) {
                        $oldPath = $setting->{$config['column']};
                        $setting->{$config['column']} = null;
                        if (is_string($oldPath) && $oldPath !== '') {
                            $cleanupPaths[] = $oldPath;
                        }
                    }
                }

                $setting->save();

                if (array_key_exists('phones', $payload)) {
                    $setting->phones()->delete();

                    foreach ((array) $payload['phones'] as $index => $phone) {
                        $setting->phones()->create([
                            'number' => $phone['number'],
                            'has_whats' => (int) $phone['hasWhats'],
                            'position' => $index,
                        ]);
                    }
                } elseif ((int) ($payload['clearPhones'] ?? 0) === 1) {
                    $setting->phones()->delete();
                }

                if (array_key_exists('socialLinks', $payload)) {
                    $setting->socialLinks()->delete();

                    foreach ((array) $payload['socialLinks'] as $index => $link) {
                        $platform = SocialPlatform::fromKey((string) $link['platform']);

                        $setting->socialLinks()->create([
                            'platform' => $platform,
                            'url' => $link['url'],
                            'position' => $index,
                        ]);
                    }
                } elseif ((int) ($payload['clearSocialLinks'] ?? 0) === 1) {
                    $setting->socialLinks()->delete();
                }

                return $setting->fresh(['phones', 'socialLinks']);
            });
        } catch (Throwable $throwable) {
            foreach ($storedFiles as $storedFile) {
                $this->brandingFileService->delete($storedFile['path']);
            }

            throw $throwable;
        }

        foreach ($cleanupPaths as $path) {
            try {
                $this->brandingFileService->delete($path);
            } catch (Throwable $throwable) {
                Log::warning('settings.branding_cleanup_failed', [
                    'path' => $path,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return $setting;
    }
}
