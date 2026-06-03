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
            'start_place_id' => 'start-place-'.$this->faker->uuid(),
            'start_postal_code' => $this->faker->postcode(),
            'start_city' => $this->faker->city(),
            'start_country' => $this->faker->country(),
            'start_country_code' => $this->faker->countryCode(),
            'start_street' => $this->faker->streetName(),
            'start_street_number' => $this->faker->buildingNumber(),
            'start_latitude' => $this->faker->latitude(),
            'start_longitude' => $this->faker->longitude(),
            'end_address' => $this->faker->address(),
            'end_place_id' => 'end-place-'.$this->faker->uuid(),
            'end_postal_code' => $this->faker->postcode(),
            'end_city' => $this->faker->city(),
            'end_country' => $this->faker->country(),
            'end_country_code' => $this->faker->countryCode(),
            'end_street' => $this->faker->streetName(),
            'end_street_number' => $this->faker->buildingNumber(),
            'end_latitude' => $this->faker->latitude(),
            'end_longitude' => $this->faker->longitude(),
            'started_at' => $this->faker->date(),
            'distance_km' => $this->faker->numberBetween(1, 1000),
        ];
    }
}
