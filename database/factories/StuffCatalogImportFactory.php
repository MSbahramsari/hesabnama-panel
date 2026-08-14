<?php

namespace Database\Factories;

use App\Models\StuffCatalogImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StuffCatalogImport>
 */
class StuffCatalogImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'file_name' => 'official-stuff-'.fake()->unique()->numberBetween(1, 9999).'.csv',
            'status' => StuffCatalogImport::STATUS_COMPLETED,
            'new_rows' => fake()->numberBetween(1, 100),
            'updated_rows' => fake()->numberBetween(0, 30),
            'unchanged_rows' => fake()->numberBetween(0, 100),
            'skipped_rows' => 0,
            'error_message' => null,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}
