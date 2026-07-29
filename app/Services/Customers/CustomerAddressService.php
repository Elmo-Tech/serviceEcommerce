<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;

class CustomerAddressService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumberService,
        private readonly AddressNormalizationService $addressNormalizationService,
    ) {}

    public function findCustomerOrFail(int $customerId): Customer
    {
        $customer = Customer::withTrashed()->find($customerId);

        if (! $customer instanceof Customer) {
            throw new ApiBusinessException(
                'customers.errors.not_found',
                'CUSTOMER_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $customer;
    }

    public function ensureCustomerActive(Customer $customer): void
    {
        if ($customer->trashed()) {
            throw new ApiBusinessException(
                'customers.errors.deleted',
                'CUSTOMER_DELETED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function findScopedAddressOrFail(Customer $customer, int $addressId): CustomerAddress
    {
        $address = CustomerAddress::withTrashed()
            ->where('customer_id', $customer->getKey())
            ->find($addressId);

        if (! $address instanceof CustomerAddress) {
            throw new ApiBusinessException(
                'customer_addresses.errors.not_found',
                'CUSTOMER_ADDRESS_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $address;
    }

    public function findActiveScopedAddressOrFail(Customer $customer, int $addressId): CustomerAddress
    {
        $address = CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->find($addressId);

        if (! $address instanceof CustomerAddress) {
            throw new ApiBusinessException(
                'customer_addresses.errors.not_found',
                'CUSTOMER_ADDRESS_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $address;
    }

    public function listAddresses(Customer $customer, string $status): Collection
    {
        $query = CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        }

        return $query->get();
    }

    public function findActiveAddressByHash(Customer $customer, string $addressHash): ?CustomerAddress
    {
        $address = CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->where('address_hash', $addressHash)
            ->first();

        return $address instanceof CustomerAddress ? $address : null;
    }

    public function findDeletedAddressByHash(Customer $customer, string $addressHash): ?CustomerAddress
    {
        $address = CustomerAddress::onlyTrashed()
            ->where('customer_id', $customer->getKey())
            ->where('address_hash', $addressHash)
            ->first();

        return $address instanceof CustomerAddress ? $address : null;
    }

    public function normalizeCreatePayload(array $payload): array
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

        $identity = $this->addressNormalizationService->normalizeIdentity(
            (string) $payload['countryCode'],
            (string) $payload['city'],
            array_key_exists('area', $payload) ? $payload['area'] : null,
            (string) $payload['street'],
        );

        return [
            'label' => $this->addressNormalizationService->normalizeOptionalText($payload['label'] ?? null),
            'phone' => $phone['display'],
            'phone_normalized' => $phone['normalized'],
            'country_code' => $identity['countryCode'],
            'city' => $identity['city'],
            'area' => $identity['area'],
            'street' => $identity['street'],
            'notes' => $this->addressNormalizationService->normalizeOptionalText($payload['notes'] ?? null),
            'address_hash' => $identity['addressHash'],
            'is_default' => (bool) ($payload['isDefault'] ?? false),
        ];
    }

    public function normalizeUpdatePayload(CustomerAddress $address, array $payload): array
    {
        $attributes = [];

        foreach (['label', 'notes'] as $optionalField) {
            if (array_key_exists($optionalField, $payload)) {
                $attributes[$optionalField] = $this->addressNormalizationService->normalizeOptionalText($payload[$optionalField]);
            }
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

            $attributes['phone'] = $phone['display'];
            $attributes['phone_normalized'] = $phone['normalized'];
        }

        $identityFieldsTouched = array_intersect(
            ['countryCode', 'city', 'area', 'street'],
            array_keys($payload),
        ) !== [];

        if ($identityFieldsTouched) {
            $identity = $this->addressNormalizationService->normalizeIdentity(
                (string) ($payload['countryCode'] ?? $address->country_code),
                (string) ($payload['city'] ?? $address->city),
                array_key_exists('area', $payload) ? $payload['area'] : $address->area,
                (string) ($payload['street'] ?? $address->street),
            );

            $attributes['country_code'] = $identity['countryCode'];
            $attributes['city'] = $identity['city'];
            $attributes['area'] = $identity['area'];
            $attributes['street'] = $identity['street'];
            $attributes['address_hash'] = $identity['addressHash'];
        }

        if (array_key_exists('isDefault', $payload)) {
            $attributes['is_default'] = (bool) $payload['isDefault'];
        }

        return $attributes;
    }

    public function lockActiveAddresses(Customer $customer): Collection
    {
        Customer::query()
            ->whereKey($customer->getKey())
            ->lockForUpdate()
            ->first();

        return CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function ensureActiveAddressLimit(Collection $activeAddresses, int $incomingCount = 1): void
    {
        if (($activeAddresses->count() + $incomingCount) > 20) {
            throw new ApiBusinessException(
                'customer_addresses.errors.limit_exceeded',
                'CUSTOMER_ADDRESS_LIMIT_EXCEEDED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function ensureActiveHashAvailable(
        Collection $activeAddresses,
        string $addressHash,
        ?int $ignoreAddressId = null,
        bool $restoreConflict = false,
    ): void {
        $duplicate = $activeAddresses
            ->first(fn (CustomerAddress $address): bool => $address->address_hash === $addressHash && $address->getKey() !== $ignoreAddressId);

        if ($duplicate instanceof CustomerAddress) {
            throw new ApiBusinessException(
                $restoreConflict ? 'customer_addresses.errors.restore_conflict' : 'customer_addresses.errors.already_exists',
                $restoreConflict ? 'CUSTOMER_ADDRESS_RESTORE_CONFLICT' : 'CUSTOMER_ADDRESS_ALREADY_EXISTS',
                $restoreConflict ? HttpStatusCode::CONFLICT : HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function setOnlyDefault(Customer $customer, int $addressId): void
    {
        CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->whereNull('deleted_at')
            ->update(['is_default' => false]);

        CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->whereKey($addressId)
            ->update(['is_default' => true]);
    }

    public function assignFallbackDefault(Customer $customer): void
    {
        CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->whereNull('deleted_at')
            ->update(['is_default' => false]);

        $fallback = CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($fallback instanceof CustomerAddress) {
            $fallback->forceFill(['is_default' => true])->save();
        }
    }
}
