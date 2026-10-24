<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    |
    | All timestamps are stored in UTC (config/app.php). This timezone is used
    | only when presenting dates and times to people.
    |
    */

    'display_timezone' => env('CAMPAIGN_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | "pcm" is the ISO 639-3 code for Nigerian Pidgin. Every Pidgin string must
    | be reviewed by a native speaker before public launch.
    |
    */

    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English'],
        'pcm' => ['name' => 'Nigerian Pidgin', 'native' => 'Naijá'],
    ],

    'supported_locales' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPPORTED_LOCALES', 'en,pcm')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Design-system reference
    |--------------------------------------------------------------------------
    |
    | The /design-system pages document every UI component and preview the
    | application shells. They are never served in production, whatever this
    | value is set to.
    |
    */

    'design_system_enabled' => (bool) env('DESIGN_SYSTEM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Security headers
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Geography and imports
    |--------------------------------------------------------------------------
    |
    | Coordinates outside the bounding box are rejected on import. The box is
    | deliberately generous around Delta State's extent.
    |
    */

    'geography' => [
        'bounds' => ['min_lat' => 4.8, 'max_lat' => 6.8, 'min_lng' => 4.9, 'max_lng' => 7.0],
    ],

    'imports' => [
        'disk' => env('IMPORTS_DISK', 'local'),
        'max_rows' => (int) env('IMPORTS_MAX_ROWS', 50_000),
        'max_file_kb' => (int) env('IMPORTS_MAX_FILE_KB', 10_240),
        'chunk' => 500,
    ],

    /*
    | Proxies (load balancers) whose X-Forwarded-* headers are trusted.
    | Comma-separated IPs/CIDRs, or "*" only when behind a private load balancer.
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    'security' => [
        'hsts' => (bool) env('SECURITY_HSTS_ENABLED', false),
        'csp_report_uri' => env('SECURITY_CSP_REPORT_URI'),
    ],

];
