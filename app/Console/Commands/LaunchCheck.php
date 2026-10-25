<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\LaunchReadiness;
use Illuminate\Console\Command;

/** Prints the launch-readiness checklist. Exits non-zero while any blocker fails. Secret values are never printed. */
final class LaunchCheck extends Command
{
    protected $signature = 'campaign:launch-check {--json : Machine-readable output}';

    protected $description = 'Check configuration, content, data and health before going live';

    public function handle(LaunchReadiness $readiness): int
    {
        $checks = $readiness->run();
        $blocking = array_filter($checks, fn ($c) => ! $c['ok'] && $c['blocker']);

        if ($this->option('json')) {
            $this->line((string) json_encode(['ready' => $blocking === [], 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['', 'Group', 'Check', 'Detail'], array_map(fn ($c) => [$c['ok'] ? '✔' : ($c['blocker'] ? '✘' : '!'), $c['group'], $c['name'], $c['detail']], $checks));
            $warnings = count(array_filter($checks, fn ($c) => ! $c['ok'] && ! $c['blocker']));
            $blocking === []
                ? $this->info("No blockers. {$warnings} warning(s) to review.")
                : $this->error(count($blocking)." blocker(s) and {$warnings} warning(s). Not ready for launch.");
        }

        return $blocking === [] ? self::SUCCESS : self::FAILURE;
    }
}
