<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Services\Customers\PhoneNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateCustomerAction
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumberService,
    ) {}

    public function execute(array $payload): Customer
    {
        $phone = $this->resolvePhoneOrFail(
            (string) $payload['phone'],
            $payload['phoneCountryCode'] ?? 'EG',
        );

        $name = $this->normalizeName((string) $payload['name']);
        $email = $this->normalizeEmail($payload['email'] ?? null);

        if ($email !== null && Customer::withTrashed()->where('email', $email)->exists()) {
            $this->throwEmailExists();
        }

        try {
            return DB::transaction(function () use ($name, $email, $phone): Customer {
                return Customer::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone['display'],
                    'phone_normalized' => $phone['normalized'],
                ]);
            });
        } catch (QueryException $exception) {
            $this->rethrowDuplicateConstraint($exception);

            throw $exception;
        }
    }

    private function resolvePhoneOrFail(string $phone, mixed $countryCode): array
    {
        $resolved = $this->phoneNumberService->normalize($phone, is_string($countryCode) ? $countryCode : 'EG');

        if ($resolved === null) {
            throw new ApiBusinessException(
                'customers.errors.phone_invalid',
                'CUSTOMER_PHONE_INVALID',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['phone' => [__('customers.errors.phone_invalid')]],
            );
        }

        if (Customer::withTrashed()->where('phone_normalized', $resolved['normalized'])->exists()) {
            $this->throwPhoneExists();
        }

        return $resolved;
    }

    private function normalizeName(string $value): string
    {
        $trimmed = trim($value);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

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
