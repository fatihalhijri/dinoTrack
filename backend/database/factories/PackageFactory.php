<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $speed = fake()->randomElement([10, 20, 30, 50, 100]);

        return [
            'name' => "Home {$speed} Mbps",
            'speed_label' => "{$speed} Mbps",
            'price' => fake()->randomElement([100_000, 150_000, 200_000, 250_000, 350_000]),
            'mikrotik_profile' => "HOME-{$speed}M",
            'is_active' => true,
            'description' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
