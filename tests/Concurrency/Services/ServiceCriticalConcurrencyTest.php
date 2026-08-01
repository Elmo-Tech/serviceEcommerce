<?php

declare(strict_types=1);

use App\Enums\Services\ServiceMediaType;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\ServiceSlugReservation;
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

function startServiceConcurrencyProcess(string ...$arguments): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/ServiceConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(20);
    $process->start();

    return $process;
}

function waitForServiceConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Service concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

function serviceCreatePayload(array $overrides = []): array
{
    $suffix = (string) random_int(100000, 999999);

    return array_replace([
        'nameAr' => 'خدمة متزامنة '.$suffix,
        'nameEn' => 'Concurrent Service '.$suffix,
        'shortDescriptionAr' => 'وصف مختصر '.$suffix,
        'shortDescriptionEn' => 'Short description '.$suffix,
        'descriptionAr' => 'وصف كامل '.$suffix,
        'descriptionEn' => 'Full description '.$suffix,
        'slugAr' => 'خدمة-متزامنة-'.$suffix,
        'slugEn' => 'concurrent-service-'.$suffix,
        'priceType' => 0,
        'basePrice' => '250.00',
        'isActive' => true,
        'isAvailable' => true,
    ], $overrides);
}

function makeConcurrencyVideoFile(string $name): string
{
    $path = storage_path('app/testing/'.$name);
    $directory = dirname($path);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, random_bytes(2048));

    return $path;
}

it('prevents a direct service from surviving beneath a deleted root during delete-vs-create races', function () {
    $rootCategory = Category::factory()->root()->create();

    $payload = json_encode(serviceCreatePayload([
        'categoryId' => $rootCategory->getKey(),
    ]), JSON_THROW_ON_ERROR);

    $deleteProcess = startServiceConcurrencyProcess('delete-category', (string) $rootCategory->getKey());
    $createProcess = startServiceConcurrencyProcess('create-service', $payload);

    $results = [
        waitForServiceConcurrencyProcess($deleteProcess),
        waitForServiceConcurrencyProcess($createProcess),
    ];

    $rootCategory->refresh();
    $activeServices = Service::query()
        ->where('category_id', $rootCategory->getKey())
        ->whereNull('deleted_at')
        ->count();

    expect(! ($rootCategory->trashed() && $activeServices > 0))->toBeTrue();

    expect(collect($results)->where('status', 'business_error')->pluck('code')->all())
        ->each
        ->toBeIn(['CATEGORY_HAS_SERVICES', 'CATEGORY_NOT_AVAILABLE']);
});

it('prevents a subcategory service from surviving beneath a deleted subcategory during delete-vs-create races', function () {
    $rootCategory = Category::factory()->root()->create();
    $subcategory = Category::factory()->subcategory($rootCategory)->create();

    $payload = json_encode(serviceCreatePayload([
        'categoryId' => $rootCategory->getKey(),
        'subcategoryId' => $subcategory->getKey(),
    ]), JSON_THROW_ON_ERROR);

    $deleteProcess = startServiceConcurrencyProcess('delete-subcategory', (string) $rootCategory->getKey(), (string) $subcategory->getKey());
    $createProcess = startServiceConcurrencyProcess('create-service', $payload);

    $results = [
        waitForServiceConcurrencyProcess($deleteProcess),
        waitForServiceConcurrencyProcess($createProcess),
    ];

    $subcategory->refresh();
    $activeServices = Service::query()
        ->where('subcategory_id', $subcategory->getKey())
        ->whereNull('deleted_at')
        ->count();

    expect(! ($subcategory->trashed() && $activeServices > 0))->toBeTrue();

    expect(collect($results)->where('status', 'business_error')->pluck('code')->all())
        ->each
        ->toBeIn(['SUBCATEGORY_HAS_SERVICES', 'SUBCATEGORY_NOT_AVAILABLE']);
});

it('prevents moving a service under a root category that is deleted concurrently', function () {
    $targetRootCategory = Category::factory()->root()->create();
    $service = Service::factory()->create();

    $payload = json_encode([
        'categoryId' => $targetRootCategory->getKey(),
    ], JSON_THROW_ON_ERROR);

    $deleteProcess = startServiceConcurrencyProcess('delete-category', (string) $targetRootCategory->getKey());
    $moveProcess = startServiceConcurrencyProcess('update-service', (string) $service->getKey(), $payload);

    $results = [
        waitForServiceConcurrencyProcess($deleteProcess),
        waitForServiceConcurrencyProcess($moveProcess),
    ];

    $targetRootCategory->refresh();
    $service->refresh();

    expect(! ($targetRootCategory->trashed() && $service->category_id === $targetRootCategory->getKey() && ! $service->trashed()))
        ->toBeTrue();

    expect(collect($results)->where('status', 'business_error')->pluck('code')->all())
        ->each
        ->toBeIn(['CATEGORY_HAS_SERVICES', 'CATEGORY_NOT_AVAILABLE']);
});

