<?php

declare(strict_types=1);

namespace App\Actions\CustomerAddresses;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Support\Facades\DB;

class UpdateCustomerAddressAction
{
    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
    ) {}

    public function execute(Customer $customer, CustomerAddress $address, array $payload): CustomerAddress
    {
        $this->customerAddressService->ensureCustomerActive($customer);
        $attributes = $this->customerAddressService->normalizeUpdatePayload($address, $payload);

        return DB::transaction(function () use ($customer, $address, $attributes): CustomerAddress {
            $activeAddresses = $this->customerAddressService->lockActiveAddresses($customer);

            if (array_key_exists('address_hash', $attributes)) {
                $this->customerAddressService->ensureActiveHashAvailable(
                    $activeAddresses,
                    $attributes['address_hash'],
                    $address->getKey(),
                );
            }

            if (
                array_key_exists('is_default', $attributes)
                && $attributes['is_default'] === false
                && $address->is_default
            ) {
                throw new ApiBusinessException(
                    'customer_addresses.errors.default_required',
                    'CUSTOMER_DEFAULT_ADDRESS_REQUIRED',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            $makeDefault = array_key_exists('is_default', $attributes) && $attributes['is_default'] === true;
            unset($attributes['is_default']);

            $address->forceFill($attributes)->save();

            if ($makeDefault) {
                $this->customerAddressService->setOnlyDefault($customer, $address->getKey());
            }

            return $address->fresh() ?? $address;
        });
    }
}
