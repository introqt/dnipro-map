<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Point;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEnv = config('services.telegram.admin_id');

        if ($adminEnv) {
            $adminIds = array_filter(array_map('trim', explode(',', $adminEnv)));
            foreach ($adminIds as $adminId) {
                $user = User::firstOrNew(['telegram_id' => (int) $adminId]);
                $user->first_name = 'Admin';
                $user->password = Hash::make('qweqwe33');
                $user->role = UserRole::Admin;
                
                // Set email for Filament login if not already set
                if (! $user->email) {
                    $user->email = 'nikita.kolotilo@gmail.com';
                }
                
                $user->save();
            }
        }

        $admin = User::where('role', UserRole::Admin)->first()
            ?? User::factory()->admin()->create();

        Point::factory(5)->create([
            'user_id' => $admin->id,
            'created_at' => now()->subMinutes(rand(10, 250)),
        ]);
    }
}
