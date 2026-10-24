<?php

// Nigerian Pidgin — DRAFT. Must be reviewed by a native speaker before launch.

return [
    'title' => 'System status',
    'description' => 'Live check of the services wey this platform need.',
    'refresh' => 'Check again',
    'last_checked' => 'We check am :time',
    'status' => [
        'ok' => 'E dey work',
        'degraded' => 'E dey slow',
        'failed' => 'E no dey work',
    ],
    'checks' => [
        'database' => 'Database',
        'postgis' => 'Map data (PostGIS)',
        'redis' => 'Redis',
        'cache' => 'Cache',
        'queue' => 'Queue workers',
    ],
    'supervisors' => ':count supervisor dey run|:count supervisors dey run',
    'overall' => [
        'ok' => 'Everything dey work well',
        'degraded' => 'Some services dey slow',
        'failed' => 'One important service no dey work',
    ],
];
