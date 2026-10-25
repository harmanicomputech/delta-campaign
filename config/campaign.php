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
    | Data-protection keys
    |--------------------------------------------------------------------------
    |
    | phone_hash: HMAC key for keyed phone-number hashes (duplicate detection and
    | look-ups without storing numbers in clear). Rotating it requires re-hashing.
    | field_encryption: AES-256 key for encrypted personal-data columns, kept
    | separate from APP_KEY so session/cookie key rotation never touches data.
    | Generate both with: php artisan campaign:keys
    |
    */

    'keys' => [
        'phone_hash' => env('PHONE_HASH_KEY'),
        'field_encryption' => env('FIELD_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'otp_length' => 6,
        'otp_ttl_seconds' => (int) env('AUTH_OTP_TTL', 300),
        'otp_max_attempts' => 5,
        'otp_resend_seconds' => 60,
        'otp_per_phone_per_hour' => (int) env('AUTH_OTP_PER_PHONE_PER_HOUR', 5),
        'otp_per_ip_per_hour' => (int) env('AUTH_OTP_PER_IP_PER_HOUR', 30),
        'pin_length_min' => 4,
        'pin_length_max' => 6,
        'pin_max_attempts' => 5,
        'pin_lockout_minutes' => 15,
        'pin_confirm_minutes' => (int) env('AUTH_PIN_CONFIRM_MINUTES', 15),
        'idle_lock_minutes' => (int) env('AUTH_IDLE_LOCK_MINUTES', 30),
        'device_cookie' => 'device_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | "outbox" stores messages in the cache for the local developer outbox
    | (/design-system/outbox) and never sends. "africastalking" sends for real.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'outbox'),
        // Shown in replies ("Reply STOP to …"); the provider short code or long number.
        'reply_number' => env('SMS_REPLY_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | USSD
    |--------------------------------------------------------------------------
    | Screens are limited to 182 characters (tested). Session state is kept in
    | the cache (Redis), encrypted, for session_ttl seconds.
    */
    'ussd' => [
        'name' => env('USSD_DISPLAY_NAME', 'Campaign'),
        'session_ttl' => (int) env('USSD_SESSION_TTL', 300),
        'max_screen' => 182,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data protection
    |--------------------------------------------------------------------------
    |
    | retention_months: supporter records are anonymised this long after their
    | last renewal (consent or contact), unless under legal hold.
    | dsr_response_days: data-subject request deadline (subject to legal review).
    |
    */

    'privacy' => [
        'retention_months' => (int) env('SUPPORTER_RETENTION_MONTHS', 36),
        'dsr_response_days' => (int) env('DSR_RESPONSE_DAYS', 30),
        'export_ttl_hours' => (int) env('EXPORT_TTL_HOURS', 24),
        'exports_disk' => env('EXPORTS_DISK', 'local'),
        'duplicate_similarity' => 0.55,
    ],

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
