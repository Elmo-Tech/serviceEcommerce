<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function dashboardHeaders(): array
{
    $token = (string) loginAdminForTests()->json('data.accessToken');

    return [
        'Authorization' => 'Bearer '.$token,
        'Accept-Language' => 'en',
    ];
}

it('returns the complete dashboard contract with status isolated to order count', function (): void {
    Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'total' => '100.00',
        'paid_amount' => '20.00',
        'created_at' => '2026-08-02 01:00:00',
        'updated_at' => '2026-08-02 01:00:00',
    ]);
    Order::factory()->create([
        'status' => OrderStatus::COMPLETED,
        'total' => '200.00',
        'paid_amount' => '150.00',
        'completed_at' => '2026-08-02 02:00:00',
        'created_at' => '2026-08-02 02:00:00',
        'updated_at' => '2026-08-02 02:00:00',
    ]);
    Order::factory()->create([
        'status' => OrderStatus::CANCELLED,
        'total' => '300.00',
        'paid_amount' => '300.00',
        'created_at' => '2026-08-02 03:00:00',
        'updated_at' => '2026-08-02 03:00:00',
    ]);
    Order::factory()->create([
        'status' => OrderStatus::COMPLETED,
        'total' => '50.00',
        'paid_amount' => '50.00',
        'completed_at' => '2026-07-15 02:00:00',
        'created_at' => '2026-07-15 02:00:00',
        'updated_at' => '2026-07-15 02:00:00',
    ]);

    $response = $this->getJson(
        '/api/v1/admin/dashboard?filter[status]=0',
        dashboardHeaders(),
    );

    $response->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.salesPeriod.dateFrom', '2026-08-01')
        ->assertJsonPath('data.salesPeriod.dateTo', '2026-08-31')
        ->assertJsonPath('data.sales.total', '350.00')
        ->assertJsonPath('data.sales.today', '300.00')
        ->assertJsonPath('data.sales.period', '300.00')
        ->assertJsonPath('data.collectedSales.total', '200.00')
        ->assertJsonPath('data.collectedSales.today', '150.00')
        ->assertJsonPath('data.collectedSales.period', '150.00')
        ->assertJsonPath('data.uncollectedSales.total', '130.00')
        ->assertJsonPath('data.uncollectedSales.today', '130.00')
        ->assertJsonMissingPath('data.uncollectedSales.period')
        ->assertJsonPath('data.orders.status', 0)
        ->assertJsonPath('data.orders.count', 1)
        ->assertJsonCount(6, 'data.performance')
        ->assertJsonPath('data.performance.4.month', '2026-07')
        ->assertJsonPath('data.performance.4.sales', '50.00')
        ->assertJsonPath('data.performance.5.month', '2026-08')
        ->assertJsonPath('data.performance.5.sales', '300.00');
});

it('rejects malformed and repeated deep-object filters', function (string $query): void {
    $this->getJson('/api/v1/admin/dashboard?'.$query, dashboardHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
})->with([
    'unknown top-level key' => 'status=1',
    'unknown nested key' => 'filter[unknown]=1',
    'array-shaped status' => 'filter[status][]=1',
    'repeated status' => 'filter[status]=1&filter[status]=2',
    'empty period' => 'filter[ordersPeriod]=',
    'lexically invalid status' => 'filter[status]=03',
    'custom without dates' => 'filter[ordersPeriod]=custom',
    'reversed dates' => 'filter[dateFrom]=2026-02-01&filter[dateTo]=2026-01-01',
    'more than 366 inclusive days' => 'filter[dateFrom]=2025-01-01&filter[dateTo]=2026-01-02',
]);

it('requires authentication for dashboard analytics', function (): void {
    $this->getJson('/api/v1/admin/dashboard', ['Accept-Language' => 'en'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('enforces active administrator and dashboard permission boundaries', function (): void {
    $inactive = User::factory()->administrator()->inactive()->create();
    Sanctum::actingAs($inactive);

    $this->getJson('/api/v1/admin/dashboard', ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');

    $withoutPermission = User::factory()->administrator()->create();
    Sanctum::actingAs($withoutPermission);

    $this->getJson('/api/v1/admin/dashboard', ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('localizes performance labels and response headers in Arabic', function (): void {
    $response = $this->getJson('/api/v1/admin/dashboard', [
        ...dashboardHeaders(),
        'Accept-Language' => 'ar',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.performance.5.month', '2026-08')
        ->assertJsonPath('data.performance.5.label', __('dashboard.performance.months.august', [], 'ar').' 2026');
});
