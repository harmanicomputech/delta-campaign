<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportRowAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Unchanged = 'unchanged';
}
