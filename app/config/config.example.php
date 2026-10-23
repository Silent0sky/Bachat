<?php
/**
 * Bachat configuration — EXAMPLE file.
 * On your hosting: copy this file to config.php and fill in real values.
 * config.php is never committed; keep credentials out of version control.
 */

return [
    'app' => [
        'name'       => 'Bachat',
        'tagline'    => 'Har Society Ki Smart Bachat',
        'env'        => 'production',            // 'development' shows detailed errors — NEVER in production
        'base_url'   => '',                      // e.g. 'https://bachat.infinityfreeapp.com' — leave '' to auto-detect
        'timezone'   => 'Asia/Kolkata',
        'locale'     => 'en',                    // default language: en | hi | mr
        'session'    => [
            'name'      => 'bachat_sid',
            'lifetime'  => 7200,                 // seconds (2 hours). Configurable session expiry.
        ],
        'mail'       => [
            'enabled'    => false,               // uses PHP mail(); InfinityFree limits email — in-app notifications always work
            'from_email' => 'no-reply@example.com',
            'from_name'  => 'Bachat',
        ],
        'cron_secret' => 'CHANGE-THIS-SECRET',    // required as ?key= when calling cron.php
    ],

    'db' => [
        // InfinityFree example: sqlXXX.infinityfree.com, epiz_XXXX_bachat
        'host'    => 'localhost',
        'name'    => 'bachat',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'secret_key'      => 'CHANGE-THIS-64-CHAR-RANDOM-SECRET',
        'max_login_tries' => 5,                  // failed attempts before temporary lock
        'lock_minutes'    => 15,
        'upload_max_kb'   => 2048,               // max upload size in KB
    ],

    'payments' => [
        'upi' => [
            'enabled' => true,                   // UPI id / QR manual payments (verified by admin)
            'vpa'     => 'bachat@upi',           // overridable per society in Society Admin → Settings
        ],
        'cod' => [
            'enabled' => true,
        ],
        'gateway' => [
            // Online gateways require valid credentials + server-side verification.
            'enabled'   => false,
            'provider'  => '',                   // e.g. razorpay
            'key_id'    => '',
            'key_secret'=> '',                   // server-side only, never exposed to browser
        ],
    ],

    'integrations' => [
        'whatsapp_api_url'   => '',              // optional placeholder — configure when you have credentials
        'whatsapp_api_token' => '',
        'sms_api_url'        => '',
        'sms_api_token'      => '',
        'browser_push'       => false,
    ],
];
