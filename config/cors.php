<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',        // Next.js waktu development (npm run dev)
        // 'https://rentalkamu.com',    // domain production Next.js , aktifkan nanti
        // 'https://www.rentalkamu.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // set true kalau pakai sanctum cookies based auth session/ bearer sanctum api token false saja
    'supports_credentials' => false,

];