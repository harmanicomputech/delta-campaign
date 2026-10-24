<?php

declare(strict_types=1);

namespace App\Support\Navigation;

final readonly class NavigationItem
{
    public function __construct(
        public string $label,
        public string $icon,
        public ?string $url,
        public bool $active,
        public int $phase,
        public bool $mobile,
    ) {}

    /** Items without a route are planned modules, rendered disabled. */
    public function isPlanned(): bool
    {
        return $this->url === null;
    }
}
