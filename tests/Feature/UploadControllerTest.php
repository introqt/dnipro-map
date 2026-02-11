<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('upload stores file and returns url', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['url']]);

    Storage::disk('public')->assertExists('uploads/'.basename($response->json('data.url')));
});

test('upload rejects files over 5MB', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg'),
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('upload rejects non-image files', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->withHeaders(['X-Telegram-Id' => $admin->telegram_id])
        ->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('upload rejects unauthenticated requests', function () {
    Storage::fake('public');

    $response = $this->postJson('/api/upload', [
        'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertUnauthorized();
});
