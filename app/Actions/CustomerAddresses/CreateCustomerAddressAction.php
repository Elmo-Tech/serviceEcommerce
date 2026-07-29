<?php

declare(strict_types=1);

namespace App\Actions\CustomerAddresses;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Support\Facades\DB;

class CreateCustomerAddressAction
{
    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
    ) {}

    public function execute(Customer $customer, array $payload): CustomerAddress
    {
        $this->customerAddressService->ensureCustomerActive($customer);
        $attributes = $this->customerAddressService->normalizeCreatePayload($payload);

        return DB::transaction(function () use ($customer, $attributes): CustomerAddress {
            $activeAddresses = $this->customerAddressService->lockActiveAddresses($customer);

            $this->customerAddressService->ensureActiveAddressLimit($activeAddresses);
            $this->customerAddressService->ensureActiveHashAvailable($activeAddresses, $attributes['address_hash']);

            $shouldBeDefault = $activeAddresses->isEmpty() || $attributes['is_default'] === true;
            $attributes['customer_id'] = $customer->getKey();
            $attributes['is_default'] = $shouldBeDefault;

            $address = CustomerAddress::query()->create($attributes);

            if ($shouldBeDefault) {
                $this->customerAddressService->setOnlyDefault($customer, $address->getKey());
            }

            return $address->fresh() ?? $address;
        });
    }
}
