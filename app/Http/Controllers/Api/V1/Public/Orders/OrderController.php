<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Orders;

use App\Actions\Orders\CreatePublicOrderAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\Orders\CreatePublicOrderRequest;
use App\Http\Resources\Api\V1\Public\Orders\PublicOrderSummaryResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly CreatePublicOrderAction $createPublicOrderAction,
    ) {}

    public function store(CreatePublicOrderRequest $request): JsonResponse
    {
        $result = $this->createPublicOrderAction->execute(
            $request->idempotencyKey(),
            $request->payload(),
        );

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('orders.created'),
                (new PublicOrderSummaryResource($result['order']))->resolve($request),
                $result['isReplay'] === true ? HttpStatusCode::OK : HttpStatusCode::CREATED,
            ),
        );
    }
}
