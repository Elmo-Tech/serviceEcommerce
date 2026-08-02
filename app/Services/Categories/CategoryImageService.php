<?php

declare(strict_types=1);

namespace App\Services\Categories;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryImageService
{
    public function disk(): string
    {
        $configuredDisk = config('filesystems.default');

        return is_string($configuredDisk) && $configuredDisk !== '' ? $configuredDisk : 'public';
    }

    /**
     * @return array{disk:string,path:string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = $this->disk();
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $path = $file->storeAs('categories/images', $filename, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
        ];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if (! is_string($disk) || $disk === '' || ! is_string($path) || $path === '') {
            return;
        }

        Storage::disk($disk)->delete($path);
    }
}
