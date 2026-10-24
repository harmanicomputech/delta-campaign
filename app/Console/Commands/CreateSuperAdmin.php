<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ScopeType;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Bootstraps the first super administrator (and recovers access in an emergency). Audited. */
#[Signature('campaign:create-admin {phone : Nigerian mobile number} {name : Full name}')]
#[Description('Create a super administrator account (or grant the role to an existing account)')]
final class CreateSuperAdmin extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $e164 = NigerianPhoneNumber::normalise((string) $this->argument('phone'));

        if ($e164 === null) {
            $this->error('Enter a valid Nigerian mobile number.');

            return self::FAILURE;
        }

        $role = Role::query()->where('slug', 'super_admin')->first();

        if ($role === null) {
            $this->error('Roles are not seeded. Run: php artisan db:seed');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($e164, $role, $audit): User {
            $user = User::findByPhone($e164) ?? (new User(['name' => (string) $this->argument('name')]))->setPhone($e164);
            $user->save();

            $hasRole = $user->activeAssignments()->where('role_id', $role->id)->exists();

            if (! $hasRole) {
                RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::State, 'scope_id' => null]);
            }

            $audit->as('console', fn () => $audit->record('user.super_admin_granted', $user, context: ['via' => 'campaign:create-admin']));

            return $user;
        });

        $this->info("Super administrator ready: {$user->name} ({$user->maskedPhone()}). They will set up two-step verification at first sign-in.");

        return self::SUCCESS;
    }
}
