<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Models\HeroSlide;
use App\Services\HeroSlides\HeroSlideMutationRetrier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    config()->set('filesystems.default', 'public');
    Storage::disk('public')->deleteDirectory('hero-slides');
});

afterEach(function (): void {
    Storage::disk('public')->deleteDirectory('hero-slides');
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

function startHeroConcurrency(string ...$arguments): Process
{
    $process = new Process([PHP_BINARY, base_path('tests/Support/HeroSlideConcurrencyRunner.php'), ...$arguments]);
    $process->setTimeout(25);
    $process->start();

    return $process;
}

function finishHeroConcurrency(Process $process): array
{
    $process->wait();
    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Hero concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

function assertHeroOrderInvariant(): void
{
    $positions = HeroSlide::query()->ordered()->pluck('position')->map(fn ($value): int => (int) $value)->all();
    expect($positions)->toBe($positions === [] ? [] : range(1, count($positions)))
        ->and(HeroSlide::query()->count())->toBeLessThanOrEqual(10);
}

it('serializes concurrent creates against an empty set', function () {
    $first = startHeroConcurrency('create');
    $second = startHeroConcurrency('create');
    $results = [finishHeroConcurrency($first), finishHeroConcurrency($second)];

    expect(collect($results)->pluck('status')->all())->toBe(['success', 'success'])
        ->and(HeroSlide::query()->count())->toBe(2);
    assertHeroOrderInvariant();
});

it('allows only one concurrent creator to take the final slot and cleans the loser file', function () {
    foreach (range(1, 9) as $position) {
        HeroSlide::factory()->atPosition($position)->create();
    }

    $first = startHeroConcurrency('create');
    $second = startHeroConcurrency('create');
    $results = collect([finishHeroConcurrency($first), finishHeroConcurrency($second)]);

    expect($results->where('status', 'success'))->toHaveCount(1)
        ->and($results->where('status', 'business_error')->pluck('code')->all())->toBe(['VALIDATION_ERROR'])
        ->and(HeroSlide::query()->count())->toBe(10)
        ->and(Storage::disk('public')->allFiles('hero-slides'))->toHaveCount(1);
    assertHeroOrderInvariant();
});

it('keeps gapless order under competing moves and create-delete races', function () {
    $slides = collect(range(1, 5))->map(fn (int $position) => HeroSlide::factory()->atPosition($position)->create());

    $up = startHeroConcurrency('move', (string) $slides[4]->getKey(), '1');
    $down = startHeroConcurrency('move', (string) $slides[0]->getKey(), '5');
    finishHeroConcurrency($up);
    finishHeroConcurrency($down);
    assertHeroOrderInvariant();

    $create = startHeroConcurrency('create', '2');
    $delete = startHeroConcurrency('delete', (string) $slides[2]->getKey());
    finishHeroConcurrency($create);
    finishHeroConcurrency($delete);
    assertHeroOrderInvariant();
});

it('keeps committed file state leak-free during replacement-delete races', function () {
    Storage::disk('public')->put('hero-slides/old.png', 'old');
    $target = HeroSlide::factory()->atPosition(1)->create(['image_path' => 'hero-slides/old.png']);
    HeroSlide::factory()->atPosition(2)->create();

    $replace = startHeroConcurrency('replace', (string) $target->getKey());
    $delete = startHeroConcurrency('delete', (string) $target->getKey());
    $results = collect([finishHeroConcurrency($replace), finishHeroConcurrency($delete)]);

    expect(HeroSlide::query()->find($target->getKey()))->toBeNull()
        ->and($results->pluck('status')->all())->each->toBeIn(['success', 'business_error'])
        ->and(Storage::disk('public')->allFiles('hero-slides'))->toBe([]);
    assertHeroOrderInvariant();
});

it('retries only deadlock serialization failures and returns a safe exhausted outcome', function () {
    $deadlock = function (): QueryException {
        $previous = new PDOException('Deadlock found', 40001);
        $previous->errorInfo = ['40001', 1213, 'Deadlock found'];

        return new QueryException('mysql', 'UPDATE hero_slides', [], $previous);
    };

    $attempts = 0;
    $result = app(HeroSlideMutationRetrier::class)->execute(function () use (&$attempts, $deadlock): string {
        $attempts++;
        if ($attempts < 3) {
            throw $deadlock();
        }

        return 'committed';
    });
    expect($result)->toBe('committed')->and($attempts)->toBe(3);

    $attempts = 0;
    try {
        app(HeroSlideMutationRetrier::class)->execute(function () use (&$attempts, $deadlock): never {
            $attempts++;
            throw $deadlock();
        });
        $this->fail('Expected exhausted mutation failure.');
    } catch (ApiBusinessException $exception) {
        expect($exception->machineCode())->toBe('HERO_SLIDE_MUTATION_FAILED')->and($attempts)->toBe(3);
    }
});
