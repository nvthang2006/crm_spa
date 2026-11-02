<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceRefund>
 */
class InvoiceRefundFactory extends Factory
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
            'processed_by' => User::factory(),
            'code' => 'HT-'.$this->faker->unique()->numerify('########'),
            'method' => 'cash',
            'amount' => 450000,
            'reference' => null,
            'reason' => 'Hoàn tiền theo yêu cầu khách hàng',
            'refunded_at' => now(),
        ];
    }
}
