<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Database sessions that also record the signed-in device, so revoking a
 * device ends every session started from it.
 */
final class DeviceAwareSessionHandler extends DatabaseSessionHandler
{
    /**
     * @param  string  $data
     * @return array<string, mixed>
     */
    protected function getDefaultPayload($data)
    {
        $payload = parent::getDefaultPayload($data);

        if ($this->container?->bound('session')) {
            $device = $this->container->make('session')->get(SignInService::SESSION_DEVICE);
            $payload['device_id'] = is_string($device) ? $device : null;
        }

        return $payload;
    }
}
