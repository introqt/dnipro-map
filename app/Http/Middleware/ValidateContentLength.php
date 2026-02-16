<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidateContentLength
{
    public function handle(Request $request, Closure $next)
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        $postMax = $this->parsePhpSize(ini_get('post_max_size') ?: '8M');

        if ($contentLength > 0 && $contentLength > $postMax) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Request entity too large. Maximum allowed POST size is ' . $this->formatBytes($postMax) . '.',
            ], 413);
        }

        return $next($request);
    }

    private function parsePhpSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) return (int)($bytes / (1024 * 1024 * 1024)) . 'G';
        if ($bytes >= 1024 * 1024) return (int)($bytes / (1024 * 1024)) . 'M';
        if ($bytes >= 1024) return (int)($bytes / 1024) . 'K';

        return (string) $bytes;
    }
}
