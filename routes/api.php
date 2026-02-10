<?php

use App\Http\Controllers\Api\PointController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Middleware\AuthenticateTelegram;
use Illuminate\Support\Facades\Route;

Route::get('/points', [PointController::class, 'index']);

Route::middleware(AuthenticateTelegram::class)->group(function () {
    Route::post('/points', [PointController::class, 'store']);
    Route::put('/points/{point}', [PointController::class, 'update']);
    Route::delete('/points/{point}', [PointController::class, 'destroy']);

    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
});
