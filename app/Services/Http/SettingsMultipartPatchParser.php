<?php

declare(strict_types=1);

namespace App\Services\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettingsMultipartPatchParser
{
    public const MAX_BODY_BYTES = 12 * 1024 * 1024;

    /** @var list<string> */
    private const SCALAR_FIELDS = [
        'siteNameAr', 'siteNameEn', 'siteDescriptionAr', 'siteDescriptionEn',
        'sloganAr', 'sloganEn', 'publicEmail', 'addressAr', 'addressEn',
        'googleMapsUrl', 'latitude', 'longitude', 'defaultSeoTitleAr',
        'defaultSeoTitleEn', 'defaultSeoDescriptionAr', 'defaultSeoDescriptionEn',
        'phones', 'socialLinks', 'logo', 'footerLogo', 'favicon',
    ];

    /** @var list<string> */
    private const FILE_FIELDS = ['logo', 'footerLogo', 'favicon'];

    /**
     * @return array{fields:array<string,mixed>,files:array<string,UploadedFile>,tempPaths:list<string>}
     */
    public function parse(string $contentType, string $body): array
    {
        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            $this->invalid('payload');
        }

        $boundary = $this->boundary($contentType);
        $delimiter = '--'.$boundary;

        if (! str_starts_with($body, $delimiter."\r\n")
            || ! str_ends_with($body, $delimiter."--\r\n") && ! str_ends_with($body, $delimiter.'--')) {
            $this->invalid('payload');
        }

        $segments = preg_split(
            '/\r\n'.preg_quote($delimiter, '/').'(?:\r\n|(?=--))/',
            substr($body, strlen($delimiter) + 2),
        );

        if (! is_array($segments)) {
            $this->invalid('payload');
        }

        $fields = [];
        $files = [];
        $tempPaths = [];
        try {
            foreach ($segments as $segment) {
                if ($segment === "--\r\n" || $segment === '--') {
                    continue;
                }

                $separator = strpos($segment, "\r\n\r\n");

                if ($separator === false) {
                    $this->invalid('payload');
                }

                $headers = $this->headers(substr($segment, 0, $separator));
                $value = substr($segment, $separator + 4);
                $disposition = $headers['content-disposition'] ?? null;

                if (! is_string($disposition)
                    || ! preg_match('/^form-data; name="([^"\\r\\n]+)"(?:; filename="([^"\\r\\n]*)")?$/D', $disposition, $matches)) {
                    $this->invalid('payload');
                }

                $name = $matches[1];
                $hasFilename = array_key_exists(2, $matches);

                if ($hasFilename) {
                    if (! in_array($name, self::FILE_FIELDS, true) || $matches[2] === '') {
                        continue;
                    }

                    $tempPath = tempnam(sys_get_temp_dir(), 'settings-');

                    if ($tempPath === false || file_put_contents($tempPath, $value, LOCK_EX) !== strlen($value)) {
                        $this->invalid($name);
                    }

                    $tempPaths[] = $tempPath;
                    $files[$name] = new UploadedFile(
                        $tempPath,
                        $matches[2],
                        $headers['content-type'] ?? 'application/octet-stream',
                        UPLOAD_ERR_OK,
                        true,
                    );

                    continue;
                }

                if (isset($headers['content-type']) || str_contains($value, "\0")) {
                    $this->invalid($name);
                }

                $this->assignField($fields, $name, $value);
            }

            if ($fields === [] && $files === []) {
                $this->invalid('payload');
            }

            return compact('fields', 'files', 'tempPaths');
        } catch (Throwable $throwable) {
            foreach ($tempPaths as $tempPath) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }

            throw $throwable;
        }
    }

    /** @param array<string,mixed> $fields */
    private function assignField(array &$fields, string $name, string $value): void
    {
        if (in_array($name, self::SCALAR_FIELDS, true)) {
            $fields[$name] = $value;

            return;
        }

        if (preg_match('/^(defaultSeoKeywordsAr|defaultSeoKeywordsEn)\[(\d+)\]$/D', $name, $matches)) {
            $fields[$matches[1]][(int) $matches[2]] = $value;

            return;
        }

        if (preg_match('/^(phones)\[(\d+)\]\[(number|hasWhats)\]$/D', $name, $matches)
            || preg_match('/^(socialLinks)\[(\d+)\]\[(platform|url)\]$/D', $name, $matches)) {
            $fields[$matches[1]][(int) $matches[2]][$matches[3]] = $value;

            return;
        }

        // Ignore extra Postman rows or unsupported keys instead of rejecting the whole payload.
    }

    private function boundary(string $contentType): string
    {
        if (! preg_match('/^multipart\/form-data\s*;\s*boundary=(?:"([A-Za-z0-9\'()+_,.\/:=?-]{1,70})"|([A-Za-z0-9\'()+_,.\/:=?-]{1,70}))\s*$/iD', $contentType, $matches)) {
            $this->invalid('payload');
        }

        return $matches[1] !== '' ? $matches[1] : $matches[2];
    }

    /** @return array<string,string> */
    private function headers(string $headerBlock): array
    {
        $headers = [];

        foreach (explode("\r\n", $headerBlock) as $line) {
            if (! preg_match('/^([A-Za-z0-9-]+): ([^\r\n]+)$/D', $line, $matches)) {
                $this->invalid('payload');
            }

            $key = strtolower($matches[1]);

            if (isset($headers[$key]) || ! in_array($key, ['content-disposition', 'content-type'], true)) {
                $this->invalid('payload');
            }

            $headers[$key] = $matches[2];
        }

        return $headers;
    }

    private function invalid(string $attribute): never
    {
        throw ValidationException::withMessages([
            $attribute => [__('validation.invalid_payload')],
        ]);
    }
}
