<?php

namespace Database\Factories;

use App\Models\Address;
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
            'start_address_id' => Address::factory(),
            'end_address_id' => Address::factory(),
            'started_at' => $this->faker->date(),
            'distance_km' => $this->faker->numberBetween(1, 1000),
        ];
    }
}
