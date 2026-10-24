<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PollingUnit> */
class PollingUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'code' => 'SMP-'.$this->faker->unique()->numerify('##-##-###'),
            'name' => 'Sample Polling Unit '.$this->faker->unique()->numerify('#####'),
            'latitude' => $this->faker->randomFloat(6, 5.0, 6.4),
            'longitude' => $this->faker->randomFloat(6, 5.2, 6.7),
            'is_sample' => true,
        ];
    }
}
