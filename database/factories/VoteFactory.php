<?php

namespace Database\Factories;

use App\Enums\VoteType;
use App\Models\Point;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vote>
 */
class VoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'point_id' => Point::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(VoteType::cases()),
        ];
    }
}
