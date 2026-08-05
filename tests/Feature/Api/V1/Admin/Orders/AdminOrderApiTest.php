<?php

declare(strict_types=1);

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
    Storage::fake(config('filesystems.default'));
});

function orderAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function orderAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('creates an admin order for an existing customer and returns localized detail', function () {
    $accessToken = orderAdminToken();

    $customer = Customer::factory()->create([
        'name' => 'Existing Customer',
        'email' => 'existing@example.com',
        'phone' => '+20 100 123 4567',
        'phone_normalized' => '01001234567',
    ]);

    $customerAddress = $customer->addresses()->create([
        'province' => 'Cairo',
        'city' => 'Heliopolis',
        'address' => 'Street 1',
        'notes' => null,
        'address_hash' => hash('sha256', 'cairo|heliopolis|street 1'),
        'is_default' => true,
    ]);

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'name_ar' => 'خدمة التنظيف',
        'name_en' => 'Cleaning Service',
        'slug_ar' => 'خدمة-التنظيف',
        'slug_en' => 'cleaning-service',
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '120.00',
    ]);

    $response = $this->postJson('/api/v1/admin/orders', [
        'customerId' => $customer->getKey(),
        'customerAddressId' => $customerAddress->getKey(),
        'orderPlace' => OrderPlace::WHATSAPP->value,
        'customerNote' => 'Customer note',
        'adminNote' => 'Admin note',
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 2,
            ],
        ],
    ], orderAdminHeaders($accessToken));

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.customerId', $customer->getKey())
        ->assertJsonPath('data.customerAddressId', $customerAddress->getKey())
        ->assertJsonPath('data.orderPlace', OrderPlace::WHATSAPP->value)
        ->assertJsonPath('data.subtotal', '240.00')
        ->assertJsonPath('data.total', '240.00')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.serviceSnapshot.name', 'Cleaning Service')
        ->assertJsonPath('data.createdByAdmin.name', 'Service Commerce Super Admin');

    expect(Order::query()->count())->toBe(1);
});

it('creates an admin order with a new address that relies on the customer phone', function () {
    $accessToken = orderAdminToken();
    $customer = Customer::factory()->create();
    $service = Service::factory()->fixed()->create([
        'base_price' => '100.00',
        'is_active' => true,
        'is_available' => true,
        'is_attachment_required' => false,
    ]);

    $response = $this->postJson('/api/v1/admin/orders', [
        'customerId' => $customer->getKey(),
        'address' => [
            'province' => 'الدقهلية',
            'city' => 'المنصورة',
            'address' => 'شارع الإمام مالك',
        ],
        'orderPlace' => OrderPlace::WEBSITE->value,
        'discountType' => 1,
        'discountValue' => 2,
        'discountReason' => 'any thing',
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 22,
            ],
        ],
    ], orderAdminHeaders($accessToken, 'ar'));

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.customerId', $customer->getKey())
        ->assertJsonPath('data.addressSnapshot.province', 'الدقهلية')
        ->assertJsonPath('data.addressSnapshot.city', 'المنصورة')
        ->assertJsonPath('data.addressSnapshot.address', 'شارع الإمام مالك')
        ->assertJsonPath('data.discount.type', 1)
        ->assertJsonPath('data.discount.value', '2.00')
        ->assertJsonPath('data.total', '2156.00');

    $savedAddress = $customer->addresses()->sole();

    expect($savedAddress->province)->toBe('الدقهلية')
        ->and($savedAddress->getAttributes())->not->toHaveKeys(['phone', 'phone_normalized']);
});

it('requires the order item attachments permission when admin create includes attachments', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);

    $admin = User::factory()->administrator()->create([
        'email' => 'orders-no-attachments@example.test',
        'password' => Hash::make('Password123!'),
    ]);
    $admin->givePermissionTo('orders.create', 'order-items.create');
    Sanctum::actingAs($admin);

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '80.00',
    ]);

    $response = $this->post('/api/v1/admin/orders', [
        'customer' => [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'phone' => '01001234567',
        ],
        'address' => [
            'province' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Building 9',
        ],
        'orderPlace' => (string) OrderPlace::WEBSITE->value,
        'items' => [
            [
                'serviceId' => (string) $service->getKey(),
                'quantity' => '1',
                'attachments' => [
                    UploadedFile::fake()->image('proof.png'),
                ],
            ],
        ],
    ], [
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('rejects admin order creation when a service requires an attachment and its item has none', function () {
    $accessToken = orderAdminToken();

    $customer = Customer::factory()->create();
    $service = Service::factory()->create([
        'is_active' => true,
        'is_available' => true,
        'is_attachment_required' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '80.00',
    ]);

    $this->postJson('/api/v1/admin/orders', [
        'customerId' => $customer->getKey(),
        'orderPlace' => OrderPlace::WEBSITE->value,
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 1,
            ],
        ],
    ], orderAdminHeaders($accessToken))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'REQUIRED_SERVICE_ATTACHMENT_MISSING');

    expect(Order::query()->count())->toBe(0);
});

