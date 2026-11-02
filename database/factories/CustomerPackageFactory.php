<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPackage>
 */
class CustomerPackageFactory extends Factory
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
            'offering_id' => Offering::factory()->state(['type' => 'package', 'sessions_total' => 12]),
            'code' => fake()->unique()->bothify('THE-########'),
            'purchased_on' => today(),
            'starts_on' => today(),
            'expires_on' => today()->addYear(),
            'sessions_total' => 12,
            'sessions_used' => 0,
            'unit_price' => 12000000,
            'status' => 'active',
        ];
    }
}
