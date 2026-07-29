<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class RestoreCustomerAction
{
    public function execute(Customer $customer): Customer
    {
        $phoneOwner = Customer::withTrashed()
            ->where('phone_normalized', $customer->phone_normalized)
            ->whereKeyNot($customer->getKey())
            ->whereNull('deleted_at')
            ->exists();

        if ($phoneOwner) {
            throw new ApiBusinessException(
                'customers.errors.phone_exists',
                'CUSTOMER_PHONE_ALREADY_EXISTS',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['phone' => [__('customers.errors.phone_exists')]],
            );
        }

        if (
            $customer->email !== null
            && Customer::withTrashed()
                ->where('email', $customer->email)
                ->whereKeyNot($customer->getKey())
                ->whereNull('deleted_at')
                ->exists()
        ) {
            throw new ApiBusinessException(
                'customers.errors.email_exists',
                'CUSTOMER_EMAIL_ALREADY_EXISTS',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['email' => [__('customers.errors.email_exists')]],
            );
        }

        DB::transaction(function () use ($customer): void {
            $customer->restore();
        });

        return $customer->fresh() ?? $customer;
    }
}
