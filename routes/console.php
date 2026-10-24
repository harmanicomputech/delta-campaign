<?php

use App\Services\Exports\SupporterExportService;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks (run by the `scheduler` container: php artisan schedule:work)
*/

// Security
Schedule::command('audit:checkpoint')->dailyAt('00:05')->onOneServer();
Schedule::command('audit:verify')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
Schedule::command('auth:prune')->dailyAt('03:00')->onOneServer();

// Data protection
Schedule::command('privacy:apply-retention')->weeklyOn(1, '01:30')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(SupporterExportService::class)->prune())->hourly()->name('exports:prune')->onOneServer();

// Queues
Schedule::command('horizon:snapshot')->everyFiveMinutes();
