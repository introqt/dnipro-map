<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelMessage;
use Illuminate\Http\Request;

class ChannelMessageController extends Controller
{
    public function store(Request $request)
    {
        $secret = $request->header('X-Channel-Webhook-Secret') ?? $request->query('secret');

        if (! $secret || $secret !== config('app.channel_webhook_secret')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'channel_id' => ['required', 'string'],
            'message_id' => ['required', 'integer'],
            'raw_message' => ['required', 'string'],
            'parsed_lat' => ['nullable', 'numeric'],
            'parsed_lon' => ['nullable', 'numeric'],
            'parsed_text' => ['nullable', 'string'],
            'keywords' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $channelMessage = ChannelMessage::updateOrCreate(
            ['channel_id' => $validated['channel_id'], 'message_id' => $validated['message_id']],
            [
                'raw_message' => $validated['raw_message'],
                'parsed_lat' => $validated['parsed_lat'] ?? null,
                'parsed_lon' => $validated['parsed_lon'] ?? null,
                'parsed_text' => $validated['parsed_text'] ?? null,
                'keywords' => $validated['keywords'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'data' => $channelMessage], 201);
    }
}
