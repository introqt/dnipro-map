<?php

namespace App\Http\Middleware;

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

        auth()->login($user);

        return $next($request);
    }
}
