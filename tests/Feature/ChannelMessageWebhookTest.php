<?php

use App\Models\ChannelMessage;

test('store rejects unauthorized requests', function () {
    $response = $this->postJson('/api/channel-messages', [
        'channel_id' => 'chan1',
        'message_id' => 1,
        'raw_message' => 'test',
    ]);

    $response->assertStatus(401);
});

test('store saves message with secret', function () {
    putenv('CHANNEL_WEBHOOK_SECRET=sekret');

    $payload = [
        'channel_id' => 'chan1',
        'message_id' => 123,
        'raw_message' => '48.4647,35.0461 Danger near bridge',
        'parsed_lat' => 48.4647,
        'parsed_lon' => 35.0461,
        'keywords' => ['danger','bridge'],
    ];

    $response = $this->withHeaders(['X-Channel-Webhook-Secret' => 'sekret'])->postJson('/api/channel-messages', $payload);
    $response->assertCreated()->assertJsonPath('success', true);
    $this->assertDatabaseHas('channel_messages', ['channel_id' => 'chan1', 'message_id' => 123]);
});
