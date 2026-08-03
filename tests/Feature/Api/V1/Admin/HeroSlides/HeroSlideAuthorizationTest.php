<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\HeroSlide;
use App\Models\User;
use Database\Seeders\HeroSlidesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HeroSlidesPermissionsSeeder::class);
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroPermissionAdmin(bool $active = true): User
{
    return User::factory()->create(['type' => UserType::ADMIN, 'is_active' => $active]);
}

function heroPermissionCreatePayload(array $overrides = []): array
{
    return array_merge([
        'titleAr' => 'عنوان', 'titleEn' => 'Title',
        'descriptionAr' => 'وصف', 'descriptionEn' => 'Description',
        'image' => UploadedFile::fake()->image('hero.png'), 'isActive' => '1',
    ], $overrides);
}

it('requires authentication and rejects non-admin and inactive identities before permissions', function () {
    $this->getJson('/api/v1/admin/hero-slides')->assertUnauthorized();

    $nonAdmin = User::factory()->create(['type' => UserType::ADMIN, 'is_active' => true]);
    DB::table('users')->where('id', $nonAdmin->getKey())->update(['type' => 1]);
    $nonAdmin->refresh();
    $nonAdmin->givePermissionTo('hero-slides.view');
    Sanctum::actingAs($nonAdmin);
    $this->getJson('/api/v1/admin/hero-slides')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    $inactive = heroPermissionAdmin(false);
    $inactive->givePermissionTo('hero-slides.view');
    Sanctum::actingAs($inactive);
    $this->getJson('/api/v1/admin/hero-slides')->assertForbidden()->assertJsonPath('code', 'USER_INACTIVE');
});

it('returns forbidden when each exact operation permission is missing', function (string $method, string $uri, array $payload) {
    Sanctum::actingAs(heroPermissionAdmin());
    HeroSlide::factory()->atPosition(1)->create(['id' => 1]);

    $this->json($method, $uri, $payload)->assertForbidden();
})->with([
    ['GET', '/api/v1/admin/hero-slides', []],
    ['POST', '/api/v1/admin/hero-slides', []],
    ['GET', '/api/v1/admin/hero-slides/1', []],
    ['PATCH', '/api/v1/admin/hero-slides/1', ['titleEn' => 'Updated']],
    ['DELETE', '/api/v1/admin/hero-slides/1', []],
]);

it('allows each operation with only its exact permission', function (string $permission, string $method, string $uri, array $payload, int $status) {
    $admin = heroPermissionAdmin();
    $admin->givePermissionTo($permission);
    Sanctum::actingAs($admin);

    if (str_contains($uri, '/1')) {
        HeroSlide::factory()->atPosition(1)->create(['id' => 1]);
    }

    if (array_key_exists('__create', $payload)) {
        $payload = heroPermissionCreatePayload();
    }

    $this->call($method, $uri, $payload, [], [], ['HTTP_ACCEPT' => 'application/json'])
        ->assertStatus($status);
})->with([
    ['hero-slides.view', 'GET', '/api/v1/admin/hero-slides', [], 200],
    ['hero-slides.view', 'GET', '/api/v1/admin/hero-slides/1', [], 200],
    ['hero-slides.create', 'POST', '/api/v1/admin/hero-slides', ['__create' => true], 201],
    ['hero-slides.update', 'PATCH', '/api/v1/admin/hero-slides/1', ['titleEn' => 'Updated'], 200],
    ['hero-slides.delete', 'DELETE', '/api/v1/admin/hero-slides/1', [], 200],
]);

it('does not let an exact permission bypass validation or ordering rules', function () {
    $admin = heroPermissionAdmin();
    $admin->givePermissionTo('hero-slides.create');
    Sanctum::actingAs($admin);

    $this->post('/api/v1/admin/hero-slides', heroPermissionCreatePayload(['isActive' => 'true']))
        ->assertUnprocessable();
});
