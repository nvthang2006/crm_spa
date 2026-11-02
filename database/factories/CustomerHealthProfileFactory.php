<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerHealthProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerHealthProfile>
 */
class CustomerHealthProfileFactory extends Factory
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
            'allergy_notes' => 'Không ghi nhận',
            'current_condition' => 'Ổn định',
            'contraindications' => null,
            'care_notes' => 'Theo dõi sau liệu trình',
        ];
    }
}
