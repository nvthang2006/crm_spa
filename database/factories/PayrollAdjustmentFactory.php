<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'code' => fake()->unique()->bothify('DC-########'),
            'type' => 'bonus',
            'amount' => 100000,
            'status' => 'pending',
            'reason' => 'Thưởng hiệu suất',
        ];
    }
}
