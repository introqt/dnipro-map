<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TelegramController;
use App\Http\Middleware\AuthenticateAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app'));

Route::get('/app', fn () => view('app'));

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
Route::get('/admin', AdminController::class)->middleware(AuthenticateAdmin::class);

Route::get('/admin/channel-messages', [\App\Http\Controllers\AdminChannelMessageController::class, 'index'])->middleware(AuthenticateAdmin::class);
Route::post('/admin/channel-messages/{id}/process', [\App\Http\Controllers\AdminChannelMessageController::class, 'process'])->middleware(AuthenticateAdmin::class);
Route::post('/admin/channel-messages/{id}/destroy', [\App\Http\Controllers\AdminChannelMessageController::class, 'destroy'])->middleware(AuthenticateAdmin::class);

Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
