<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use App\Services\Authentication\SignInService;
use App\Services\Sms\Drivers\OutboxSmsGateway;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithAccounts
{
    protected function seedAccessControl(): void
    {
        $this->seed([GeographySeeder::class, RolesAndPermissionsSeeder::class]);
        OutboxSmsGateway::clear();
    }

    /** Acts as $user with a fully established workspace session. */
    protected function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession([
            SignInService::SESSION_LAST_ACTIVITY => now()->getTimestamp(),
            SignInService::SESSION_PIN_CONFIRMED => now()->getTimestamp(),
        ]);
    }

    protected function lga(string $slug): Lga
    {
        return Lga::query()->where('slug', $slug)->firstOrFail();
    }

    protected function ward(string $lgaSlug, string $code = 'W01'): Ward
    {
        return Ward::query()->firstOrCreate(['lga_id' => $this->lga($lgaSlug)->id, 'code' => $code], ['name' => "Sample {$lgaSlug} {$code}", 'is_sample' => true]);
    }

    /** The most recent one-time code sent to a number (developer outbox driver). */
    protected function lastCodeSentTo(string $e164): ?string
    {
        foreach (OutboxSmsGateway::messages() as $message) {
            if ($message['to'] === $e164 && preg_match('/\b(\d{6})\b/', $message['message'], $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    protected function messagesTo(string $e164): int
    {
        return count(array_filter(OutboxSmsGateway::messages(), fn (array $m): bool => $m['to'] === $e164));
    }
}
