<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'receipt_code' => fake()->unique()->bothify('PT-########'),
            'method' => 'cash',
            'amount' => 500000,
            'status' => 'completed',
            'paid_at' => now(),
        ];
    }
}
