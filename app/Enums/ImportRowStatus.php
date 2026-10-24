<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportRowStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Applied = 'applied';
    case Skipped = 'skipped';
}
