<?php

declare(strict_types=1);

use App\Actions\Categories\CreateSubcategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\ReorderCategoriesAction;
use App\Actions\Categories\ReorderSubcategoriesAction;
use App\Actions\Categories\RestoreSubcategoryAction;
use App\Exceptions\ApiBusinessException;
use App\Models\Category;
use Illuminate\Contracts\Console\Kernel;

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

        case 'create-subcategory':
            $category = Category::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $payload = json_decode($argv[3] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $subcategory = $app->make(CreateSubcategoryAction::class)->execute($category, $payload);

            echo json_encode([
                'status' => 'success',
                'subcategoryId' => $subcategory->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'restore-subcategory':
            $category = Category::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $subcategory = Category::withTrashed()->findOrFail((int) ($argv[3] ?? 0));
            $restoredSubcategory = $app->make(RestoreSubcategoryAction::class)->execute($category, $subcategory);

            echo json_encode([
                'status' => 'success',
                'subcategoryId' => $restoredSubcategory->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'reorder-categories':
            $orderedIds = json_decode($argv[2] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            $app->make(ReorderCategoriesAction::class)->execute(array_map('intval', $orderedIds));

            echo json_encode([
                'status' => 'success',
                'orderedIds' => array_map('intval', $orderedIds),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'reorder-subcategories':
            $category = Category::withTrashed()->findOrFail((int) ($argv[2] ?? 0));
            $orderedIds = json_decode($argv[3] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            $app->make(ReorderSubcategoriesAction::class)->execute($category, array_map('intval', $orderedIds));

            echo json_encode([
                'status' => 'success',
                'orderedIds' => array_map('intval', $orderedIds),
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
