<?php

declare(strict_types=1);

namespace App\Actions\CustomerAddresses;

use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressService;

class ResolveGuestCustomerAddressAction
{
    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
        private readonly CreateCustomerAddressAction $createCustomerAddressAction,
        private readonly RestoreCustomerAddressAction $restoreCustomerAddressAction,
    ) {}

    public function execute(Customer $customer, array $payload): array
    {
        $this->customerAddressService->ensureCustomerActive($customer);
        $normalizedPayload = $this->customerAddressService->normalizeCreatePayload($payload);

        $activeMatch = $this->customerAddressService->findActiveAddressByHash(
            $customer,
            $normalizedPayload['address_hash'],
        );

        if ($activeMatch instanceof CustomerAddress) {
            return $this->makeResolutionResult($activeMatch, $normalizedPayload, false, false);
        }

        $deletedMatch = $this->customerAddressService->findDeletedAddressByHash(
            $customer,
            $normalizedPayload['address_hash'],
        );

        if ($deletedMatch instanceof CustomerAddress) {
            $restoredAddress = $this->restoreCustomerAddressAction->execute($customer, $deletedMatch);

            return $this->makeResolutionResult($restoredAddress, $normalizedPayload, false, true);
        }

        try {
            $createdAddress = $this->createCustomerAddressAction->execute($customer, $payload);

            return $this->makeResolutionResult($createdAddress, $normalizedPayload, true, false);
        } catch (ApiBusinessException $exception) {
            if ($exception->machineCode() !== 'CUSTOMER_ADDRESS_ALREADY_EXISTS') {
                throw $exception;
            }

            $reloadedAddress = $this->customerAddressService->findActiveAddressByHash(
                $customer,
                $normalizedPayload['address_hash'],
            );

            if (! $reloadedAddress instanceof CustomerAddress) {
                throw $exception;
            }

            return $this->makeResolutionResult($reloadedAddress, $normalizedPayload, false, false);
        }
    }

    private function makeResolutionResult(
        CustomerAddress $address,
        array $submittedAddress,
        bool $wasCreated,
        bool $wasRestored,
    ): array {
        return [
            'address' => $address,
            'wasCreated' => $wasCreated,
            'wasRestored' => $wasRestored,
            'submittedAddress' => [
                'phone' => $submittedAddress['phone'],
                'phoneNormalized' => $submittedAddress['phone_normalized'],
                'province' => $submittedAddress['province'],
                'city' => $submittedAddress['city'],
                'address' => $submittedAddress['address'],
                'notes' => $submittedAddress['notes'],
                'addressHash' => $submittedAddress['address_hash'],
                'isDefault' => $submittedAddress['is_default'],
            ],
        ];
    }
}
