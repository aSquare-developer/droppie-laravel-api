<?php

namespace Database\Factories;

use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'start_address' => $this->faker->address(),
            'end_address' => $this->faker->address(),
            'distance_km' => $this->faker->numberBetween(1, 1000),
            'comment' => $this->faker->sentence(),
        ];
    }
}
