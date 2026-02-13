<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'telegram_id' => fake()->unique()->numberBetween(100000, 999999999),
            'first_name' => fake()->firstName(),
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Banned,
            'banned_at' => now(),
            'ban_reason' => 'Violated community guidelines',
        ]);
    }

    public function muted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Muted,
            'banned_at' => now(),
            'ban_reason' => 'Spamming',
        ]);
    }
}
