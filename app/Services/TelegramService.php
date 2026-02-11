<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    private string $token;

    private string $apiBase;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->apiBase = "https://api.telegram.org/bot{$this->token}";
    }

    public function setWebhook(string $url): array
    {
        $response = Http::post("{$this->apiBase}/setWebhook", [
            'url' => $url,
        ]);

        return $response->json();
    }

    public function sendMessage(int $chatId, string $text, array $keyboard = []): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if (! empty($keyboard)) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        Http::post("{$this->apiBase}/sendMessage", $payload);
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'] ?? [];

        $user = $this->resolveUser($from);

        if ($text === '/start') {
            $this->handleStart($chatId, $user);

            return;
        }

        if (isset($message['location'])) {
            $this->handleLocation($chatId, $user, $message['location']);

            return;
        }

        // Handle unsubscribe button text
        if (stripos($text, 'unsubscribe') !== false || $text === '🔕 Unsubscribe') {
            // Remove all subscriptions for the user
            $user->subscriptions()->delete();

            $this->sendMessage($chatId, '✅ Unsubscribed.');

            return;
        }
    }

    private function resolveUser(array $from): User
    {
        $adminTelegramId = (int) config('services.telegram.admin_id');

        return User::updateOrCreate(
            ['telegram_id' => $from['id']],
            [
                'first_name' => $from['first_name'] ?? 'Unknown',
                'role' => $from['id'] === $adminTelegramId ? UserRole::Admin : UserRole::User,
            ]
        );
    }

    private function handleStart(int $chatId, User $user): void
    {
        $webAppUrl = config('services.telegram.web_app_url');
        $mapUrl = $webAppUrl.'?telegram_id='.$user->telegram_id;
        $greeting = "Welcome, {$user->first_name}! 👋\n\nUse the buttons below to interact with the map.";

        $rows = [
            [
                [
                    'text' => '📍 Map',
                    'web_app' => ['url' => $mapUrl],
                ],
            ],
        ];

        // Show Subscribe or Unsubscribe depending on whether the user has a subscription
        if ($user->subscriptions()->exists()) {
            $rows[] = [
                [
                    'text' => '🔕 Unsubscribe',
                ],
            ];
        } else {
            $rows[] = [
                [
                    'text' => '🔔 Subscribe',
                    'request_location' => true,
                ],
            ];
        }

        if ($user->isAdmin()) {
            $adminUrl = preg_replace('#/app$#', '/admin', $webAppUrl);
            $adminUrl .= '?telegram_id='.$user->telegram_id;
            $rows[] = [
                [
                    'text' => '🛠 Admin Panel',
                    'web_app' => ['url' => $adminUrl],
                ],
            ];
        }

        $keyboard = [
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ];

        $this->sendMessage($chatId, $greeting, $keyboard);
    }

    private function handleLocation(int $chatId, User $user, array $location): void
    {
        $defaultRadiusKm = 5;

        $user->subscriptions()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'radius_km' => $defaultRadiusKm,
            ]
        );

        $this->sendMessage(
            $chatId,
            "✅ Subscribed! You'll receive notifications about danger points within {$defaultRadiusKm} km of your location."
        );
    }
}
