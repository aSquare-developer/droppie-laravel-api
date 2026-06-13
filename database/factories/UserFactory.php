<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->profile()->firstOrCreate([], [
                'first_name' => fake()->firstName(),
            ]);

            $user->vehicles()->firstOrCreate(['is_active' => true]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withProfile(array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($attributes): void {
            $user->profile()->updateOrCreate([], $attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withVehicle(array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($attributes): void {
            $user->activeVehicle()->updateOrCreate([], [
                ...$attributes,
                'is_active' => true,
            ]);
        });
    }
}