it('updates an editable admin order and can delegate an allowed status change through patch', function () {
    $accessToken = orderAdminToken();
    $adminId = User::query()->value('id');

    $originalCustomer = Customer::factory()->create([
        'name' => 'Original Customer',
        'email' => 'original@example.com',
        'phone' => '01001234567',
        'phone_normalized' => '01001234567',
    ]);

    $replacementCustomer = Customer::factory()->create([
        'name' => 'Replacement Customer',
        'email' => 'replacement@example.com',
        'phone' => '01005554444',
        'phone_normalized' => '01005554444',
    ]);

    $replacementAddress = $replacementCustomer->addresses()->create([
        'province' => 'Giza',
        'city' => 'Dokki',
        'address' => 'Street 99',
        'notes' => null,
        'address_hash' => hash('sha256', 'giza|dokki|street 99'),
        'is_default' => true,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $originalCustomer->getKey(),
        'customer_name' => $originalCustomer->name,
        'customer_phone' => $originalCustomer->phone_normalized,
        'customer_email' => $originalCustomer->email,
        'created_by_admin_id' => $adminId,
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
        'paid_amount' => '0.00',
        'subtotal' => '500.00',
        'total' => '500.00',
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'item_total' => '500.00',
    ]);

    $response = $this->patchJson('/api/v1/admin/orders/'.$order->getKey(), [
        'customerId' => $replacementCustomer->getKey(),
        'customerAddressId' => $replacementAddress->getKey(),
        'customerNote' => 'Updated customer note',
        'adminNote' => 'Updated admin note',
        'discountType' => 0,
        'discountValue' => '50.00',
        'discountReason' => 'Manual adjustment',
        'orderPlace' => OrderPlace::WHATSAPP->value,
        'status' => OrderStatus::CONFIRMED->value,
    ], orderAdminHeaders($accessToken));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.customerId', $replacementCustomer->getKey())
        ->assertJsonPath('data.customerSnapshot.name', 'Replacement Customer')
        ->assertJsonPath('data.customerAddressId', $replacementAddress->getKey())
        ->assertJsonPath('data.addressSnapshot.province', 'Giza')
        ->assertJsonPath('data.customerNote', 'Updated customer note')
        ->assertJsonPath('data.adminNote', 'Updated admin note')
        ->assertJsonPath('data.discount.type', 0)
        ->assertJsonPath('data.discount.value', '50.00')
        ->assertJsonPath('data.discount.amount', '50.00')
        ->assertJsonPath('data.discount.reason', 'Manual adjustment')
        ->assertJsonPath('data.orderPlace', OrderPlace::WHATSAPP->value)
        ->assertJsonPath('data.status', OrderStatus::CONFIRMED->value)
        ->assertJsonPath('data.total', '450.00');
});

it('deletes only an eligible admin-created pending unpaid order', function () {
    $accessToken = orderAdminToken();
    $adminId = User::query()->value('id');

    $order = Order::factory()->create([
        'created_by_admin_id' => $adminId,
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
        'paid_amount' => '0.00',
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->getKey(),
    ]);

    $response = $this->deleteJson('/api/v1/admin/orders/'.$order->getKey(), [], orderAdminHeaders($accessToken));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Order deleted successfully.');

    expect(Order::query()->whereKey($order->getKey())->exists())->toBeFalse();
});

it('rejects deleting a public-created or non-eligible order', function () {
    $accessToken = orderAdminToken();

    $publicOrder = Order::factory()->create([
        'created_by_admin_id' => null,
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
        'paid_amount' => '0.00',
    ]);

    OrderItem::factory()->create([
        'order_id' => $publicOrder->getKey(),
    ]);

    $response = $this->deleteJson('/api/v1/admin/orders/'.$publicOrder->getKey(), [], orderAdminHeaders($accessToken));

    $response->assertConflict()
        ->assertJsonPath('code', 'ORDER_DELETE_NOT_ALLOWED');

    expect(Order::query()->whereKey($publicOrder->getKey())->exists())->toBeTrue();
});
