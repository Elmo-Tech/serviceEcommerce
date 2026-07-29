<?php

declare(strict_types=1);

use App\Models\Customer;
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

function startCustomerConcurrencyProcess(string ...$arguments): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/CustomerConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(20);
    $process->start();

    return $process;
}

function waitForCustomerConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

it('keeps one canonical customer when the same normalized phone is created concurrently', function () {
    $payload = json_encode([
        'name' => 'Concurrent Customer',
        'phone' => '01001234567',
        'phoneCountryCode' => 'EG',
    ], JSON_THROW_ON_ERROR);

    $firstProcess = startCustomerConcurrencyProcess('create-customer', $payload);
    $secondProcess = startCustomerConcurrencyProcess('create-customer', $payload);

    $results = [
        waitForCustomerConcurrencyProcess($firstProcess),
        waitForCustomerConcurrencyProcess($secondProcess),
    ];

    $customer = Customer::query()->sole();

    expect($customer->phone_normalized)->toBe('+201001234567')
        ->and(collect($results)->where('status', 'success'))->toHaveCount(1)
        ->and(collect($results)->where('status', 'business_error'))->toHaveCount(1)
        ->and(collect($results)->where('status', 'business_error')->pluck('code')->all())->toBe([
            'CUSTOMER_PHONE_ALREADY_EXISTS',
        ]);
});

it('leaves exactly one active default address after concurrent default changes', function () {
    $customer = Customer::factory()->create([
        'phone' => '+20 100 333 4444',
        'phone_normalized' => '+201003334444',
    ]);

    $customer->addresses()->create([
        'label' => 'Original Default',
        'phone' => '+20 100 333 4444',
        'phone_normalized' => '+201003334444',
        'country_code' => 'EG',
        'city' => 'Cairo',
        'area' => 'Nasr City',
        'street' => 'Street 1',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|cairo|nasr city|street 1'),
        'is_default' => true,
    ]);

    $firstCandidate = $customer->addresses()->create([
        'label' => 'First Candidate',
        'phone' => '+20 100 333 4444',
        'phone_normalized' => '+201003334444',
        'country_code' => 'EG',
        'city' => 'Giza',
        'area' => null,
        'street' => 'Street 2',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|giza||street 2'),
        'is_default' => false,
    ]);

    $secondCandidate = $customer->addresses()->create([
        'label' => 'Second Candidate',
        'phone' => '+20 100 333 4444',
        'phone_normalized' => '+201003334444',
        'country_code' => 'EG',
        'city' => 'Alexandria',
        'area' => null,
        'street' => 'Street 3',
        'notes' => null,
        'address_hash' => hash('sha256', 'eg|alexandria||street 3'),
        'is_default' => false,
    ]);

    $firstProcess = startCustomerConcurrencyProcess(
        'set-default-address',
        (string) $customer->getKey(),
        (string) $firstCandidate->getKey(),
    );
    $secondProcess = startCustomerConcurrencyProcess(
        'set-default-address',
        (string) $customer->getKey(),
        (string) $secondCandidate->getKey(),
    );

    waitForCustomerConcurrencyProcess($firstProcess);
    waitForCustomerConcurrencyProcess($secondProcess);

    $activeDefaults = $customer->addresses()
        ->where('is_default', true)
        ->pluck('id')
        ->all();

    expect($activeDefaults)->toHaveCount(1)
        ->and($activeDefaults[0])->toBeIn([$firstCandidate->getKey(), $secondCandidate->getKey()]);
});

it('keeps one active canonical address when duplicate guest resolution runs concurrently', function () {
    $customer = Customer::factory()->create([
        'phone' => '+20 100 444 5555',
        'phone_normalized' => '+201004445555',
    ]);

    $payload = json_encode([
        'label' => 'Guest Address',
        'phone' => '01004445555',
        'phoneCountryCode' => 'EG',
        'countryCode' => 'EG',
        'city' => 'Cairo',
        'area' => 'Maadi',
        'street' => 'Street 9',
        'notes' => 'Same request',
    ], JSON_THROW_ON_ERROR);

    $firstProcess = startCustomerConcurrencyProcess(
        'resolve-address',
        (string) $customer->getKey(),
        $payload,
    );
    $secondProcess = startCustomerConcurrencyProcess(
        'resolve-address',
        (string) $customer->getKey(),
        $payload,
    );

    $results = [
        waitForCustomerConcurrencyProcess($firstProcess),
        waitForCustomerConcurrencyProcess($secondProcess),
    ];

    $addresses = $customer->addresses()->get();

    expect($addresses)->toHaveCount(1)
        ->and($addresses->first()?->address_hash)->toBe(hash('sha256', 'eg|cairo|maadi|street 9'))
        ->and(collect($results)->pluck('status')->unique()->all())->toBe(['success'])
        ->and(collect($results)->pluck('addressId')->unique())->toHaveCount(1)
        ->and(collect($results)->where('wasCreated', true))->toHaveCount(1)
        ->and(collect($results)->where('wasCreated', false))->toHaveCount(1);
});
