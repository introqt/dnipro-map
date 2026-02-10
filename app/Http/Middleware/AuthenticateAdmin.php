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

        if ($telegramId) {
            $user = User::where('telegram_id', $telegramId)->first();

            if ($user?->isAdmin()) {
                return $next($request);
            }
        }

        return redirect('/admin/login');
    }
}
