<?php

namespace Database\Factories;

use App\Enums\PointStatus;
use App\Enums\PointType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Point>
 */
class PointFactory extends Factory
{
    public function definition(): array
    {
        $mediaCount = fake()->numberBetween(0, 3);
        $media = [];
        
        for ($i = 0; $i < $mediaCount; $i++) {
            $media[] = fake()->randomElement([
                fake()->imageUrl(640, 480, 'danger', true),
                'videos/' . fake()->uuid() . '.mp4',
            ]);
        }

        return [
            'user_id' => User::factory(),
            'latitude' => fake()->latitude(48.35, 48.60),
            'longitude' => fake()->longitude(34.90, 35.15),
            'description' => fake()->sentence(),
            'media' => $media,
            'status' => PointStatus::Active,
            'type' => fake()->randomElement(PointType::cases()),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PointStatus::Archived,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PointStatus::Pending,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PointStatus::Rejected,
            'rejection_reason' => 'Does not meet community standards',
        ]);
    }
}
