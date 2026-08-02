<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\OrderItemAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderAttachmentStore
{
    private const MAX_PER_ITEM = 3;

    private const MAX_PER_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_CREATE_REQUEST_FILES = 30;

    private const MAX_CREATE_REQUEST_BYTES = 100 * 1024 * 1024;

    private const MAX_STANDALONE_UPLOAD_FILES = 3;

    private const MAX_STANDALONE_UPLOAD_BYTES = 30 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * @param  list<UploadedFile>  $files
     */
    public function ensureCreateRequestAggregateLimits(array $files): void
    {
        $this->ensureFileCountWithinLimit($files, self::MAX_CREATE_REQUEST_FILES);
        $this->ensureCombinedBytesWithinLimit($files, self::MAX_CREATE_REQUEST_BYTES);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function ensureStandaloneUploadLimits(int $existingCount, array $files): void
    {
        if (($existingCount + count($files)) > self::MAX_PER_ITEM) {
            throw new ApiBusinessException(
                'order_attachments.errors.limit_exceeded',
                'ATTACHMENT_LIMIT_REACHED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        $this->ensureFileCountWithinLimit($files, self::MAX_STANDALONE_UPLOAD_FILES);
        $this->ensureCombinedBytesWithinLimit($files, self::MAX_STANDALONE_UPLOAD_BYTES);
    }

    public function ensureFileIsAllowed(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new ApiBusinessException(
                'order_attachments.errors.upload_failed',
                'ATTACHMENT_NOT_ALLOWED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = (string) ($file->getMimeType() ?: '');
        $sizeBytes = (int) $file->getSize();

        if (! array_key_exists($extension, self::ALLOWED_MIME_TYPES)) {
            throw new ApiBusinessException(
                'order_attachments.errors.type_not_allowed',
                'ATTACHMENT_NOT_ALLOWED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if (self::ALLOWED_MIME_TYPES[$extension] !== $mimeType) {
            throw new ApiBusinessException(
                'order_attachments.errors.type_not_allowed',
                'ATTACHMENT_NOT_ALLOWED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($sizeBytes > self::MAX_PER_FILE_BYTES) {
            throw new ApiBusinessException(
                'order_attachments.errors.size_exceeded',
                'ATTACHMENT_TOO_LARGE',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @return array{
     *   disk:string,
     *   path:string,
     *   stored_name:string,
     *   original_name:string,
     *   mime_type:string,
     *   extension:string,
     *   size_bytes:int
     * }
     */
    public function storeUploadedFile(UploadedFile $file, string $orderNumber, int $orderItemId): array
    {
        $this->ensureFileIsAllowed($file);

        $disk = (string) config('filesystems.default', 'public');
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid()->toString().'.'.$extension;
        $directory = sprintf('orders/%s/items/%d/attachments', $orderNumber, $orderItemId);
        $path = $file->storeAs($directory, $storedName, $disk);

        return [
            'disk' => $disk,
            'path' => (string) $path,
            'stored_name' => $storedName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    public function deleteStoredFile(OrderItemAttachment|array $attachment): void
    {
        $disk = $attachment instanceof OrderItemAttachment ? $attachment->disk : (string) $attachment['disk'];
        $path = $attachment instanceof OrderItemAttachment ? $attachment->path : (string) $attachment['path'];

        if ($path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * @param  list<array{disk:string,path:string}>  $createdFiles
     */
    public function cleanupCreatedFiles(array $createdFiles): void
    {
        foreach ($createdFiles as $createdFile) {
            if (Storage::disk($createdFile['disk'])->exists($createdFile['path'])) {
                Storage::disk($createdFile['disk'])->delete($createdFile['path']);
            }
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function ensureFileCountWithinLimit(array $files, int $limit): void
    {
        if (count($files) > $limit) {
            throw new ApiBusinessException(
                'order_attachments.errors.limit_exceeded',
                'ATTACHMENT_LIMIT_REACHED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function ensureCombinedBytesWithinLimit(array $files, int $limitBytes): void
    {
        $combinedBytes = array_sum(array_map(
            static fn (UploadedFile $file): int => (int) $file->getSize(),
            $files,
        ));

        if ($combinedBytes > $limitBytes) {
            throw new ApiBusinessException(
                'order_attachments.errors.size_exceeded',
                'ATTACHMENT_TOO_LARGE',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }
}
