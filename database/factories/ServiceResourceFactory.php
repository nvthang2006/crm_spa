<?php

namespace Database\Factories;

use App\Models\ResourceArea;
use App\Models\ServiceResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceResource>
 */
class ServiceResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_area_id' => ResourceArea::factory(),
            'code' => fake()->unique()->bothify('R-###'),
            'name' => 'Giường '.fake()->unique()->numerify('##'),
            'resource_type' => 'bed',
            'status' => 'ready',
        ];
    }
}
