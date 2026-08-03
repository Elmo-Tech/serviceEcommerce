<?php

declare(strict_types=1);

use App\Enums\ContactMessages\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publicContactMessagePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mohamed Hassan',
        'email' => 'mohamed@example.com',
        'phone' => '+20 101 234 5678',
        'subject' => 'Service inquiry',
        'message' => "I would like to know more about the available services.\nPlease contact me back.",
    ], $overrides);
}

it('stores a public contact message and returns the approved created envelope', function () {
    $response = $this->postJson('/api/v1/public/contact-messages', publicContactMessagePayload(), [
        'Accept-Language' => 'en',
    ]);

    $response->assertCreated()
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null)
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('status');

    $storedMessage = ContactMessage::query()->sole();

    expect($storedMessage->name)->toBe('Mohamed Hassan')
        ->and($storedMessage->email)->toBe('mohamed@example.com')
        ->and($storedMessage->phone)->toBe('+20 101 234 5678')
        ->and($storedMessage->subject)->toBe('Service inquiry')
        ->and($storedMessage->message)->toContain('Please contact me back.')
        ->and($storedMessage->status)->toBe(ContactMessageStatus::NEW);
});

it('normalizes an empty email to null and rejects unknown public fields', function () {
    $this->postJson('/api/v1/public/contact-messages', publicContactMessagePayload([
        'email' => '   ',
    ]), [
        'Accept-Language' => 'en',
    ])->assertCreated();

    expect(ContactMessage::query()->sole()->email)->toBeNull();

    $this->postJson('/api/v1/public/contact-messages', publicContactMessagePayload([
        'status' => 'read',
    ]), [
        'Accept-Language' => 'en',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('rate limits public contact message creation after five attempts per minute from the same ip', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/public/contact-messages', publicContactMessagePayload([
            'email' => "person{$attempt}@example.com",
        ]), [
            'Accept-Language' => 'en',
        ])->assertCreated();
    }

    $this->postJson('/api/v1/public/contact-messages', publicContactMessagePayload([
        'email' => 'blocked@example.com',
    ]), [
        'Accept-Language' => 'en',
    ])->assertStatus(429)
        ->assertJsonPath('code', 'RATE_LIMITED');
});
