<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerAddressCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return CustomerAddressResource::collection($this->collection)->resolve($request);
    }
}
