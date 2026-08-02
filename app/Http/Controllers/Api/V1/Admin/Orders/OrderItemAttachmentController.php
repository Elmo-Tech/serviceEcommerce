<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Orders;

use App\Actions\Orders\DeleteOrderItemAttachmentAction;
use App\Actions\Orders\UploadOrderItemAttachmentsAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Orders\UploadOrderItemAttachmentsRequest;
use App\Http\Resources\Api\V1\Admin\Orders\AdminOrderAttachmentResource;
use App\Models\Order;
use App\Services\Orders\OrderNestedResourceResolver;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderItemAttachmentController extends Controller
{
    public function __construct(
        private readonly UploadOrderItemAttachmentsAction $uploadOrderItemAttachmentsAction,
        private readonly DeleteOrderItemAttachmentAction $deleteOrderItemAttachmentAction,
        private readonly OrderNestedResourceResolver $orderNestedResourceResolver,
    ) {}

    public function store(UploadOrderItemAttachmentsRequest $request, Order $order, int $orderItem): JsonResponse
    {
        $resolvedItem = $this->orderNestedResourceResolver->resolveOrderItem($order, $orderItem);
        $attachments = $this->uploadOrderItemAttachmentsAction->execute($order, $resolvedItem, $request->uploadedFiles());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('order_attachments.uploaded'),
                AdminOrderAttachmentResource::collection(collect($attachments))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function destroy(Order $order, int $orderItem, int $attachment): JsonResponse
    {
        $resolvedAttachment = $this->orderNestedResourceResolver->resolveAttachment($order, $orderItem, $attachment);

        $this->deleteOrderItemAttachmentAction->execute($order, $resolvedAttachment);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('order_attachments.deleted'), null),
        );
    }

    public function download(Order $order, int $orderItem, int $attachment): StreamedResponse
    {
        $resolvedAttachment = $this->orderNestedResourceResolver->resolveAttachment($order, $orderItem, $attachment);

        return Storage::disk($resolvedAttachment->disk)->download(
            $resolvedAttachment->path,
            $resolvedAttachment->original_name,
        );
    }
}
