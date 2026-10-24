<?php

return [
    'title' => 'System status',
    'description' => 'Live checks of the services this platform depends on.',
    'refresh' => 'Refresh',
    'last_checked' => 'Checked :time',
    'status' => [
        'ok' => 'Operational',
        'degraded' => 'Degraded',
        'failed' => 'Unavailable',
    ],
    'checks' => [
        'database' => 'Database',
        'postgis' => 'Geospatial (PostGIS)',
        'redis' => 'Redis',
        'cache' => 'Cache',
        'queue' => 'Queue workers',
    ],
    'supervisors' => ':count supervisor running|:count supervisors running',
    'overall' => [
        'ok' => 'All systems operational',
        'degraded' => 'Some services are degraded',
        'failed' => 'A required service is unavailable',
    ],
];
