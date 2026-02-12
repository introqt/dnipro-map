<?php

use App\Models\Point;
use App\Models\User;

test('index returns all active points publicly', function () {
    Point::factory(3)->create();

    $response = $this->getJson('/api/points');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

test('store creates a point for admin', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->postJson('/api/points', [
            'latitude' => 48.4647,
            'longitude' => 35.0461,
            'description' => 'Test danger point',
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.description', 'Test danger point');

    $this->assertDatabaseHas('points', ['description' => 'Test danger point']);
});

test('store rejects non-admin user', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson('/api/points', [
            'latitude' => 48.4647,
            'longitude' => 35.0461,
            'description' => 'Should fail',
        ]);

    $response->assertForbidden();
});

test('store rejects unauthenticated request', function () {
    $response = $this->postJson('/api/points', [
        'latitude' => 48.4647,
        'longitude' => 35.0461,
        'description' => 'Should fail',
    ]);

    $response->assertUnauthorized();
});

test('update modifies an existing point', function () {
    $admin = User::factory()->admin()->create();
    $point = Point::factory()->create(['user_id' => $admin->id]);

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->putJson("/api/points/{$point->id}", [
            'description' => 'Updated description',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.description', 'Updated description');
});

test('destroy deletes a point', function () {
    $admin = User::factory()->admin()->create();
    $point = Point::factory()->create(['user_id' => $admin->id]);

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->deleteJson("/api/points/{$point->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('points', ['id' => $point->id]);
});

test('store promotes user to admin when telegram id matches config', function () {
    $user = User::factory()->create(['role' => 'user']);

    config(['services.telegram.admin_id' => (string) $user->telegram_id]);

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson('/api/points', [
            'latitude' => 48.4647,
            'longitude' => 35.0461,
            'description' => 'Admin via config',
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.description', 'Admin via config');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'admin',
    ]);
});

test('store validates latitude bounds', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->postJson('/api/points', [
            'latitude' => 50.0,
            'longitude' => 35.0461,
            'description' => 'Out of bounds',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('latitude');
});
