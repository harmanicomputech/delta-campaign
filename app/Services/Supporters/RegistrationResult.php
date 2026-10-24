<?php

declare(strict_types=1);

namespace App\Services\Supporters;

use App\Models\Supporter;

final readonly class RegistrationResult
{
    public function __construct(public Supporter $supporter, public bool $created) {}
}
