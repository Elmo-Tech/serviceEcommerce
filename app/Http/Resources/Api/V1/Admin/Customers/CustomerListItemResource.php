<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Customers;

use App\Models\Customer;
use App\Support\Customers\CustomerPhoneFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => CustomerPhoneFormatter::forResponse($this->phone, $this->phone_normalized),
            'isDeleted' => $this->trashed(),
            'addressesCount' => (int) ($this->addresses_count ?? 0),
            'createdAt' => $this->created_at?->toJSON(),
        ];
    }
}
