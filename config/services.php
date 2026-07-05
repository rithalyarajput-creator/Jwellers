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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'ga4' => [
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'api_secret' => env('GA4_API_SECRET'),
    ],

    'facebook' => [
        'pixel_id' => env('FB_PIXEL_ID'),
        'access_token' => env('FB_ACCESS_TOKEN'),
        'test_event_code' => env('FB_TEST_EVENT_CODE'),
    ],

    'meta' => [
        'page_access_token'        => env('META_PAGE_ACCESS_TOKEN'),
        'app_secret'               => env('META_APP_SECRET'),
        'verify_token'             => env('META_VERIFY_TOKEN'),
        'whatsapp_phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    ],

    // Shiprocket Checkout (hosted one-page checkout, separate from Shipping API)
    // The Shipping API still uses the Setting model (shiprocket_email/password/api_token).
    'shiprocket_checkout' => [
        'enabled'  => env('SHIPROCKET_CHECKOUT_ENABLED', false),
        'key'      => env('SHIPROCKET_CHECKOUT_KEY'),
        'secret'   => env('SHIPROCKET_CHECKOUT_SECRET'),
        // Production: https://checkout-api.shiprocket.com  Staging: https://fastrr-api-dev.pickrr.com
        'base_url' => env('SHIPROCKET_CHECKOUT_BASE_URL', 'https://checkout-api.shiprocket.com'),
        // Inbound order webhook is authenticated by a custom header configured
        // in the Shiprocket dashboard. Handler also accepts X-Api-HMAC-SHA256.
        'webhook_header_name' => env('SHIPROCKET_CHECKOUT_WEBHOOK_HEADER', 'foreverkids'),
        'webhook_token'       => env('SHIPROCKET_CHECKOUT_WEBHOOK_TOKEN'),
    ],

];
