<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Services\Customers\CustomerMatchingService;

class ResolveGuestCustomerAction
{
    public function __construct(
        private readonly CustomerMatchingService $customerMatchingService,
        private readonly CreateCustomerAction $createCustomerAction,
        private readonly RestoreCustomerAction $restoreCustomerAction,
    ) {}

    public function execute(array $payload): array
    {
        $submittedCustomer = $this->customerMatchingService->normalizeSubmittedCustomer($payload);
        $matchingCustomer = $this->customerMatchingService->findMatchingCustomer($submittedCustomer['phoneNormalized']);

        if ($matchingCustomer instanceof Customer && ! $matchingCustomer->trashed()) {
            return $this->customerMatchingService->makeResolutionResult(
                $matchingCustomer,
                $submittedCustomer,
                false,
                false,
            );
        }

        if ($matchingCustomer instanceof Customer && $matchingCustomer->trashed()) {
            $restoredCustomer = $this->restoreCustomerAction->execute($matchingCustomer);

            return $this->customerMatchingService->makeResolutionResult(
                $restoredCustomer,
                $submittedCustomer,
                false,
                true,
            );
        }

        try {
            $createdCustomer = $this->createCustomerAction->execute([
                'name' => $submittedCustomer['name'],
                'email' => $submittedCustomer['email'],
                'phone' => $payload['phone'],
                'phoneCountryCode' => $submittedCustomer['phoneCountryCode'],
            ]);

            return $this->customerMatchingService->makeResolutionResult(
                $createdCustomer,
                $submittedCustomer,
                true,
                false,
            );
        } catch (ApiBusinessException $exception) {
            if ($exception->machineCode() !== 'CUSTOMER_PHONE_ALREADY_EXISTS') {
                throw $exception;
            }

            $reloadedCustomer = $this->customerMatchingService->findMatchingCustomer($submittedCustomer['phoneNormalized']);

            if (! $reloadedCustomer instanceof Customer) {
                throw $exception;
            }

            if ($reloadedCustomer->trashed()) {
                $reloadedCustomer = $this->restoreCustomerAction->execute($reloadedCustomer);

                return $this->customerMatchingService->makeResolutionResult(
                    $reloadedCustomer,
                    $submittedCustomer,
                    false,
                    true,
                );
            }

            return $this->customerMatchingService->makeResolutionResult(
                $reloadedCustomer,
                $submittedCustomer,
                false,
                false,
            );
        }
    }
}
