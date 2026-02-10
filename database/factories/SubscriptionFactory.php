<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'latitude' => fake()->latitude(48.35, 48.60),
            'longitude' => fake()->longitude(34.90, 35.15),
            'radius_km' => fake()->numberBetween(1, 50),
        ];
    }
}
