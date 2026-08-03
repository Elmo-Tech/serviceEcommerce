<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Orders;

use App\Actions\Orders\AddOrderItemAction;
use App\Actions\Orders\DeleteOrderItemAction;
use App\Actions\Orders\UpdateOrderItemAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Orders\CreateOrderItemRequest;
use App\Http\Requests\Api\V1\Admin\Orders\UpdateOrderItemRequest;
use App\Http\Resources\Api\V1\Admin\Orders\AdminOrderItemResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderNestedResourceResolver;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function __construct(
        private readonly AddOrderItemAction $addOrderItemAction,
        private readonly UpdateOrderItemAction $updateOrderItemAction,
        private readonly DeleteOrderItemAction $deleteOrderItemAction,
        private readonly OrderNestedResourceResolver $orderNestedResourceResolver,
    ) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $items = $order->items()
            ->with(['selectedOptions.values', 'answers', 'attachments'])
            ->ordered()
            ->get();

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('orders.retrieved'),
                'data' => AdminOrderItemResource::collection($items)->resolve($request),
            ], HttpStatusCode::OK->value),
        );
    }

    public function store(CreateOrderItemRequest $request, Order $order): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if ($request->hasFile('attachments') && ! $admin->can('order-item-attachments.create')) {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.forbidden'),
                    'FORBIDDEN',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        $orderItem = $this->addOrderItemAction->execute($order, $request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.created'),
                (new AdminOrderItemResource($orderItem))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, Order $order, int $orderItem): JsonResponse
    {
        $resolvedItem = $this->orderNestedResourceResolver->resolveOrderItem($order, $orderItem)
            ->load(['selectedOptions.values', 'answers', 'attachments']);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.retrieved'),
                (new AdminOrderItemResource($resolvedItem))->resolve($request),
            ),
        );
    }

    public function update(UpdateOrderItemRequest $request, Order $order, int $orderItem): JsonResponse
    {
        $resolvedItem = $this->orderNestedResourceResolver->resolveOrderItem($order, $orderItem);

        $updatedItem = $this->updateOrderItemAction->execute($order, $resolvedItem, $request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.updated'),
                (new AdminOrderItemResource($updatedItem))->resolve($request),
            ),
        );
    }

    public function destroy(Order $order, int $orderItem): JsonResponse
    {
        $resolvedItem = $this->orderNestedResourceResolver->resolveOrderItem($order, $orderItem);

        $this->deleteOrderItemAction->execute($order, $resolvedItem);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('orders.deleted'), null),
        );
    }
}
