<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password 
                            {identifier : Email or Telegram ID of the user}
                            {--password= : New password (if not provided, a random one will be generated)}';

    protected $description = 'Reset a user\'s password by email or Telegram ID';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');
        $newPassword = $this->option('password');

        // Find user by email or telegram_id
        $user = is_numeric($identifier)
            ? User::where('telegram_id', (int) $identifier)->first()
            : User::where('email', $identifier)->first();

        if (! $user) {
            $this->error("User not found with identifier: {$identifier}");

            return self::FAILURE;
        }

        // Generate random password if not provided
        if (! $newPassword) {
            $newPassword = Str::password(12);
            $this->info('Generated random password');
        }

        // Update password
        $user->password = Hash::make($newPassword);
        $user->save();

        $this->newLine();
        $this->info('Password reset successfully!');
        $this->newLine();
        $this->line("User: {$user->email} (Telegram ID: {$user->telegram_id})");
        $this->line("New password: <fg=green>{$newPassword}</>");
        $this->newLine();

        return self::SUCCESS;
    }
}
