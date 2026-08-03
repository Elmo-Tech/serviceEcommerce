<?php

declare(strict_types=1);

namespace App\Services\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StrictMultipartPatchParser
{
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    public const MAX_OVERHEAD_BYTES = 64 * 1024;

    /** @var list<string> */
    private const SCALAR_FIELDS = [
        'titleAr',
        'titleEn',
        'descriptionAr',
        'descriptionEn',
        'isActive',
        'position',
    ];

    /**
     * @param  list<string>|null  $scalarFields
     * @return array{fields:array<string,string>,file:UploadedFile|null,tempPath:string|null}
     */
    public function parse(
        string $contentType,
        string $body,
        ?array $scalarFields = null,
        string $temporaryFilePrefix = 'hero-slide-',
    ): array {
        $allowedScalarFields = $scalarFields ?? self::SCALAR_FIELDS;

        if (strlen($body) > self::MAX_FILE_BYTES + self::MAX_OVERHEAD_BYTES) {
            $this->invalid('image');
        }

        $boundary = $this->boundary($contentType);
        $delimiter = '--'.$boundary;

        if ($body === '' || ! str_ends_with($body, $delimiter."--\r\n") && ! str_ends_with($body, $delimiter.'--')) {
            $this->invalid('payload');
        }

        if (! str_starts_with($body, $delimiter."\r\n")) {
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
        $file = null;
        $tempPath = null;

        try {
            foreach ($segments as $segment) {
                if ($segment === "--\r\n" || $segment === '--') {
                    continue;
                }

                $separator = strpos($segment, "\r\n\r\n");

                if ($separator === false) {
                    $this->invalid('payload');
                }

                $headerBlock = substr($segment, 0, $separator);
                $value = substr($segment, $separator + 4);
                $headers = $this->headers($headerBlock);
                $disposition = $headers['content-disposition'] ?? null;

                if (! is_string($disposition) || ! preg_match('/^form-data; name="([A-Za-z][A-Za-z0-9]*)"(?:; filename="([^"\\r\\n]*)")?$/D', $disposition, $matches)) {
                    $this->invalid('payload');
                }

                $name = $matches[1];
                $hasFilename = array_key_exists(2, $matches);

                if ($hasFilename) {
                    if ($name !== 'image' || $file !== null || $matches[2] === '') {
                        $this->invalid('image');
                    }

                    if (strlen($value) > self::MAX_FILE_BYTES) {
                        $this->invalid('image');
                    }

                    $mime = $headers['content-type'] ?? 'application/octet-stream';
                    $tempPath = tempnam(sys_get_temp_dir(), $temporaryFilePrefix);

                    if ($tempPath === false || file_put_contents($tempPath, $value, LOCK_EX) !== strlen($value)) {
                        $this->invalid('image');
                    }

                    $file = new UploadedFile($tempPath, $matches[2], $mime, UPLOAD_ERR_OK, true);

                    continue;
                }

                if (! in_array($name, $allowedScalarFields, true) || array_key_exists($name, $fields)) {
                    $this->invalid($name === '' ? 'payload' : $name);
                }

                if (isset($headers['content-type']) || str_contains($value, "\0")) {
                    $this->invalid($name);
                }

                $fields[$name] = $value;
            }

            if ($fields === [] && $file === null) {
                $this->invalid('payload');
            }

            return ['fields' => $fields, 'file' => $file, 'tempPath' => $tempPath];
        } catch (\Throwable $throwable) {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }

            throw $throwable;
        }
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
        if (str_contains($headerBlock, "\r\r") || str_contains($headerBlock, "\n\n")) {
            $this->invalid('payload');
        }

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
