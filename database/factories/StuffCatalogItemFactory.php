<?php

namespace Database\Factories;

use App\Models\StuffCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StuffCatalogItem>
 */
class StuffCatalogItemFactory extends Factory
{
    protected $model = StuffCatalogItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemId = fake()->unique()->numerify('#############');
        $description = fake()->words(4, true);
        $type = fake()->randomElement(['عمومی داخل', 'عمومی خدمت', 'اختصاصی داخل']);
        $effectiveDate = '1405/01/01';

        return [
            'item_id' => $itemId,
            'description' => $description,
            'type' => $type,
            'vat' => fake()->randomElement([0, 10]),
            'source_created_date' => '1404/12/01',
            'effective_date' => $effectiveDate,
            'expiration_date' => null,
            'source_updated_date' => '1405/01/01',
            'source_hash' => hash('sha256', Str::of($itemId)->append('|', $description, '|', $type, '|', $effectiveDate)->toString()),
        ];
    }
}
