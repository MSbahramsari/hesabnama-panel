<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'number' => fake()->unique()->bothify('INV-####-????'),
            'invoice_date' => today(),
            'status' => InvoiceStatus::Draft,
            'subtotal' => 10000000,
            'tax_total' => 1000000,
            'discount_total' => 0,
            'total' => 11000000,
        ];
    }
}
