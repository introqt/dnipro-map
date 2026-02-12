<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTelegram
{
    public function handle(Request $request, Closure $next): Response
    {
        $telegramId = $request->header('X-Telegram-Id') ?? $request->query('telegram_id');

        if (! $telegramId) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram ID is required.',
            ], 401);
        }

        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['first_name' => 'User']
        );

        if ($user->role !== UserRole::Admin && $this->isConfiguredAdmin($telegramId)) {
            $user->update(['role' => UserRole::Admin]);
        }

        auth()->login($user);

        return $next($request);
    }

    private function isConfiguredAdmin(string|int $telegramId): bool
    {
        $adminEnv = config('services.telegram.admin_id');

        if (! $adminEnv) {
            return false;
        }

        $adminIds = array_filter(array_map('trim', explode(',', $adminEnv)));

        return in_array((string) $telegramId, $adminIds, true);
    }
}
