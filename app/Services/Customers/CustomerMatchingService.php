<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;

class CustomerMatchingService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumberService,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     email: ?string,
     *     phone: string,
     *     phoneNormalized: string,
     *     phoneCountryCode: string
     * }
     */
    public function normalizeSubmittedCustomer(array $payload): array
    {
        $phone = $this->phoneNumberService->normalize(
            (string) $payload['phone'],
            is_string($payload['phoneCountryCode'] ?? null) ? $payload['phoneCountryCode'] : 'EG',
        );

        if ($phone === null) {
            throw new ApiBusinessException(
                'customers.errors.phone_invalid',
                'CUSTOMER_PHONE_INVALID',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['phone' => [__('customers.errors.phone_invalid')]],
            );
        }

        $name = trim((string) $payload['name']);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        $email = null;

        if (array_key_exists('email', $payload) && $payload['email'] !== null) {
            $trimmedEmail = trim((string) $payload['email']);
            $email = $trimmedEmail === '' ? null : mb_strtolower($trimmedEmail);
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone['display'],
            'phoneNormalized' => $phone['normalized'],
            'phoneCountryCode' => $phone['regionCode'],
        ];
    }

    public function findMatchingCustomer(string $phoneNormalized): ?Customer
    {
        $customer = Customer::withTrashed()
            ->where('phone_normalized', $phoneNormalized)
            ->first();

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * @return array{
     *     customer: Customer,
     *     wasCreated: bool,
     *     wasRestored: bool,
     *     submittedCustomer: array{name: string, email: ?string, phone: string, phoneNormalized: string, phoneCountryCode: string}
     * }
     */
    public function makeResolutionResult(
        Customer $customer,
        array $submittedCustomer,
        bool $wasCreated,
        bool $wasRestored,
    ): array {
        return [
            'customer' => $customer,
            'wasCreated' => $wasCreated,
            'wasRestored' => $wasRestored,
            'submittedCustomer' => $submittedCustomer,
        ];
    }
}
