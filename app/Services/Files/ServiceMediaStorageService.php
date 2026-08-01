<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\ServiceMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceMediaStorageService
{
    /**
     * @return array{
     *     disk:string,
     *     path:string,
     *     stored_name:string,
     *     original_name:string,
     *     mime_type:string,
     *     extension:string,
     *     size_bytes:int
     * }
     */
    public function storeUploadedFile(UploadedFile $file, string $directory = 'services'): array
    {
        $disk = (string) config('filesystems.default', 'public');
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $storedName, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'stored_name' => $storedName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    public function deleteStoredFile(ServiceMedia|array $media): void
    {
        $disk = $media instanceof ServiceMedia ? $media->disk : (string) $media['disk'];
        $path = $media instanceof ServiceMedia ? $media->path : (string) $media['path'];

        if ($path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }
}
