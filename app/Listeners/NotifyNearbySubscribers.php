<?php

namespace App\Listeners;

use App\Events\PointCreated;
use App\Models\Subscription;
use App\Services\TelegramService;

class NotifyNearbySubscribers
{
    private const EARTH_RADIUS_KM = 6371;

    public function handle(PointCreated $event): void
    {
        $point = $event->point;
        $telegram = app(TelegramService::class);
        $webAppUrl = config('services.telegram.web_app_url');

        Subscription::with('user')->chunkById(100, function ($subscriptions) use ($point, $telegram, $webAppUrl) {
            foreach ($subscriptions as $subscription) {
                $distance = $this->haversineDistance(
                    (float) $subscription->latitude,
                    (float) $subscription->longitude,
                    (float) $point->latitude,
                    (float) $point->longitude
                );

                if ($distance > $subscription->radius_km) {
                    continue;
                }

                $distanceFormatted = round($distance, 1);
                $text = "⚠️ <b>New danger point nearby!</b>\n\n"
                    ."{$point->description}\n"
                    ."📏 {$distanceFormatted} km from your location";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '📍 View on Map',
                                'web_app' => ['url' => $webAppUrl],
                            ],
                        ],
                    ],
                ];

                $telegram->sendMessage($subscription->user->telegram_id, $text, $keyboard);
            }
        });
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
