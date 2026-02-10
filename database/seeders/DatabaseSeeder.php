<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Point;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminTelegramId = config('services.telegram.admin_id');

        if ($adminTelegramId) {
            User::factory()->admin()->create([
                'telegram_id' => (int) $adminTelegramId,
                'first_name' => 'Admin',
            ]);
        }

        $admin = User::where('role', UserRole::Admin)->first()
            ?? User::factory()->admin()->create();

        Point::factory(5)->create([
            'user_id' => $admin->id,
            'created_at' => now()->subMinutes(rand(10, 250)),
        ]);
    }
}
