<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LoginEvent;
use App\Models\OtpChallenge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('auth:prune {--login-days=365 : Keep login history this many days}')]
#[Description('Delete expired one-time-code challenges and old login history')]
final class PruneAuthenticationData extends Command
{
    public function handle(): int
    {
        $challenges = OtpChallenge::query()->where('expires_at', '<', now()->subDay())->delete();
        $events = LoginEvent::query()->where('created_at', '<', now()->subDays((int) $this->option('login-days')))->delete();

        $this->info("Pruned {$challenges} challenges and {$events} login events.");

        return self::SUCCESS;
    }
}
