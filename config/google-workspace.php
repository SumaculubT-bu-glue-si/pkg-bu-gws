<?php

return [
    'credentials' => [
        'path' => env('GOOGLE_WORKSPACE_CREDENTIALS_PATH', 'app/bu-project-470908-62e94ff11fdb.json'),
        'admin_email' => env('GOOGLE_WORKSPACE_ADMIN_EMAIL', 'izumi@capo.co.jp'),
    ],

    'cache' => [
        'driver' => 'redis',
        'prefix' => 'gws_',
        'ttl' => [
            'user' => 3600,        // 1 hour for user data
            'org_unit' => 86400,   // 24 hours for org units
            'default' => 1800      // 30 minutes default
        ]
    ],

    'monitoring' => [
        'enabled' => true,
        'rate_limit_threshold' => 80,  // Alert at 80% of rate limit
        'log_slow_requests' => true,
        'slow_request_threshold' => 2000, // ms
        'alert_channels' => ['slack', 'email'],
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
