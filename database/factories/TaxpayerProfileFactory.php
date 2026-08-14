<?php

namespace Database\Factories;

use App\Models\TaxpayerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use phpseclib3\Crypt\RSA;

/**
 * @extends Factory<TaxpayerProfile>
 */
class TaxpayerProfileFactory extends Factory
{
    private static ?string $privateKey = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'taxpayer_name' => fake()->company(),
            'taxpayer_type' => 'legal',
            'national_id' => fake()->unique()->numerify('140########'),
            'economic_code' => fake()->unique()->numerify('41##########'),
            'fiscal_id' => fake()->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'branch_code' => null,
            'private_key' => self::$privateKey ??= RSA::createKey(2048)->toString('PKCS8'),
            'connection_verified_at' => null,
        ];
    }
}