it('prevents moving a service under a subcategory that is deleted concurrently', function () {
    $rootCategory = Category::factory()->root()->create();
    $targetSubcategory = Category::factory()->subcategory($rootCategory)->create();
    $service = Service::factory()->categorized($rootCategory)->create();

    $payload = json_encode([
        'categoryId' => $rootCategory->getKey(),
        'subcategoryId' => $targetSubcategory->getKey(),
    ], JSON_THROW_ON_ERROR);

    $deleteProcess = startServiceConcurrencyProcess('delete-subcategory', (string) $rootCategory->getKey(), (string) $targetSubcategory->getKey());
    $moveProcess = startServiceConcurrencyProcess('update-service', (string) $service->getKey(), $payload);

    $results = [
        waitForServiceConcurrencyProcess($deleteProcess),
        waitForServiceConcurrencyProcess($moveProcess),
    ];

    $targetSubcategory->refresh();
    $service->refresh();

    expect(! ($targetSubcategory->trashed() && $service->subcategory_id === $targetSubcategory->getKey() && ! $service->trashed()))
        ->toBeTrue();

    expect(collect($results)->where('status', 'business_error')->pluck('code')->all())
        ->each
        ->toBeIn(['SUBCATEGORY_HAS_SERVICES', 'SUBCATEGORY_NOT_AVAILABLE']);
});

it('prevents a restored service from surviving beneath a deleted hierarchy during restore-vs-delete races', function () {
    $rootCategory = Category::factory()->root()->create();
    $service = Service::factory()->categorized($rootCategory)->create();
    $service->delete();

    $deleteProcess = startServiceConcurrencyProcess('delete-category', (string) $rootCategory->getKey());
    $restoreProcess = startServiceConcurrencyProcess('restore-service', (string) $service->getKey());

    $results = [
        waitForServiceConcurrencyProcess($deleteProcess),
        waitForServiceConcurrencyProcess($restoreProcess),
    ];

    $invalidActiveReferences = Service::query()
        ->join('categories', 'categories.id', '=', 'services.category_id')
        ->where('services.id', $service->getKey())
        ->whereNull('services.deleted_at')
        ->whereNotNull('categories.deleted_at')
        ->count();

    expect($invalidActiveReferences)->toBe(0);

    expect(collect($results)->where('status', 'business_error')->pluck('code')->all())
        ->each
        ->toBeIn(['CATEGORY_HAS_SERVICES']);
});

it('keeps cross-locale slug reservations unique under concurrent claims', function () {
    $slugAr = 'slug-race-ar';
    $slugEn = 'slug-race-en';

    $firstPayload = json_encode(serviceCreatePayload([
        'slugAr' => $slugAr,
        'slugEn' => $slugEn,
    ]), JSON_THROW_ON_ERROR);

    $secondPayload = json_encode(serviceCreatePayload([
        'slugAr' => $slugEn,
        'slugEn' => $slugAr,
    ]), JSON_THROW_ON_ERROR);

    $firstProcess = startServiceConcurrencyProcess('create-service', $firstPayload);
    $secondProcess = startServiceConcurrencyProcess('create-service', $secondPayload);

    $results = [
        waitForServiceConcurrencyProcess($firstProcess),
        waitForServiceConcurrencyProcess($secondProcess),
    ];

    $reservations = ServiceSlugReservation::query()
        ->whereIn('slug', [$slugAr, $slugEn])
        ->get();

    expect($reservations)->toHaveCount(2)
        ->and($reservations->pluck('service_id')->unique()->count())->toBe(1)
        ->and(collect($results)->pluck('status')->all())->toContain('success')
        ->and(collect($results)->where('status', 'business_error')->pluck('code')->all())->toContain('VALIDATION_ERROR');
});

it('keeps main-image selection singular under overlapping set-main requests', function () {
    $service = Service::factory()->create();
    $firstImage = ServiceMedia::factory()->for($service)->image(true)->create();
    $secondImage = ServiceMedia::factory()->for($service)->image()->create();
    $thirdImage = ServiceMedia::factory()->for($service)->image()->create();

    $firstProcess = startServiceConcurrencyProcess('set-main-media', (string) $service->getKey(), (string) $secondImage->getKey());
    $secondProcess = startServiceConcurrencyProcess('set-main-media', (string) $service->getKey(), (string) $thirdImage->getKey());

    waitForServiceConcurrencyProcess($firstProcess);
    waitForServiceConcurrencyProcess($secondProcess);

    $mainImages = ServiceMedia::query()
        ->where('service_id', $service->getKey())
        ->where('type', ServiceMediaType::IMAGE)
        ->where('is_main', true)
        ->pluck('id')
        ->all();

    expect($firstImage->fresh()?->is_main)->toBeFalse()
        ->and($mainImages)->toHaveCount(1)
        ->and($mainImages[0])->toBeIn([$secondImage->getKey(), $thirdImage->getKey()]);
});

it('keeps video uploads unique under overlapping concurrent requests', function () {
    $service = Service::factory()->create();
    $firstVideoPath = makeConcurrencyVideoFile('service-concurrency-video-1.mp4');
    $secondVideoPath = makeConcurrencyVideoFile('service-concurrency-video-2.mp4');

    $firstProcess = startServiceConcurrencyProcess(
        'upload-video',
        (string) $service->getKey(),
        $firstVideoPath,
        'first-video.mp4',
        'video/mp4',
    );
    $secondProcess = startServiceConcurrencyProcess(
        'upload-video',
        (string) $service->getKey(),
        $secondVideoPath,
        'second-video.mp4',
        'video/mp4',
    );

    $results = [
        waitForServiceConcurrencyProcess($firstProcess),
        waitForServiceConcurrencyProcess($secondProcess),
    ];

    $videoCount = ServiceMedia::query()
        ->where('service_id', $service->getKey())
        ->where('type', ServiceMediaType::VIDEO)
        ->count();

    expect($videoCount)->toBe(1)
        ->and(collect($results)->pluck('status')->all())->toContain('success')
        ->and(collect($results)->where('status', 'business_error')->pluck('code')->all())->toContain('SERVICE_VIDEO_LIMIT_REACHED');
});
