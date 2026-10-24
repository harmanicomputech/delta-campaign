<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupporterSource;
use App\Models\Supporter;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supporter> */
class SupporterFactory extends Factory
{
    protected $model = Supporter::class;

    public function definition(): array
    {
        return [
            'full_name' => 'Sample Supporter '.$this->faker->unique()->numerify('#####'),
            'ward_id' => Ward::factory(),
            'lga_id' => fn (array $attributes) => Ward::query()->find($attributes['ward_id'])?->lga_id,
            'source' => SupporterSource::Staff,
            'preferred_language' => 'en',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Supporter $supporter): void {
            if ($supporter->phone_hash === null && $supporter->erased_at === null) {
                $supporter->setPhone('081'.$this->faker->unique()->numerify('########'));
            }
            $supporter->adult_confirmed_at ??= now();
            $supporter->is_sample = true;
        });
    }

    public function phone(string $phone): static
    {
        return $this->afterMaking(fn (Supporter $s) => $s->setPhone($phone));
    }
}
