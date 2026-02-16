<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserStatus
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->status === UserStatus::Banned) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been banned.',
            ], 403);
        }

        if ($user->status === UserStatus::Muted && in_array($request->method(), self::WRITE_METHODS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been muted. You cannot perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
