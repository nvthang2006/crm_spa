<?php

namespace Database\Factories;

use App\Models\CommissionRule;
use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offering_id' => Offering::factory(),
            'beneficiary_type' => 'primary_technician',
            'basis' => 'payment',
            'calculation_type' => 'percentage',
            'value' => 10,
            'starts_on' => today(),
            'is_active' => true,
        ];
    }
}
