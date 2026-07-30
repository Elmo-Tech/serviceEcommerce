<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
});

afterEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

function startCategoryConcurrencyProcess(string ...$arguments): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/CategoryConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(20);
    $process->start();

    return $process;
}

function waitForCategoryConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Category concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

it('prevents a non-deleted child from surviving beneath a deleted root during delete-vs-create races', function () {
    $rootCategory = Category::factory()->root()->create();

    $payload = json_encode([
        'nameAr' => 'تنظيف متزامن',
        'nameEn' => 'Concurrent Cleaning',
        'descriptionAr' => 'وصف متزامن',
        'descriptionEn' => 'Concurrent description',
    ], JSON_THROW_ON_ERROR);

    $deleteProcess = startCategoryConcurrencyProcess('delete-category', (string) $rootCategory->getKey());
    $createProcess = startCategoryConcurrencyProcess('create-subcategory', (string) $rootCategory->getKey(), $payload);

    $results = [
        waitForCategoryConcurrencyProcess($deleteProcess),
        waitForCategoryConcurrencyProcess($createProcess),
    ];

    $rootCategory->refresh();
    $activeChildren = Category::query()
        ->where('parent_id', $rootCategory->getKey())
        ->whereNull('deleted_at')
        ->count();

    expect($activeChildren)->toBeGreaterThanOrEqual(0)
        ->and(! ($rootCategory->trashed() && $activeChildren > 0))->toBeTrue()
        ->and(collect($results)->pluck('status')->all())->toContain('success');

    $businessCodes = collect($results)
        ->where('status', 'business_error')
        ->pluck('code')
        ->all();

    expect($businessCodes)->each->toBeIn([
        'CATEGORY_HAS_SUBCATEGORIES',
        'CATEGORY_NOT_FOUND',
    ]);
});

it('prevents a restored child from surviving beneath a deleted parent during delete-vs-restore races', function () {
    $rootCategory = Category::factory()->root()->create();
    $subcategory = Category::factory()->subcategory($rootCategory)->create();
    $subcategory->delete();

    $deleteProcess = startCategoryConcurrencyProcess('delete-category', (string) $rootCategory->getKey());
    $restoreProcess = startCategoryConcurrencyProcess('restore-subcategory', (string) $rootCategory->getKey(), (string) $subcategory->getKey());

    $results = [
        waitForCategoryConcurrencyProcess($deleteProcess),
        waitForCategoryConcurrencyProcess($restoreProcess),
    ];

    $rootCategory->refresh();
    $subcategory->refresh();

    expect(! ($rootCategory->trashed() && ! $subcategory->trashed()))->toBeTrue();

    $businessCodes = collect($results)
        ->where('status', 'business_error')
        ->pluck('code')
        ->all();

    expect($businessCodes)->each->toBeIn([
        'CATEGORY_HAS_SUBCATEGORIES',
        'PARENT_CATEGORY_DELETED',
    ]);
});

it('keeps root-category reorders atomic under overlapping concurrent requests', function () {
    $categories = Category::factory()->count(4)->root()->create();

    $firstOrder = json_encode([
        $categories[3]->getKey(),
        $categories[2]->getKey(),
        $categories[1]->getKey(),
        $categories[0]->getKey(),
    ], JSON_THROW_ON_ERROR);

    $secondOrder = json_encode([
        $categories[1]->getKey(),
        $categories[0]->getKey(),
        $categories[3]->getKey(),
        $categories[2]->getKey(),
    ], JSON_THROW_ON_ERROR);

    $firstProcess = startCategoryConcurrencyProcess('reorder-categories', $firstOrder);
    $secondProcess = startCategoryConcurrencyProcess('reorder-categories', $secondOrder);

    waitForCategoryConcurrencyProcess($firstProcess);
    waitForCategoryConcurrencyProcess($secondProcess);

    $sortOrders = Category::query()
        ->roots()
        ->orderBy('id')
        ->pluck('sort_order')
        ->all();

    expect($sortOrders)->toHaveCount(4)
        ->and(collect($sortOrders)->sort()->values()->all())->toBe([0, 1, 2, 3]);
});

it('keeps scoped subcategory reorders atomic under overlapping concurrent requests for the same parent', function () {
    $rootCategory = Category::factory()->root()->create();
    $subcategories = Category::factory()->count(3)->subcategory($rootCategory)->create();

    $firstOrder = json_encode([
        $subcategories[2]->getKey(),
        $subcategories[1]->getKey(),
        $subcategories[0]->getKey(),
    ], JSON_THROW_ON_ERROR);

    $secondOrder = json_encode([
        $subcategories[1]->getKey(),
        $subcategories[0]->getKey(),
        $subcategories[2]->getKey(),
    ], JSON_THROW_ON_ERROR);

    $firstProcess = startCategoryConcurrencyProcess('reorder-subcategories', (string) $rootCategory->getKey(), $firstOrder);
    $secondProcess = startCategoryConcurrencyProcess('reorder-subcategories', (string) $rootCategory->getKey(), $secondOrder);

    waitForCategoryConcurrencyProcess($firstProcess);
    waitForCategoryConcurrencyProcess($secondProcess);

    $sortOrders = Category::query()
        ->where('parent_id', $rootCategory->getKey())
        ->orderBy('id')
        ->pluck('sort_order')
        ->all();

    expect($sortOrders)->toHaveCount(3)
        ->and(collect($sortOrders)->sort()->values()->all())->toBe([0, 1, 2]);
});

it('rejects cross-parent reorder races without corrupting the valid scoped reorder', function () {
    $rootCategory = Category::factory()->root()->create();
    $otherRootCategory = Category::factory()->root()->create();

    $firstSubcategory = Category::factory()->subcategory($rootCategory)->create();
    $secondSubcategory = Category::factory()->subcategory($rootCategory)->create();
    $outsideSubcategory = Category::factory()->subcategory($otherRootCategory)->create();

    $validOrder = json_encode([
        $secondSubcategory->getKey(),
        $firstSubcategory->getKey(),
    ], JSON_THROW_ON_ERROR);

    $invalidOrder = json_encode([
        $firstSubcategory->getKey(),
        $outsideSubcategory->getKey(),
    ], JSON_THROW_ON_ERROR);

    $validProcess = startCategoryConcurrencyProcess('reorder-subcategories', (string) $rootCategory->getKey(), $validOrder);
    $invalidProcess = startCategoryConcurrencyProcess('reorder-subcategories', (string) $rootCategory->getKey(), $invalidOrder);

    $results = [
        waitForCategoryConcurrencyProcess($validProcess),
        waitForCategoryConcurrencyProcess($invalidProcess),
    ];

    $rootSortOrders = Category::query()
        ->where('parent_id', $rootCategory->getKey())
        ->orderBy('id')
        ->pluck('sort_order')
        ->all();

    expect($rootSortOrders)->toHaveCount(2)
        ->and(collect($rootSortOrders)->sort()->values()->all())->toBe([0, 1])
        ->and($outsideSubcategory->fresh()?->sort_order)->not->toBe(1);

    expect(collect($results)->where('status', 'business_error')->pluck('code')->all())->toContain('SUBCATEGORY_NOT_FOUND');
});
