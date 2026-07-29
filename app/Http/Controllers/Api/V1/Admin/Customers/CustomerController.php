<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Customers;

use App\Actions\Customers\CreateCustomerAction;
use App\Actions\Customers\DeleteCustomerAction;
use App\Actions\Customers\RestoreCustomerAction;
use App\Actions\Customers\UpdateCustomerAction;
use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Customers\ListCustomersRequest;
use App\Http\Requests\Api\V1\Admin\Customers\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Admin\Customers\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\Admin\Customers\CustomerDetailResource;
use App\Http\Resources\Api\V1\Admin\Customers\CustomerListItemResource;
use App\Models\Customer;
use App\Queries\Customers\CustomerIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerIndexQuery $customerIndexQuery,
        private readonly CreateCustomerAction $createCustomerAction,
        private readonly UpdateCustomerAction $updateCustomerAction,
        private readonly DeleteCustomerAction $deleteCustomerAction,
        private readonly RestoreCustomerAction $restoreCustomerAction,
    ) {}

    public function index(ListCustomersRequest $request): JsonResponse
    {
        $customers = $this->customerIndexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('customers.listed'),
                'data' => CustomerListItemResource::collection(collect($customers->items()))->resolve($request),
                'meta' => [
                    'currentPage' => $customers->currentPage(),
                    'perPage' => $customers->perPage(),
                    'total' => $customers->total(),
                    'lastPage' => $customers->lastPage(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->createCustomerAction->execute($request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customers.created'),
                (new CustomerDetailResource($this->loadCustomerDetail($customer->getKey())))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, int|string $customer): JsonResponse
    {
        $resolvedCustomer = $this->loadCustomerDetail((int) $customer);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customers.retrieved'),
                (new CustomerDetailResource($resolvedCustomer))->resolve($request),
            ),
        );
    }

    public function update(UpdateCustomerRequest $request, int|string $customer): JsonResponse
    {
        $resolvedCustomer = $this->resolveCustomerForMutation((int) $customer);
        $updatedCustomer = $this->updateCustomerAction->execute($resolvedCustomer, $request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customers.updated'),
                (new CustomerDetailResource($this->loadCustomerDetail($updatedCustomer->getKey())))->resolve($request),
            ),
        );
    }

    public function destroy(int|string $customer): JsonResponse
    {
        $resolvedCustomer = $this->resolveCustomerForMutation((int) $customer);

        $this->deleteCustomerAction->execute($resolvedCustomer);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customers.deleted'),
                null,
            ),
        );
    }

    public function restore(Request $request, int|string $customer): JsonResponse
    {
        $resolvedCustomer = Customer::withTrashed()->find((int) $customer);

        if (! $resolvedCustomer instanceof Customer) {
            $this->throwCustomerNotFound();
        }

        if (! $resolvedCustomer->trashed()) {
            $this->throwCustomerNotFound();
        }

        $restoredCustomer = $this->restoreCustomerAction->execute($resolvedCustomer);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('customers.restored'),
                (new CustomerDetailResource($this->loadCustomerDetail($restoredCustomer->getKey())))->resolve($request),
            ),
        );
    }

    private function loadCustomerDetail(int $customerId): Customer
    {
        $customer = Customer::withTrashed()
            ->withCount(['activeAddresses as addresses_count'])
            ->with(['addresses' => function ($query): void {
                $query->withTrashed()
                    ->orderByDesc('is_default')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            }])
            ->find($customerId);

        if (! $customer instanceof Customer) {
            $this->throwCustomerNotFound();
        }

        return $customer;
    }

    private function resolveCustomerForMutation(int $customerId): Customer
    {
        $customer = Customer::withTrashed()->find($customerId);

        if (! $customer instanceof Customer) {
            $this->throwCustomerNotFound();
        }

        if ($customer->trashed()) {
            throw new ApiBusinessException(
                'customers.errors.deleted',
                'CUSTOMER_DELETED',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        return $customer;
    }

    private function throwCustomerNotFound(): never
    {
        throw new ApiBusinessException(
            'customers.errors.not_found',
            'CUSTOMER_NOT_FOUND',
            HttpStatusCode::NOT_FOUND,
        );
    }
}
