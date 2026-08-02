<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;

class CustomerOrderResolver
{
    /**
     * @return array{name:string,email:?string,phone:string,phoneNormalized:string}
     */
    public function normalizeSubmittedCustomer(array $payload): array
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) ($payload['name'] ?? ''))) ?? trim((string) ($payload['name'] ?? ''));
        $email = $this->normalizeEmail($payload['email'] ?? null);
        $phone = $this->normalizeEgyptianPhone((string) ($payload['phone'] ?? ''));

        if ($phone === null) {
            throw new ApiBusinessException(
                'orders.errors.customer_phone_invalid',
                'CUSTOMER_PHONE_INVALID',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['phone' => [__('orders.errors.customer_phone_invalid')]],
            );
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'phoneNormalized' => $phone,
        ];
    }

    public function resolvePublicCustomer(array $payload): Customer
    {
        $submitted = $this->normalizeSubmittedCustomer($payload);

        $matchingCustomers = Customer::query()
            ->where('phone_normalized', $submitted['phoneNormalized'])
            ->orderBy('id')
            ->get();

        if ($matchingCustomers->count() === 1) {
            return $matchingCustomers->firstOrFail();
        }

        if ($matchingCustomers->count() > 1 && $submitted['email'] !== null) {
            $emailMatches = $matchingCustomers
                ->filter(fn (Customer $customer): bool => $this->normalizeEmail($customer->email) === $submitted['email'])
                ->values();

            if ($emailMatches->count() === 1) {
                return $emailMatches->firstOrFail();
            }
        }

        return Customer::query()->create([
            'name' => $submitted['name'],
            'email' => $submitted['email'],
            'phone' => $submitted['phone'],
            'phone_normalized' => $submitted['phoneNormalized'],
        ]);
    }

    public function resolveAdminCustomer(?int $customerId, ?array $customerPayload): Customer
    {
        if (($customerId === null && $customerPayload === null) || ($customerId !== null && $customerPayload !== null)) {
            throw new ApiBusinessException(
                'orders.errors.customer_source_invalid',
                'CUSTOMER_SOURCE_INVALID',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);

            if (! $customer instanceof Customer) {
                throw new ApiBusinessException(
                    'orders.errors.customer_not_found',
                    'CUSTOMER_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            return $customer;
        }

        /** @var array $customerPayload */
        $submitted = $this->normalizeSubmittedCustomer($customerPayload);

        return Customer::query()->create([
            'name' => $submitted['name'],
            'email' => $submitted['email'],
            'phone' => $submitted['phone'],
            'phone_normalized' => $submitted['phoneNormalized'],
        ]);
    }

    public function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeEgyptianPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0020')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '20')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! preg_match('/^01\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
