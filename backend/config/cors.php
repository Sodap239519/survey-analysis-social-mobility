<?php
// backend/config/cors.php

return [
    // Apply CORS to API routes
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Allow your frontend dev server
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    // Allow common headers (or use ['*'])
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // If you use Sanctum cookie auth set true; for Bearer token it can still be true
    'supports_credentials' => true,
];