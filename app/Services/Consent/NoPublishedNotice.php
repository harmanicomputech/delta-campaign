<?php

declare(strict_types=1);

namespace App\Services\Consent;

use RuntimeException;

/** Consent cannot be collected until a privacy notice has been published. */
final class NoPublishedNotice extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No privacy notice is published. Consent cannot be recorded until the Data Protection Officer publishes one.');
    }
}
