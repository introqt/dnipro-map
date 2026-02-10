<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function webhook(Request $request, TelegramService $telegramService): JsonResponse
    {
        $telegramService->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
