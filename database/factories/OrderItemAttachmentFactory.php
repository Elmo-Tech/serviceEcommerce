<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderItemAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItemAttachment>
 */
class OrderItemAttachmentFactory extends Factory
{
    protected $model = OrderItemAttachment::class;

    public function definition(): array
    {
        $storedName = Str::uuid()->toString().'.pdf';

        return [
            'order_item_id' => OrderItem::factory(),
            'disk' => 'public',
            'path' => 'orders/attachments/'.$storedName,
            'stored_name' => $storedName,
            'original_name' => 'specification.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 2048,
        ];
    }
}
