<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Client-side web SDK config (injected into Blade views)
    |--------------------------------------------------------------------------
    */
    'api_key' => env('FIREBASE_API_KEY'),
    'database_url' => env('FIREBASE_DATABASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | kreait/laravel-firebase server-side SDK config
    |--------------------------------------------------------------------------
    */
    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [
        'app' => [
            'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/credentials.json')),

            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],

            'logging' => [
                'http_log_channel' => null,
                'http_debug_log_channel' => null,
            ],

            'http_client_options' => [
                'proxy' => null,
                'timeout' => 0.0,
                'gzip' => false,
            ],

            // Caches the Google OAuth2 access token (and public-key verifier data)
            // across requests instead of re-fetching it on every PHP process —
            // without this, every server-side Firebase call pays a ~1.5-2s token
            // exchange with oauth2.googleapis.com on top of the actual request.
            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),
        ],
    ],
];
