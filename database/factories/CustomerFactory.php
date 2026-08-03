<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'economic_code' => fake()->unique()->numerify('4###########'),
            'national_id' => fake()->numerify('1##########'),
            'name' => fake()->company(),
            'type' => 'legal',
            'address' => fake()->address(),
            'postal_code' => fake()->numerify('##########'),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
