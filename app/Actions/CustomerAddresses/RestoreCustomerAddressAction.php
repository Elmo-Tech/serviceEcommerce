<?php

declare(strict_types=1);

namespace App\Actions\CustomerAddresses;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Support\Facades\DB;

class RestoreCustomerAddressAction
{
    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
    ) {}

    public function execute(Customer $customer, CustomerAddress $address): CustomerAddress
    {
        $this->customerAddressService->ensureCustomerActive($customer);

        return DB::transaction(function () use ($customer, $address): CustomerAddress {
            $activeAddresses = $this->customerAddressService->lockActiveAddresses($customer);

            $this->customerAddressService->ensureActiveAddressLimit($activeAddresses);
            $this->customerAddressService->ensureActiveHashAvailable(
                $activeAddresses,
                $address->address_hash,
                $address->getKey(),
                true,
            );

            $address->restore();

            if ($activeAddresses->isEmpty()) {
                $this->customerAddressService->setOnlyDefault($customer, $address->getKey());
            } elseif (! $activeAddresses->contains(fn (CustomerAddress $candidate): bool => $candidate->is_default)) {
                $this->customerAddressService->assignFallbackDefault($customer);
            } else {
                $address->forceFill(['is_default' => false])->save();
            }

            return $address->fresh() ?? $address;
        });
    }
}
