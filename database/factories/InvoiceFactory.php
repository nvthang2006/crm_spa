<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'code' => fake()->unique()->bothify('HD-########'),
            'type' => 'service',
            'status' => 'pending',
            'subtotal' => 500000,
            'discount_amount' => 0,
            'total_received' => 0,
            'issued_at' => now(),
        ];
    }
}
