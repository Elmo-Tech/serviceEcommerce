<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Customers;

use App\Actions\CustomerAddresses\CreateCustomerAddressAction;
use App\Actions\CustomerAddresses\DeleteCustomerAddressAction;
use App\Actions\CustomerAddresses\RestoreCustomerAddressAction;
use App\Actions\CustomerAddresses\SetDefaultCustomerAddressAction;
use App\Actions\CustomerAddresses\UpdateCustomerAddressAction;
use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Customers\ListCustomerAddressesRequest;
use App\Http\Requests\Api\V1\Admin\Customers\StoreCustomerAddressRequest;
use App\Http\Requests\Api\V1\Admin\Customers\UpdateCustomerAddressRequest;
use App\Http\Resources\Api\V1\Admin\Customers\CustomerAddressCollection;
use App\Http\Resources\Api\V1\Admin\Customers\CustomerAddressResource;
use App\Services\Customers\CustomerAddressService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
        private readonly CreateCustomerAddressAction $createCustomerAddressAction,
        private readonly UpdateCustomerAddressAction $updateCustomerAddressAction,
        private readonly DeleteCustomerAddressAction $deleteCustomerAddressAction,
        private readonly RestoreCustomerAddressAction $restoreCustomerAddressAction,
        private readonly SetDefaultCustomerAddressAction $setDefaultCustomerAddressAction,
    ) {}

    public function index(ListCustomerAddressesRequest $request, int|string $customer): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $this->customerAddressService->ensureCustomerActive($resolvedCustomer);

        $addresses = $this->customerAddressService->listAddresses(
            $resolvedCustomer,
            $request->statusFilter(),
        );

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.listed'),
                (new CustomerAddressCollection($addresses))->resolve($request),
            ),
        );
    }

    public function store(StoreCustomerAddressRequest $request, int|string $customer): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $address = $this->createCustomerAddressAction->execute($resolvedCustomer, $request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.created'),
                (new CustomerAddressResource($address))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, int|string $customer, int|string $address): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $this->customerAddressService->ensureCustomerActive($resolvedCustomer);
        $resolvedAddress = $this->customerAddressService->findScopedAddressOrFail($resolvedCustomer, (int) $address);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.retrieved'),
                (new CustomerAddressResource($resolvedAddress))->resolve($request),
            ),
        );
    }

    public function update(UpdateCustomerAddressRequest $request, int|string $customer, int|string $address): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $resolvedAddress = $this->customerAddressService->findActiveScopedAddressOrFail($resolvedCustomer, (int) $address);
        $updatedAddress = $this->updateCustomerAddressAction->execute($resolvedCustomer, $resolvedAddress, $request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.updated'),
                (new CustomerAddressResource($updatedAddress))->resolve($request),
            ),
        );
    }

    public function destroy(int|string $customer, int|string $address): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $resolvedAddress = $this->customerAddressService->findActiveScopedAddressOrFail($resolvedCustomer, (int) $address);

        $this->deleteCustomerAddressAction->execute($resolvedCustomer, $resolvedAddress);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.deleted'),
                null,
            ),
        );
    }

    public function restore(Request $request, int|string $customer, int|string $address): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $resolvedAddress = $this->customerAddressService->findScopedAddressOrFail($resolvedCustomer, (int) $address);

        if (! $resolvedAddress->trashed()) {
            throw new ApiBusinessException(
                'customer_addresses.errors.not_found',
                'CUSTOMER_ADDRESS_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        $restoredAddress = $this->restoreCustomerAddressAction->execute($resolvedCustomer, $resolvedAddress);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.restored'),
                (new CustomerAddressResource($restoredAddress))->resolve($request),
            ),
        );
    }

    public function setDefault(Request $request, int|string $customer, int|string $address): JsonResponse
    {
        $resolvedCustomer = $this->customerAddressService->findCustomerOrFail((int) $customer);
        $resolvedAddress = $this->customerAddressService->findActiveScopedAddressOrFail($resolvedCustomer, (int) $address);
        $defaultAddress = $this->setDefaultCustomerAddressAction->execute($resolvedCustomer, $resolvedAddress);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customer_addresses.default_updated'),
                (new CustomerAddressResource($defaultAddress))->resolve($request),
            ),
        );
    }
}
