<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Services\Customers\PhoneNumberService;
use Illuminate\Database\QueryException;

class UpdateCustomerAction
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumberService,
    ) {}

    public function execute(Customer $customer, array $payload): Customer
    {
        $attributes = [];

        if (array_key_exists('name', $payload)) {
            $attributes['name'] = $this->normalizeName((string) $payload['name']);
        }

        if (array_key_exists('email', $payload)) {
            $email = $this->normalizeEmail($payload['email']);

            if (
                $email !== null
                && Customer::withTrashed()
                    ->where('email', $email)
                    ->whereKeyNot($customer->getKey())
                    ->exists()
            ) {
                $this->throwEmailExists();
            }

            $attributes['email'] = $email;
        }

        if (array_key_exists('phone', $payload)) {
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

            if (
                Customer::withTrashed()
                    ->where('phone_normalized', $phone['normalized'])
                    ->whereKeyNot($customer->getKey())
                    ->exists()
            ) {
                $this->throwPhoneExists();
            }

            $attributes['phone'] = $phone['display'];
            $attributes['phone_normalized'] = $phone['normalized'];
        }

        try {
            $customer->forceFill($attributes)->save();
        } catch (QueryException $exception) {
            $this->rethrowDuplicateConstraint($exception);

            throw $exception;
        }

        return $customer->fresh() ?? $customer;
    }

    private function normalizeName(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : mb_strtolower($trimmed);
    }

    private function rethrowDuplicateConstraint(QueryException $exception): void
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'phone_normalized')) {
            $this->throwPhoneExists();
        }

        if (str_contains($message, 'email')) {
            $this->throwEmailExists();
        }
    }

    private function throwPhoneExists(): never
    {
        throw new ApiBusinessException(
            'customers.errors.phone_exists',
            'CUSTOMER_PHONE_ALREADY_EXISTS',
            HttpStatusCode::UNPROCESSABLE_ENTITY,
            ['phone' => [__('customers.errors.phone_exists')]],
        );
    }

    private function throwEmailExists(): never
    {
        throw new ApiBusinessException(
            'customers.errors.email_exists',
            'CUSTOMER_EMAIL_ALREADY_EXISTS',
            HttpStatusCode::UNPROCESSABLE_ENTITY,
            ['email' => [__('customers.errors.email_exists')]],
        );
    }
}
