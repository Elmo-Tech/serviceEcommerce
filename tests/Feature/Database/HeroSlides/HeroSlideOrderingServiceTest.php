<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Models\HeroSlide;
use App\Services\HeroSlides\HeroSlideOrderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('mutates empty and populated ordered sets and releases the advisory lock', function () {
    $service = app(HeroSlideOrderingService::class);

    expect($service->mutate(fn ($slides): int => $slides->count()))->toBe(0);

    foreach (range(1, 3) as $position) {
        HeroSlide::factory()->atPosition($position)->create();
    }

    $service->mutate(function ($slides) use ($service): void {
        $ids = array_reverse(array_map('intval', $slides->modelKeys()));
        $service->park($slides);
        $service->assign($ids);
    });

    expect(HeroSlide::query()->ordered()->pluck('position')->all())->toBe([1, 2, 3]);

    $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', ['service-commerce:hero-slides:ordering']);
    expect((int) $lock->acquired)->toBe(1);
    DB::selectOne('SELECT RELEASE_LOCK(?)', ['service-commerce:hero-slides:ordering']);
});

it('rejects a corrupt non-contiguous sequence before invoking the mutation', function () {
    HeroSlide::factory()->atPosition(2)->create();

    expect(fn () => app(HeroSlideOrderingService::class)->mutate(fn (): null => null))
        ->toThrow(RuntimeException::class, 'ordering invariant');
});

it('uses unique-index-safe temporary positions and refuses duplicate targets', function () {
    $service = app(HeroSlideOrderingService::class);
    foreach (range(1, 3) as $position) {
        HeroSlide::factory()->atPosition($position)->create();
    }

    $service->mutate(function ($slides) use ($service): void {
        $service->park($slides);
        expect(HeroSlide::query()->orderBy('position')->pluck('position')->all())->toBe([101, 102, 103]);
        expect(fn () => $service->assign([1, 1, 2]))->toThrow(RuntimeException::class);
    });
});

it('returns the stable unavailable outcome when another connection holds the ordering lock', function () {
    $configuration = config('database.connections.mysql');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $configuration['host'], $configuration['port'], $configuration['database']),
        $configuration['username'],
        $configuration['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $statement = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $statement->execute(['service-commerce:hero-slides:ordering']);
    expect((int) $statement->fetchColumn())->toBe(1);

    try {
        app(HeroSlideOrderingService::class)->mutate(fn (): null => null);
        $this->fail('Expected ordering lock timeout.');
    } catch (ApiBusinessException $exception) {
        expect($exception->machineCode())->toBe('HERO_SLIDE_ORDERING_UNAVAILABLE');
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute(['service-commerce:hero-slides:ordering']);
    }
});
