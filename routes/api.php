<?php

use App\Http\Controllers\Api\PointController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ChannelMessageController;
use App\Http\Middleware\AuthenticateTelegram;
use Illuminate\Support\Facades\Route;

Route::get('/points', [PointController::class, 'index']);
Route::get('/points/{point}/comments', [CommentController::class, 'index']);

Route::post('/channel-messages', [ChannelMessageController::class, 'store']);

Route::middleware(AuthenticateTelegram::class)->group(function () {
    Route::post('/points', [PointController::class, 'store']);
    Route::put('/points/{point}', [PointController::class, 'update']);
    Route::delete('/points/{point}', [PointController::class, 'destroy']);

    Route::get('/subscriptions', [SubscriptionController::class, 'show']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions', [SubscriptionController::class, 'destroy']);

    Route::post('/upload', [UploadController::class, 'store']);

    Route::post('/points/{point}/vote', [VoteController::class, 'store']);
    Route::post('/points/{point}/comments', [CommentController::class, 'store']);
});
