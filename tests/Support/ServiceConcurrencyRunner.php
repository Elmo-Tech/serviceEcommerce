<?php

declare(strict_types=1);

use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\DeleteSubcategoryAction;
use App\Actions\Services\CreateServiceAction;
use App\Actions\Services\RestoreServiceAction;
use App\Actions\Services\SetServiceMainMediaAction;
use App\Actions\Services\UpdateServiceAction;
use App\Actions\Services\UploadServiceMediaAction;
use App\Enums\Services\ServiceMediaType;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceMedia;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? null;

if (! is_string($mode) || $mode === '') {
    fwrite(STDERR, 'Missing concurrency mode.');

    exit(1);
}

try {
    switch ($mode) {
        case 'delete-category':
            $category = Category::query()->findOrFail((int) ($argv[2] ?? 0));
            $app->make(DeleteCategoryAction::class)->execute($category);

            echo json_encode([
                'status' => 'success',
                'categoryId' => $category->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'delete-subcategory':
            $rootCategory = Category::query()->findOrFail((int) ($argv[2] ?? 0));
            $subcategory = Category::query()->findOrFail((int) ($argv[3] ?? 0));
            $app->make(DeleteSubcategoryAction::class)->execute($rootCategory, $subcategory);

            echo json_encode([
                'status' => 'success',
                'subcategoryId' => $subcategory->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'create-service':
            $payload = json_decode($argv[2] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $service = $app->make(CreateServiceAction::class)->execute($payload);

            echo json_encode([
                'status' => 'success',
                'serviceId' => $service->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'update-service':
            $service = Service::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $payload = json_decode($argv[3] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $updatedService = $app->make(UpdateServiceAction::class)->execute($service, $payload);

            echo json_encode([
                'status' => 'success',
                'serviceId' => $updatedService->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'restore-service':
            $service = Service::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $restoredService = $app->make(RestoreServiceAction::class)->execute($service);

            echo json_encode([
                'status' => 'success',
                'serviceId' => $restoredService->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'set-main-media':
            $service = Service::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $media = ServiceMedia::query()->findOrFail((int) ($argv[3] ?? 0));
            $updatedMedia = $app->make(SetServiceMainMediaAction::class)->execute($service, $media);

            echo json_encode([
                'status' => 'success',
                'mediaId' => $updatedMedia->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'upload-video':
            $service = Service::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $path = (string) ($argv[3] ?? '');
            $originalName = (string) ($argv[4] ?? 'video.mp4');
            $mimeType = (string) ($argv[5] ?? 'video/mp4');

            $uploadedFile = new UploadedFile(
                $path,
                $originalName,
                $mimeType,
                null,
                true,
            );

            $media = $app->make(UploadServiceMediaAction::class)->execute($service, [[
                'file' => $uploadedFile,
                'type' => ServiceMediaType::VIDEO->value,
            ]]);

            echo json_encode([
                'status' => 'success',
                'mediaId' => $media->first()?->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);
    }

    fwrite(STDERR, 'Unknown concurrency mode.');

    exit(1);
} catch (ApiBusinessException $exception) {
    echo json_encode([
        'status' => 'business_error',
        'code' => $exception->machineCode(),
        'httpStatus' => $exception->status()->value,
    ], JSON_THROW_ON_ERROR);

    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable::class.': '.$throwable->getMessage());

    exit(1);
}
