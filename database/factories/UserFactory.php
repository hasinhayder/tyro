<?php

namespace HasinHayder\Tyro\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\Illuminate\Contracts\Auth\Authenticatable>
 */
class UserFactory extends Factory {
    protected static ?string $password = null;

    public function modelName(): string {
        return config('tyro.models.user', 'App\\Models\\User');
    }

    public function definition(): array {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
