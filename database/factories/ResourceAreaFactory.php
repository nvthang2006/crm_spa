<?php

namespace Database\Factories;

use App\Models\ResourceArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceArea>
 */
class ResourceAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('AREA-###'),
            'name' => 'Khu '.fake('vi_VN')->word(),
            'floor' => 'Tầng 1',
            'description' => fake('vi_VN')->sentence(),
            'status' => 'active',
        ];
    }
}
