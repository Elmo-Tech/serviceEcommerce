<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Orders;

use App\Actions\Orders\CreateAdminOrderAction;
use App\Actions\Orders\DeleteOrderAction;
use App\Actions\Orders\UpdateOrderAction;
use App\Http\Requests\Api\V1\Admin\Orders\ListOrdersRequest;
use App\Http\Resources\Api\V1\Admin\Orders\AdminOrderIndexResource;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Orders\CreateAdminOrderRequest;
use App\Http\Requests\Api\V1\Admin\Orders\UpdateOrderRequest;
use App\Http\Resources\Api\V1\Admin\Orders\AdminOrderResource;
use App\Models\Order;
use App\Models\User;
use App\Queries\Orders\AdminOrderIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly CreateAdminOrderAction $createAdminOrderAction,
        private readonly UpdateOrderAction $updateOrderAction,
        private readonly DeleteOrderAction $deleteOrderAction,
        private readonly AdminOrderIndexQuery $adminOrderIndexQuery,
    ) {}

    public function index(ListOrdersRequest $request): JsonResponse
    {
        $orders = $this->adminOrderIndexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('orders.listed'),
                'data' => AdminOrderIndexResource::collection(collect($orders->items()))->resolve($request),
                'meta' => [
                    'currentPage' => $orders->currentPage(),
                    'perPage' => $orders->perPage(),
                    'total' => $orders->total(),
                    'lastPage' => $orders->lastPage(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function store(CreateAdminOrderRequest $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if ($this->requestContainsAttachments($request) && ! $admin->can('order-item-attachments.create')) {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.forbidden'),
                    'FORBIDDEN',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        $order = $this->createAdminOrderAction->execute($request->payload(), $admin)
            ->load([
                'cancelledByAdmin',
                'createdByAdmin',
                'items.selectedOptions.values',
                'items.answers',
                'items.attachments',
            ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.created'),
                (new AdminOrderResource($order))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $loadedOrder = $order->load([
            'cancelledByAdmin',
            'createdByAdmin',
            'items.selectedOptions.values',
            'items.answers',
            'items.attachments',
        ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.retrieved'),
                (new AdminOrderResource($loadedOrder))->resolve($request),
            ),
        );
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (array_key_exists('status', $request->payload()) && ! $admin->can('orders.change-status')) {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.forbidden'),
                    'FORBIDDEN',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        $updatedOrder = $this->updateOrderAction->execute($order, $request->payload(), $admin);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.updated'),
                (new AdminOrderResource($updatedOrder))->resolve($request),
            ),
        );
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $this->deleteOrderAction->execute($order);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.deleted'),
                null,
            ),
        );
    }

    private function requestContainsAttachments(CreateAdminOrderRequest $request): bool
    {
        foreach ((array) $request->file('items', []) as $itemFiles) {
            if (is_array($itemFiles) && is_array($itemFiles['attachments'] ?? null) && $itemFiles['attachments'] !== []) {
                return true;
            }
        }

        return false;
    }
}
