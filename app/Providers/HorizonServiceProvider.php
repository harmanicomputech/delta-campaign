<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * Horizon is open in the local environment only. Elsewhere access is denied
     * until Phase 3 introduces the "system.horizon" permission for operators.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null): bool => false);
    }
}
