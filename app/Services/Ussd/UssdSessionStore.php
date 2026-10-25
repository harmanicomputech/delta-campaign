<?php

declare(strict_types=1);

namespace App\Services\Ussd;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** Encrypted, short-lived USSD state in the cache (Redis in production). */
final class UssdSessionStore
{
    public function __construct(private readonly string $prefix = 'ussd') {}

    public function load(string $sessionId): ?UssdSession
    {
        $payload = Cache::get($this->key($sessionId));

        if (! is_string($payload)) {
            return null;
        }

        try {
            return UssdSession::fromArray($sessionId, (array) Crypt::decrypt($payload));
        } catch (\Throwable) {
            return null;
        }
    }

    public function save(UssdSession $session): void
    {
        Cache::put($this->key($session->id), Crypt::encrypt($session->toArray()), (int) config('campaign.ussd.session_ttl'));
    }

    public function forget(string $sessionId): void
    {
        Cache::forget($this->key($sessionId));
    }

    private function key(string $sessionId): string
    {
        // Session ids are opaque provider values; hash them so keys are uniform and unguessable.
        return $this->prefix.':'.hash('sha256', $sessionId);
    }
}
