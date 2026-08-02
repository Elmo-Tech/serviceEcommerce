<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandingFileService
{
    public function __construct(
        private readonly SvgSafetyInspector $svgSafetyInspector,
    ) {}

    public function disk(): string
    {
        return (string) config('filesystems.default', 'public');
    }

    /**
     * @return array{path:string,disk:string}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        if (! $this->svgSafetyInspector->isSafe($file)) {
            throw new ApiBusinessException('settings.invalid_svg', 'UNSAFE_SVG', HttpStatusCode::UNPROCESSABLE_ENTITY, [
                'file' => [__('settings.invalid_svg')],
            ]);
        }

        $disk = $this->disk();
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            $directory,
            Str::uuid()->toString().($extension !== '' ? '.'.$extension : ''),
            $disk,
        );

        return [
            'disk' => $disk,
            'path' => $path,
        ];
    }

    public function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }
}
