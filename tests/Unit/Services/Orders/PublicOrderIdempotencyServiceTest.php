<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Services\Orders\PublicOrderIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('builds a stable fingerprint for equivalent customer phone formats and reordered value ids', function () {
    $service = app(PublicOrderIdempotencyService::class);

    $payloadA = [
        'customer' => [
            'name' => ' Mohamed Hassan ',
            'email' => 'MOHAMED@example.com',
            'phone' => '+20 100 123 4567',
        ],
        'items' => [[
            'serviceId' => 20,
            'selectedOptions' => [[
                'pricingOptionId' => 7,
                'valueIds' => [15, 14],
            ]],
        ]],
    ];

    $payloadB = [
        'items' => [[
            'selectedOptions' => [[
                'valueIds' => [14, 15],
                'pricingOptionId' => 7,
            ]],
            'serviceId' => 20,
        ]],
        'customer' => [
            'phone' => '01001234567',
            'email' => 'mohamed@example.com',
            'name' => 'Mohamed Hassan',
        ],
    ];

    expect($service->fingerprint($payloadA))->toBe($service->fingerprint($payloadB));
});

it('includes uploaded file content in the fingerprint instead of filename only', function () {
    $service = app(PublicOrderIdempotencyService::class);

    $firstFile = UploadedFile::fake()->createWithContent('first-name.pdf', 'same-content');
    $sameContentDifferentName = UploadedFile::fake()->createWithContent('renamed.pdf', 'same-content');
    $differentContent = UploadedFile::fake()->createWithContent('renamed.pdf', 'different-content');

    $basePayload = [
        'customer' => ['name' => 'Guest', 'phone' => '01012345678'],
        'items' => [[
            'serviceId' => 1,
            'attachments' => [],
        ]],
    ];

    $payloadA = $basePayload;
    $payloadA['items'][0]['attachments'] = [$firstFile];

    $payloadB = $basePayload;
    $payloadB['items'][0]['attachments'] = [$sameContentDifferentName];

    $payloadC = $basePayload;
    $payloadC['items'][0]['attachments'] = [$differentContent];

    expect($service->fingerprint($payloadA))->toBe($service->fingerprint($payloadB))
        ->and($service->fingerprint($payloadA))->not->toBe($service->fingerprint($payloadC));
});

it('reserves completes and replays the same idempotency key', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');

    $service = app(PublicOrderIdempotencyService::class);
    $key = (string) Str::uuid();
    $fingerprint = $service->fingerprint([
        'customer' => ['name' => 'Guest', 'phone' => '01012345678'],
        'items' => [['serviceId' => 1]],
    ]);

    $reserved = $service->reserve($key, $fingerprint, now());

    expect($reserved['isReplay'])->toBeFalse()
        ->and($reserved['reservation']->order_id)->toBeNull();

    $order = Order::factory()->create();
    $service->complete($reserved['reservation'], $order, now());

    $replayed = $service->reserve($key, $fingerprint, now());

    expect($replayed['isReplay'])->toBeTrue()
        ->and($replayed['replayOrder']?->is($order))->toBeTrue()
        ->and($replayed['reservation']->fresh()?->completed_at)->not->toBeNull();
});

it('rejects reusing the same key with a different fingerprint', function () {
    $service = app(PublicOrderIdempotencyService::class);
    $key = (string) Str::uuid();

    $first = $service->fingerprint([
        'customer' => ['name' => 'Guest', 'phone' => '01012345678'],
        'items' => [['serviceId' => 1]],
    ]);

    $second = $service->fingerprint([
        'customer' => ['name' => 'Guest', 'phone' => '01012345678'],
        'items' => [['serviceId' => 2]],
    ]);

    $reservation = $service->reserve($key, $first, now());
    $service->complete($reservation['reservation'], Order::factory()->create(), now());

    expect(fn () => $service->reserve($key, $second, now()))
        ->toThrow(ApiBusinessException::class, 'IDEMPOTENCY_KEY_REUSED');
});
