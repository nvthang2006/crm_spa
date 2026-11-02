<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'NV'.fake()->unique()->numerify('###'),
            'name' => fake('vi_VN')->name(),
            'phone' => fake()->numerify('09########'),
            'email' => fake()->unique()->safeEmail(),
            'job_title' => 'Kỹ thuật viên',
            'shift_name' => 'Ca sáng',
            'base_salary' => 7000000,
            'status' => 'active',
        ];
    }
}
