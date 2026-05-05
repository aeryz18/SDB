<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    | Credentials for the Firebase Realtime Database, sourced from .env so
    | they are never hardcoded in version-controlled view files.
    */
    'api_key'      => env('FIREBASE_API_KEY'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
];
