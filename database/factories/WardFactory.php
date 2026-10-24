<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ward> */
class WardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lga_id' => fn () => Lga::query()->inRandomOrder()->value('id'),
            'code' => 'SMP-W'.$this->faker->unique()->numerify('####'),
            'name' => 'Sample Ward '.$this->faker->unique()->numerify('####'),
            'is_sample' => true,
        ];
    }
}
