<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Renders a Lucide icon from resources/icons as inline SVG.
 *
 * Decorative by default (aria-hidden). Pass a "label" to make it meaningful
 * to assistive technology.
 */
final class Icon extends Component
{
    /** @var array<string, string> */
    private static array $cache = [];

    public string $paths;

    public function __construct(
        public string $name,
        public ?string $label = null,
        public float $stroke = 1.75,
    ) {
        $this->paths = self::load($name);
    }

    public static function load(string $name): string
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid icon name [{$name}].");
        }

        $path = resource_path("icons/{$name}.svg");

        if (! is_file($path)) {
            throw new InvalidArgumentException("Icon [{$name}] not found. Add it to scripts/icons.json and run `npm run icons`.");
        }

        return self::$cache[$name] = trim((string) file_get_contents($path));
    }

    public function render(): View
    {
        return view('components.icon');
    }
}
