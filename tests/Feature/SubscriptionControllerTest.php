<?php

use App\Models\User;

test('store creates a subscription', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson('/api/subscriptions', [
            'latitude' => 48.4647,
            'longitude' => 35.0461,
            'radius_km' => 5,
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.radius_km', 5);

    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $user->id,
        'radius_km' => 5,
    ]);
});

test('store updates existing subscription for same user', function () {
    $user = User::factory()->create();

    $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson('/api/subscriptions', [
            'latitude' => 48.4647,
            'longitude' => 35.0461,
            'radius_km' => 5,
        ]);

    $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson('/api/subscriptions', [
            'latitude' => 48.4700,
            'longitude' => 35.0500,
            'radius_km' => 10,
        ]);

    $this->assertDatabaseCount('subscriptions', 1);
    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $user->id,
        'radius_km' => 10,
    ]);
});

test('store rejects unauthenticated request', function () {
    $response = $this->postJson('/api/subscriptions', [
        'latitude' => 48.4647,
        'longitude' => 35.0461,
        'radius_km' => 5,
    ]);

    $response->assertUnauthorized();
});
