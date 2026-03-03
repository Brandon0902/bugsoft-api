<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | For development: allow all origins.
    | IMPORTANT: Do NOT use this in production. Restrict allowed_origins.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Allow all origins (DEV ONLY)
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // For Bearer token auth (Sanctum tokens), this should stay false.
    // If you later switch to cookie-based SPA auth, set true and restrict origins.
    'supports_credentials' => false,

];