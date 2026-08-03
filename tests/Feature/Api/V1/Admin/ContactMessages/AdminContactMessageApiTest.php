<?php

declare(strict_types=1);

use App\Enums\ContactMessages\ContactMessageStatus;
use App\Enums\UserType;
use App\Models\ContactMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ContactMessagesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ContactMessagesPermissionsSeeder::class);
});

function contactMessageAdmin(bool $active = true): User
{
    return User::factory()->create([
        'type' => UserType::ADMIN,
        'is_active' => $active,
    ]);
}

it('enforces the approved authentication, admin-type, active-user, and permission boundaries', function () {
    $this->getJson('/api/v1/admin/contact-messages')->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $nonAdmin = contactMessageAdmin();
    DB::table('users')->where('id', $nonAdmin->getKey())->update(['type' => 1]);
    $nonAdmin->refresh();
    $nonAdmin->givePermissionTo('contact-messages.view');
    Sanctum::actingAs($nonAdmin);

    $this->getJson('/api/v1/admin/contact-messages')->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $inactiveAdmin = contactMessageAdmin(false);
    $inactiveAdmin->givePermissionTo('contact-messages.view');
    Sanctum::actingAs($inactiveAdmin);

    $this->getJson('/api/v1/admin/contact-messages')->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');

    $forbiddenAdmin = contactMessageAdmin();
    Sanctum::actingAs($forbiddenAdmin);

    $this->getJson('/api/v1/admin/contact-messages')->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('lists contact messages with the approved filters and newest-first ordering', function () {
    $admin = contactMessageAdmin();
    $admin->givePermissionTo('contact-messages.view');
    Sanctum::actingAs($admin);

    ContactMessage::factory()->create([
        'name' => 'Older Mohamed',
        'email' => 'older@example.com',
        'phone' => '01000000001',
        'subject' => 'First subject',
        'status' => ContactMessageStatus::NEW,
        'created_at' => CarbonImmutable::parse('2026-08-01 10:00:00', 'UTC'),
        'updated_at' => CarbonImmutable::parse('2026-08-01 10:00:00', 'UTC'),
    ]);

    $latestMessage = ContactMessage::factory()->read()->create([
        'name' => 'Latest Mohamed',
        'email' => 'latest@example.com',
        'phone' => '01000000002',
        'subject' => 'Second subject',
        'created_at' => CarbonImmutable::parse('2026-08-02 11:00:00', 'UTC'),
        'updated_at' => CarbonImmutable::parse('2026-08-02 11:00:00', 'UTC'),
    ]);

    $this->getJson('/api/v1/admin/contact-messages?filter[status]=read&filter[search]=mohamed&filter[date][0]=2026-08-02&page=1&perPage=15', [
        'Accept-Language' => 'en',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Latest Mohamed')
        ->assertJsonPath('data.0.status', 'read')
        ->assertJsonPath('data.0.createdAt', $latestMessage->fresh()->created_at?->toJSON())
        ->assertJsonMissingPath('data.0.message')
        ->assertJsonPath('meta.total', 1);
});

it('shows, updates, and deletes a contact message with the approved workflow rules', function () {
    $admin = contactMessageAdmin();
    $admin->givePermissionTo('contact-messages.view');
    $admin->givePermissionTo('contact-messages.update');
    $admin->givePermissionTo('contact-messages.delete');
    Sanctum::actingAs($admin);

    $contactMessage = ContactMessage::factory()->create([
        'status' => ContactMessageStatus::NEW,
        'message' => 'I would like to know more about the available services.',
    ]);

    $this->getJson('/api/v1/admin/contact-messages/'.$contactMessage->getKey(), [
        'Accept-Language' => 'en',
    ])->assertOk()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.message', 'I would like to know more about the available services.');

    expect($contactMessage->fresh()->status)->toBe(ContactMessageStatus::NEW);

    $this->patchJson('/api/v1/admin/contact-messages/'.$contactMessage->getKey(), [
        'status' => 'read',
    ], [
        'Accept-Language' => 'en',
    ])->assertOk()
        ->assertJsonPath('data.status', 'read')
        ->assertJsonPath('data.message', 'I would like to know more about the available services.');

    expect($contactMessage->fresh()->status)->toBe(ContactMessageStatus::READ);

    $this->deleteJson('/api/v1/admin/contact-messages/'.$contactMessage->getKey(), [], [
        'Accept-Language' => 'en',
    ])->assertOk()
        ->assertJsonPath('data', null);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('rejects invalid filter and update payload shapes and returns not found for missing records', function () {
    $admin = contactMessageAdmin();
    $admin->givePermissionTo('contact-messages.view');
    $admin->givePermissionTo('contact-messages.update');
    $admin->givePermissionTo('contact-messages.delete');
    Sanctum::actingAs($admin);

    $contactMessage = ContactMessage::factory()->create();

    $this->getJson('/api/v1/admin/contact-messages?filter[date][0]=2026-08-03&filter[date][1]=2026-08-01', [
        'Accept-Language' => 'en',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->patchJson('/api/v1/admin/contact-messages/'.$contactMessage->getKey(), [
        'name' => 'Changed',
    ], [
        'Accept-Language' => 'en',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->getJson('/api/v1/admin/contact-messages/999999', [
        'Accept-Language' => 'en',
    ])->assertNotFound()
        ->assertJsonPath('code', 'CONTACT_MESSAGE_NOT_FOUND');
});
