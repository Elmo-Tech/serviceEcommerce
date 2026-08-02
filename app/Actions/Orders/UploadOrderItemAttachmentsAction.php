<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAttachment;
use App\Services\Orders\OrderAttachmentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadOrderItemAttachmentsAction
{
    public function __construct(
        private readonly OrderAttachmentStore $orderAttachmentStore,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<OrderItemAttachment>
     */
    public function execute(Order $order, OrderItem $orderItem, array $files): array
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($order, $orderItem, $files, &$storedFiles): array {
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if (! $lockedOrder->isEditable()) {
                    throw new ApiBusinessException(
                        'orders.errors.order_not_editable',
                        'ORDER_NOT_EDITABLE',
                        HttpStatusCode::CONFLICT,
                    );
                }

                $lockedItem = $lockedOrder->items()
                    ->whereKey($orderItem->getKey())
                    ->withCount('attachments')
                    ->firstOrFail();

                $this->orderAttachmentStore->ensureStandaloneUploadLimits((int) $lockedItem->attachments_count, $files);

                $persistedAttachments = [];

                foreach ($files as $file) {
                    $stored = $this->orderAttachmentStore->storeUploadedFile($file, $lockedOrder->order_number, (int) $lockedItem->getKey());
                    $storedFiles[] = [
                        'disk' => $stored['disk'],
                        'path' => $stored['path'],
                    ];
                    $persistedAttachments[] = $lockedItem->attachments()->create($stored);
                }

                return $persistedAttachments;
            });
        } catch (\Throwable $throwable) {
            $this->orderAttachmentStore->cleanupCreatedFiles($storedFiles);

            throw $throwable;
        }
    }
}
