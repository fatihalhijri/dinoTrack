<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'base_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',
        'connect_timeout' => 5,
        'timeout' => 15,
        // Dikirim sebagai custom_expiry karena respons charge QRIS tidak mendokumentasikan expiry_time.
        'qris_expiry_minutes' => 15,
    ],

    // Kredensial router disimpan per router di database (terenkripsi), bukan di .env.
    'mikrotik' => [
        'connect_timeout' => 5,
        'socket_timeout' => 10,
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'fonnte'),
    ],

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'base_url' => 'https://api.fonnte.com',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
