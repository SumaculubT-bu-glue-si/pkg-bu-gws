<?php

return [
    // Credentials must be set in the host Laravel app's .env or config/services.php.
    // Do NOT store any credential paths or keys in this package.
    // Example for host app .env:
    // GOOGLE_WORKSPACE_CREDENTIALS_PATH=storage/app/your-key.json
    // GOOGLE_WORKSPACE_ADMIN_EMAIL=admin@yourdomain.com
    'credentials' => [
        // Always proxy to host app config/env only
        'path' => env('GOOGLE_WORKSPACE_CREDENTIALS_PATH'),
        'admin_email' => env('GOOGLE_WORKSPACE_ADMIN_EMAIL'),
    ],

    'cache' => [
        'enabled' => env('GOOGLE_WORKSPACE_CACHE_ENABLED', true),
        'prefix' => env('GOOGLE_WORKSPACE_CACHE_PREFIX', 'gws:'),
        'ttl' => [
            'user' => env('GOOGLE_WORKSPACE_CACHE_USER_TTL', 3600), // 1 hour
            'default' => env('GOOGLE_WORKSPACE_CACHE_DEFAULT_TTL', 1800), // 30 minutes
        ],
    ],

    'monitoring' => [
        'enabled' => env('GOOGLE_WORKSPACE_MONITORING_ENABLED', true),
        'slow_request_threshold' => env('GOOGLE_WORKSPACE_SLOW_REQUEST_THRESHOLD', 5000),
        'rate_limit_threshold' => env('GOOGLE_WORKSPACE_RATE_LIMIT_THRESHOLD', 80),
    ],

    'sync' => [
        'real_time' => true,
        'batch_size' => 100,
        'retry_attempts' => 3,
        'retry_delay' => 5, // seconds
    ],

    'api' => [
        'application_name' => 'AssetWise',
        'scopes' => [
            'https://www.googleapis.com/auth/admin.directory.user',
            'https://www.googleapis.com/auth/admin.directory.group',
            'https://www.googleapis.com/auth/admin.directory.resource.calendar',
        ],
    ],
];