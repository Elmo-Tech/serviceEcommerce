<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAttachment;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
    Storage::fake(config('filesystems.default'));
});

function orderAttachmentHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function orderAttachmentToken(): string
{
    return (string) loginAdminForTests()->json('data.accessToken');
}

it('uploads deletes and downloads nested attachments with strict ownership', function () {
    $accessToken = orderAttachmentToken();

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $orderItem = OrderItem::factory()->for($order)->create();

    $uploadResponse = $this->post('/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey().'/attachments', [
        'attachments' => [
            UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ],
    ], [
        'Accept' => 'application/json',
        ...orderAttachmentHeaders($accessToken),
    ]);

    $uploadResponse->assertCreated()
        ->assertJsonPath('data.0.originalName', 'brief.pdf')
        ->assertJsonMissingPath('data.0.path');

    $attachmentId = (int) $uploadResponse->json('data.0.id');
    $attachment = OrderItemAttachment::query()->findOrFail($attachmentId);

    Storage::disk($attachment->disk)->assertExists($attachment->path);

    $downloadResponse = $this->get('/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey().'/attachments/'.$attachmentId.'/download', orderAttachmentHeaders($accessToken));
    $downloadResponse->assertOk();

    $foreignOrder = Order::factory()->create();
    $foreignItem = OrderItem::factory()->for($foreignOrder)->create();
    $foreignAttachment = OrderItemAttachment::factory()->for($foreignItem)->create();

    $this->getJson(
        '/api/v1/admin/orders/'.$order->getKey().'/items/'.$foreignItem->getKey().'/attachments/'.$foreignAttachment->getKey().'/download',
        orderAttachmentHeaders($accessToken),
    )->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->deleteJson(
        '/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey().'/attachments/'.$attachmentId,
        [],
        orderAttachmentHeaders($accessToken),
    )->assertOk()
        ->assertJsonPath('success', true);
});
