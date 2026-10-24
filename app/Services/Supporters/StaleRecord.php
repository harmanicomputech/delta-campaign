<?php

declare(strict_types=1);

namespace App\Services\Supporters;

use RuntimeException;

/** Thrown when a record changed after the editor loaded it (optimistic locking). */
final class StaleRecord extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This record was changed by someone else. Reload it and apply your correction again.');
    }
}
