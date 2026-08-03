<?php

namespace Database\Factories;

use App\Models\Good;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Good> */
class GoodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commodity_code' => fake()->unique()->numerify('1#######'),
            'name' => fake()->words(3, true),
            'unit' => 'عدد',
            'measurement_unit_code' => '1627',
            'unit_price' => fake()->numberBetween(1000000, 100000000),
            'tax_rate' => 10,
            'is_active' => true,
        ];
    }
}
