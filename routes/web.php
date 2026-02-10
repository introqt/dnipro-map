<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app'));

Route::get('/app', fn () => view('app'));
Route::get('/admin', fn () => view('admin'));

Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
