<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollLine>
 */
class PayrollLineFactory extends Factory
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
            'base_salary' => 7000000,
            'work_days' => 0,
            'commission_amount' => 0,
            'tips' => 0,
            'deductions' => 0,
            'net_pay' => 7000000,
            'status' => 'draft',
        ];
    }
}
