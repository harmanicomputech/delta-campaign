<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['name' => 'Sample User '.$this->faker->unique()->numerify('####'), 'locale' => 'en'];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->phone_hash === null) {
                $user->setPhone('080'.$this->faker->unique()->numerify('########'));
            }
        });
    }

    public function phone(string $phone): static
    {
        return $this->afterMaking(fn (User $user) => $user->setPhone($phone));
    }

    public function suspended(): static
    {
        return $this->state(fn () => [])->afterMaking(fn (User $user) => $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now()]));
    }

    /** Assigns a seeded role. $scopeId is required except for statewide roles. */
    public function withRole(string $slug, ?int $scopeId = null): static
    {
        return $this->afterCreating(function (User $user) use ($slug, $scopeId): void {
            $role = Role::query()->where('slug', $slug)->firstOrFail();
            RoleAssignment::query()->create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => $role->scope_level,
                'scope_id' => $role->scope_level === ScopeType::State ? null : $scopeId,
            ]);
            $user->flushAuthorizationCache();
        });
    }

    public function withTwoFactor(string $secret = 'JBSWY3DPEHPK3PXP'): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['AAAA-BBBB', 'CCCC-DDDD']]));
    }
}
