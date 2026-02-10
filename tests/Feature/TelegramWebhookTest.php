<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('start command creates user and responds', function () {
    Http::fake();

    $response = $this->postJson('/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 123456],
            'from' => [
                'id' => 987654,
                'first_name' => 'TestUser',
            ],
            'text' => '/start',
        ],
    ]);

    $response->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('users', [
        'telegram_id' => 987654,
        'first_name' => 'TestUser',
    ]);

    Http::assertSentCount(1);
});

test('location message creates subscription', function () {
    Http::fake();

    User::factory()->create(['telegram_id' => 111222]);

    $response = $this->postJson('/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 111222],
            'from' => [
                'id' => 111222,
                'first_name' => 'LocationUser',
            ],
            'location' => [
                'latitude' => 48.4647,
                'longitude' => 35.0461,
            ],
        ],
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('subscriptions', [
        'radius_km' => 5,
    ]);
});
