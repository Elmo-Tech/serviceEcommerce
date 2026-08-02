<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Orders;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Orders\ChangeOrderStatusRequest;
use App\Http\Resources\Api\V1\Admin\Orders\AdminOrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderStatusController extends Controller
{
    public function __construct(
        private readonly ChangeOrderStatusAction $changeOrderStatusAction,
    ) {}

    public function update(ChangeOrderStatusRequest $request, Order $order): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $updatedOrder = $this->changeOrderStatusAction->execute($order, $request->validated(), $admin);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.status_updated'),
                (new AdminOrderResource($updatedOrder))->resolve($request),
            ),
        );
    }
}
