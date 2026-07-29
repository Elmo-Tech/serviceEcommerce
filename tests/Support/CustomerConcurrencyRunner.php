<?php

declare(strict_types=1);

use App\Actions\CustomerAddresses\ResolveGuestCustomerAddressAction;
use App\Actions\CustomerAddresses\SetDefaultCustomerAddressAction;
use App\Actions\Customers\CreateCustomerAction;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Models\CustomerAddress;
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
        case 'create-customer':
            $payload = json_decode($argv[2] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $customer = $app->make(CreateCustomerAction::class)->execute($payload);

            echo json_encode([
                'status' => 'success',
                'customerId' => $customer->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'set-default-address':
            $customer = Customer::query()->findOrFail((int) ($argv[2] ?? 0));
            $address = CustomerAddress::query()->findOrFail((int) ($argv[3] ?? 0));
            $resolvedAddress = $app->make(SetDefaultCustomerAddressAction::class)->execute($customer, $address);

            echo json_encode([
                'status' => 'success',
                'addressId' => $resolvedAddress->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'resolve-address':
            $customer = Customer::query()->findOrFail((int) ($argv[2] ?? 0));
            $payload = json_decode($argv[3] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $result = $app->make(ResolveGuestCustomerAddressAction::class)->execute($customer, $payload);

            echo json_encode([
                'status' => 'success',
                'addressId' => $result['address']->getKey(),
                'wasCreated' => $result['wasCreated'],
                'wasRestored' => $result['wasRestored'],
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
