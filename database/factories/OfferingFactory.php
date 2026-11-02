<?php

namespace Database\Factories;

use App\Models\Offering;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offering>
 */
class OfferingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_category_id' => ServiceCategory::factory(),
            'code' => fake()->unique()->bothify('DV###'),
            'name' => fake('vi_VN')->words(3, true),
            'type' => 'service',
            'duration_minutes' => 60,
            'price' => 500000,
            'sessions_total' => 1,
            'status' => 'active',
            'featured' => false,
            'description' => fake('vi_VN')->sentence(),
        ];
    }
}
