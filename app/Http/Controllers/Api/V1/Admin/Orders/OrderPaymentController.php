<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Orders;

use App\Actions\Orders\UpdateOrderPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Orders\UpdateOrderPaymentRequest;
use App\Http\Resources\Api\V1\Admin\Orders\AdminOrderPaymentResource;
use App\Models\Order;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function __construct(
        private readonly UpdateOrderPaymentAction $updateOrderPaymentAction,
    ) {}

    public function show(Request $request, Order $order): JsonResponse
    {
        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.retrieved'),
                (new AdminOrderPaymentResource($order))->resolve($request),
            ),
        );
    }

    public function update(UpdateOrderPaymentRequest $request, Order $order): JsonResponse
    {
        $updatedOrder = $this->updateOrderPaymentAction->execute(
            $order,
            (string) $request->validated('paidAmount'),
        );

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.payment_updated'),
                (new AdminOrderPaymentResource($updatedOrder))->resolve($request),
            ),
        );
    }
}
