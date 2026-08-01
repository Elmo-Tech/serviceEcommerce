<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Customers;

use App\Models\CustomerAddress;
use App\Support\Customers\CustomerPhoneFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => CustomerPhoneFormatter::forResponse($this->phone, $this->phone_normalized),
            'province' => $this->province,
            'city' => $this->city,
            'address' => $this->address,
            'notes' => $this->notes,
            'isDefault' => $this->is_default,
            'isDeleted' => $this->trashed(),
            'deletedAt' => $this->deleted_at?->toJSON(),
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
        ];
    }
}
