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
class CustomerDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => CustomerPhoneFormatter::forResponse($this->phone, $this->phone_normalized),
            'isDeleted' => $this->trashed(),
            'deletedAt' => $this->deleted_at?->toJSON(),
            'addressesCount' => (int) ($this->addresses_count ?? 0),
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses'))->resolve(),
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
        ];
    }
}
