<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeleteCustomerAction
{
    public function execute(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $customer->activeAddresses()->get()->each->delete();
            $customer->delete();
        });
    }
}
