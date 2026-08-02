<?php

declare(strict_types=1);

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServiceOrderFieldType;
use App\Enums\Services\ServicePriceType;
use App\Enums\Services\ServicePricingInputType;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

function adminOrderLocalizationHeaders(string $locale): array
{
    return [
        'Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'),
        'Accept-Language' => $locale,
    ];
}

it('projects localized order detail snapshots while keeping machine keys stable in english', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::CONFIRMED,
        'order_place' => OrderPlace::WHATSAPP,
        'payment_status' => PaymentStatus::PARTIALLY_PAID,
        'customer_name' => 'Mohamed Hassan',
        'customer_phone' => '01001234567',
        'customer_email' => 'mohamed@example.com',
        'address_province' => 'Cairo',
        'address_city' => 'Nasr City',
        'address_text' => 'Building 10',
        'subtotal' => '550.00',
        'total' => '500.00',
        'paid_amount' => '200.00',
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => null,
        'service_name_ar' => 'تصميم هوية',
        'service_name_en' => 'Brand Design',
        'service_slug_ar' => 'تصميم-هوية',
        'service_slug_en' => 'brand-design',
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '550.00',
        'unit_price' => '550.00',
        'quantity' => 1,
        'item_total' => '550.00',
    ]);

    $selectedOption = $item->selectedOptions()->create([
        'pricing_option_id' => null,
        'option_name_ar' => 'المقاس',
        'option_name_en' => 'Size',
        'input_type' => ServicePricingInputType::SELECT->value,
        'is_required' => true,
    ]);
    $selectedOption->values()->create([
        'pricing_option_value_id' => null,
        'value_label_ar' => 'كبير',
        'value_label_en' => 'Large',
        'price_adjustment' => '50.00',
    ]);

    $item->answers()->create([
        'service_order_field_id' => null,
        'question_ar' => 'الوصف',
        'question_en' => 'Description',
        'field_type' => ServiceOrderFieldType::TEXT->value,
        'is_required' => true,
        'answer' => 'Needs premium quality',
    ]);

    $attachment = $item->attachments()->create([
        'disk' => 'public',
        'path' => 'orders/attachments/sample.pdf',
        'stored_name' => 'sample.pdf',
        'original_name' => 'sample.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 2048,
    ]);

    $englishResponse = $this->getJson(
        '/api/v1/admin/orders/'.$order->getKey(),
        adminOrderLocalizationHeaders('en'),
    );

    $englishResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.status', OrderStatus::CONFIRMED->value)
        ->assertJsonPath('data.payment.paymentStatus', PaymentStatus::PARTIALLY_PAID->value)
        ->assertJsonPath('data.orderPlace', OrderPlace::WHATSAPP->value)
        ->assertJsonPath('data.items.0.serviceSnapshot.name', 'Brand Design')
        ->assertJsonPath('data.items.0.serviceSnapshot.slug', 'brand-design')
        ->assertJsonPath('data.items.0.selectedOptions.0.name', 'Size')
        ->assertJsonPath('data.items.0.selectedOptions.0.values.0.label', 'Large')
        ->assertJsonPath('data.items.0.answers.0.question', 'Description')
        ->assertJsonPath('data.items.0.attachments.0.downloadEndpoint', '/api/v1/admin/orders/'.$order->getKey().'/items/'.$item->getKey().'/attachments/'.$attachment->getKey().'/download');

    $arabicResponse = $this->getJson(
        '/api/v1/admin/orders/'.$order->getKey(),
        adminOrderLocalizationHeaders('ar'),
    );

    $arabicResponse->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.status', OrderStatus::CONFIRMED->value)
        ->assertJsonPath('data.payment.paymentStatus', PaymentStatus::PARTIALLY_PAID->value)
        ->assertJsonPath('data.orderPlace', OrderPlace::WHATSAPP->value)
        ->assertJsonPath('data.items.0.serviceSnapshot.name', 'تصميم هوية')
        ->assertJsonPath('data.items.0.serviceSnapshot.slug', 'تصميم-هوية')
        ->assertJsonPath('data.items.0.selectedOptions.0.name', 'المقاس')
        ->assertJsonPath('data.items.0.selectedOptions.0.values.0.label', 'كبير')
        ->assertJsonPath('data.items.0.answers.0.question', 'الوصف')
        ->assertJsonPath('data.customerSnapshot.name', 'Mohamed Hassan')
        ->assertJsonPath('data.addressSnapshot.city', 'Nasr City');
});
