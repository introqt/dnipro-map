<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('admin_telegram_id')) {
            return $next($request);
        }

        $telegramId = $request->header('X-Telegram-Id') ?? $request->query('telegram_id');

        // Allow admins defined in ADMIN_TELEGRAM_ID env var (comma-separated)
        $adminEnv = config('services.telegram.admin_id');
        if ($telegramId && $adminEnv) {
            $adminIds = array_filter(array_map('trim', explode(',', $adminEnv)));
            if (in_array((string) $telegramId, $adminIds, true) || in_array((int) $telegramId, $adminIds, true)) {
                $user = User::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    ['first_name' => 'Admin']
                );
                auth()->login($user);

                return $next($request);
            }
        }

        if ($telegramId) {
            $user = User::where('telegram_id', $telegramId)->first();

            if ($user?->isAdmin()) {
                return $next($request);
            }
        }

        return redirect('/admin/login');
    }
}
