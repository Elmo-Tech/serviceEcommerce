<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Services\Orders\OrderAttachmentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

it('stores attachment files with generated metadata and cleans them up', function () {
    $service = app(OrderAttachmentStore::class);
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $stored = $service->storeUploadedFile($file, 'ORD-20260801-0001', 15);

    expect($stored['disk'])->toBe('public')
        ->and($stored['extension'])->toBe('pdf')
        ->and($stored['original_name'])->toBe('document.pdf')
        ->and($stored['path'])->toContain('orders/ORD-20260801-0001/items/15/attachments/')
        ->and(Storage::disk('public')->exists($stored['path']))->toBeTrue();

    $service->cleanupCreatedFiles([[
        'disk' => $stored['disk'],
        'path' => $stored['path'],
    ]]);

    expect(Storage::disk('public')->exists($stored['path']))->toBeFalse();
});

it('rejects unsupported types and oversized files', function () {
    $service = app(OrderAttachmentStore::class);

    $invalid = UploadedFile::fake()->create('archive.zip', 10, 'application/zip');
    $oversized = UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf');

    expect(fn () => $service->ensureFileIsAllowed($invalid))
        ->toThrow(ApiBusinessException::class, 'ATTACHMENT_NOT_ALLOWED');

    expect(fn () => $service->ensureFileIsAllowed($oversized))
        ->toThrow(ApiBusinessException::class, 'ATTACHMENT_TOO_LARGE');
});

it('enforces aggregate create and standalone upload limits', function () {
    $service = app(OrderAttachmentStore::class);

    $createFiles = [];

    for ($index = 0; $index < 31; $index++) {
        $createFiles[] = UploadedFile::fake()->create("file-{$index}.pdf", 10, 'application/pdf');
    }

    expect(fn () => $service->ensureCreateRequestAggregateLimits($createFiles))
        ->toThrow(ApiBusinessException::class, 'ATTACHMENT_LIMIT_REACHED');

    $standaloneFiles = [
        UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('third.pdf', 10, 'application/pdf'),
    ];

    expect(fn () => $service->ensureStandaloneUploadLimits(1, $standaloneFiles))
        ->toThrow(ApiBusinessException::class, 'ATTACHMENT_LIMIT_REACHED');
});
