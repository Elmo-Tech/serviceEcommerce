<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Models\OrderNumberSequence;
use App\Services\Orders\OrderNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('allocates sequential utc order numbers per business date', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $allocator = app(OrderNumberAllocator::class);

    $first = $allocator->allocate(now());
    $second = $allocator->allocate(now());

    expect($first)->toBe('ORD-20260801-0001')
        ->and($second)->toBe('ORD-20260801-0002')
        ->and(OrderNumberSequence::query()->where('business_date', '2026-08-01')->value('last_sequence'))->toBe(2);
});

it('resets the sequence for a new utc business date', function () {
    $allocator = app(OrderNumberAllocator::class);

    $firstMoment = Carbon::parse('2026-08-01 23:59:59', 'UTC');
    Carbon::setTestNow($firstMoment);
    expect($allocator->allocate($firstMoment))->toBe('ORD-20260801-0001');

    $secondMoment = Carbon::parse('2026-08-02 00:00:01', 'UTC');
    Carbon::setTestNow($secondMoment);
    expect($allocator->allocate($secondMoment))->toBe('ORD-20260802-0001');
});

it('rejects sequence exhaustion after 9999', function () {
    OrderNumberSequence::query()->create([
        'business_date' => '2026-08-01',
        'last_sequence' => 9999,
    ]);

    $allocator = app(OrderNumberAllocator::class);

    expect(fn () => $allocator->allocate(Carbon::parse('2026-08-01 12:00:00')))
        ->toThrow(ApiBusinessException::class, 'ORDER_NUMBER_SEQUENCE_EXHAUSTED');
});
