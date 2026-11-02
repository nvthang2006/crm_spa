<?php

namespace Database\Factories;

use App\Models\CommissionEntry;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionEntry>
 */
class CommissionEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'basis' => 'payment',
            'calculation_type' => 'percentage',
            'rate' => 10,
            'amount' => 50000,
            'status' => 'pending',
            'occurred_at' => now(),
        ];
    }
}
