<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Member,
            'plan' => Plan::Business,
            'permissions' => ['customers', 'goods', 'invoices'],
            'license_expires_at' => now()->addYear(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin, 'plan' => Plan::Enterprise, 'permissions' => []]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['license_expires_at' => now()->subDay()]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
