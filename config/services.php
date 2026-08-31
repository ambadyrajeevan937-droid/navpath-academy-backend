<?php

// Secrets come from the environment only — never committed, never shipped in
// the APK. The Android client holds no Learnyst or Razorpay secret at all; it
// talks to this API, which holds them.
return [
    'learnyst' => [
        'base_url'       => env('LEARNYST_BASE_URL', 'https://api.learnyst.com'),
        'api_key'        => env('LEARNYST_API_KEY'),
        'webhook_secret' => env('LEARNYST_WEBHOOK_SECRET'),
    ],

    'razorpay' => [
        'key'    => env('RAZORPAY_KEY'),        // publishable — safe on the client
        'secret' => env('RAZORPAY_SECRET'),     // server only
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS_PATH'),
    ],
];
