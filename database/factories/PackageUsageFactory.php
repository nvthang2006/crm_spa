<?php

namespace Database\Factories;

use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageUsage>
 */
class PackageUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_package_id' => CustomerPackage::factory(),
            'session_number' => 1,
            'direction' => 'use',
            'verification_method' => 'admin_confirmation',
            'used_at' => now(),
        ];
    }
}
