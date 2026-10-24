<?php

declare(strict_types=1);

namespace App\Enums;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Failed = 'failed';

    public function label(): string
    {
        return __("health.status.{$this->value}");
    }

    /** Badge tone used by <x-ui.badge>. */
    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Degraded => 'warning',
            self::Failed => 'danger',
        };
    }

    /**
     * The worst status in a set: one failure fails the whole system.
     *
     * @param  iterable<self>  $statuses
     */
    public static function worst(iterable $statuses): self
    {
        $worst = self::Ok;

        foreach ($statuses as $status) {
            if ($status === self::Failed) {
                return self::Failed;
            }

            if ($status === self::Degraded) {
                $worst = self::Degraded;
            }
        }

        return $worst;
    }
}
