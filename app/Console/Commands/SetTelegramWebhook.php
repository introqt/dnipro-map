<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url?}';

    protected $description = 'Set the Telegram bot webhook URL';

    public function handle(TelegramService $telegramService): int
    {
        $url = $this->argument('url') ?? config('app.url').'/telegram/webhook';

        $result = $telegramService->setWebhook($url);

        if ($result['ok'] ?? false) {
            $this->info("Webhook set to: {$url}");

            return self::SUCCESS;
        }

        $this->error('Failed to set webhook: '.($result['description'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
