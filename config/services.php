<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mercadopago' => [
        'access_token' => env('MP_ACCESS_TOKEN'),
        'public_key' => env('MP_PUBLIC_KEY'),
        'notification_url' => env('MP_NOTIFICATION_URL'),
        'success_url' => env('MP_SUCCESS_URL'),
        'failure_url' => env('MP_FAILURE_URL'),
        'pending_url' => env('MP_PENDING_URL'),
        'shop_access_token' => env('SHOP_MP_ACCESS_TOKEN'),
        'shop_notification_url' => env('SHOP_MP_NOTIFICATION_URL'),
        'shop_success_url' => env('SHOP_MP_SUCCESS_URL'),
        'shop_failure_url' => env('SHOP_MP_FAILURE_URL'),
        'shop_pending_url' => env('SHOP_MP_PENDING_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'openrouteservice' => [
        'api_key' => env('OPENROUTESERVICE_API_KEY'),
    ],

    'shop' => [
        'origin_address' => env('SHOP_ORIGIN_ADDRESS', 'Alsina 5651, Billinghurst, Buenos Aires, Argentina'),
        'shipping_rate_per_km' => env('SHOP_SHIPPING_COST_PER_KM', env('SHOP_SHIPPING_RATE_PER_KM', 800)),
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        'api_base_url' => env('SPOTIFY_API_BASE_URL', 'https://api.spotify.com/v1'),
        'accounts_base_url' => env('SPOTIFY_ACCOUNTS_BASE_URL', 'https://accounts.spotify.com'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'api_base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

];
