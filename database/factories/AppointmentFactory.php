<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'offering_id' => Offering::factory(),
            'primary_employee_id' => Employee::factory(),
            'code' => fake()->unique()->bothify('LH-########'),
            'appointment_date' => today(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
            'source' => 'booked',
            'payment_type' => 'service',
        ];
    }
}
