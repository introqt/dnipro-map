<?php

use App\Models\Point;
use App\Models\User;

test('authenticated user can like a point', function () {
    $user = User::factory()->create();
    $point = Point::factory()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson("/api/points/{$point->id}/vote", ['type' => 'like']);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.likes', 1)
        ->assertJsonPath('data.dislikes', 0)
        ->assertJsonPath('data.user_vote', 'like');
});

test('voting same type again removes vote', function () {
    $user = User::factory()->create();
    $point = Point::factory()->create();

    $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson("/api/points/{$point->id}/vote", ['type' => 'like']);

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson("/api/points/{$point->id}/vote", ['type' => 'like']);

    $response->assertOk()
        ->assertJsonPath('data.likes', 0)
        ->assertJsonPath('data.user_vote', null);
});

test('voting different type switches vote', function () {
    $user = User::factory()->create();
    $point = Point::factory()->create();

    $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson("/api/points/{$point->id}/vote", ['type' => 'like']);

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson("/api/points/{$point->id}/vote", ['type' => 'dislike']);

    $response->assertOk()
        ->assertJsonPath('data.likes', 0)
        ->assertJsonPath('data.dislikes', 1)
        ->assertJsonPath('data.user_vote', 'dislike');
});

test('unauthenticated user cannot vote', function () {
    $point = Point::factory()->create();

    $response = $this->postJson("/api/points/{$point->id}/vote", ['type' => 'like']);

    $response->assertUnauthorized();
});

test('vote validates type field', function () {
    $user = User::factory()->create();
    $point = Point::factory()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $user->telegram_id])
        ->postJson("/api/points/{$point->id}/vote", ['type' => 'invalid']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

test('points index includes vote counts', function () {
    $point = Point::factory()->create();
    $user = User::factory()->create();

    $point->votes()->create(['user_id' => $user->id, 'type' => 'like']);

    $response = $this->getJson('/api/points');

    $response->assertOk()
        ->assertJsonPath('data.0.likes', 1)
        ->assertJsonPath('data.0.dislikes', 0);
});
