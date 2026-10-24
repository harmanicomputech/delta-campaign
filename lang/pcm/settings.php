<?php

// Nigerian Pidgin — DRAFT. Must be reviewed by a native speaker before launch.
// Keys not translated yet fall back to English.

return array_replace(require __DIR__.'/../en/settings.php', [
    'intro' => 'Platform settings wey staff wey get permission fit change. We dey record every change for audit log.',
    'more_help' => 'We no dey set secrets, keys or provider passwords here. Dem dey for server environment.',
]);
