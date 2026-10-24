<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The design-system reference is a development tool. It is never available in
 * production, regardless of configuration.
 */
final class EnsureDesignSystemEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app()->isProduction() || ! config('campaign.design_system_enabled'), 404);

        return $next($request);
    }
}
