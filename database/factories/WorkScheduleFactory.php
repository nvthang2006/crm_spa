<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
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
            'work_date' => today(),
            'shift_name' => 'Ca sáng',
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'status' => 'scheduled',
        ];
    }
}
