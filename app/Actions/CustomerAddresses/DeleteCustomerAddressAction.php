<?php

declare(strict_types=1);

namespace App\Actions\CustomerAddresses;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Support\Facades\DB;

class DeleteCustomerAddressAction
{
    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
    ) {}

    public function execute(Customer $customer, CustomerAddress $address): void
    {
        $this->customerAddressService->ensureCustomerActive($customer);

        DB::transaction(function () use ($customer, $address): void {
            $this->customerAddressService->lockActiveAddresses($customer);
            $wasDefault = $address->is_default;

            $address->delete();

            if ($wasDefault) {
                $this->customerAddressService->assignFallbackDefault($customer);
            }
        });
    }
}
