<?php

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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'lemon_squeezy' => [
        'key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
        'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),
        'variant_starter' => env('LEMON_SQUEEZY_VARIANT_STARTER', '123456'),
        'variant_growth' => env('LEMON_SQUEEZY_VARIANT_GROWTH', '123457'),
        'variant_scale' => env('LEMON_SQUEEZY_VARIANT_SCALE', '123458'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'public' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_starter' => env('STRIPE_PRICE_STARTER'),
        'price_growth' => env('STRIPE_PRICE_GROWTH'),
        'price_scale' => env('STRIPE_PRICE_SCALE'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'cost_per_million_tokens' => env('OPENAI_COST_PER_MILLION_TOKENS', 0.15),
    ],

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
        'api_url' => env('DEEPL_API_URL', 'https://api-free.deepl.com/v2/translate'),
        'cost_per_million_characters' => env('DEEPL_COST_PER_MILLION_CHARACTERS', 25.00),
    ],

    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'geoip' => [
        'url' => env('GEOIP_URL', 'https://ipapi.co/{ip}/json/'),
        'default_country' => env('GEOIP_DEFAULT_COUNTRY', 'TZ'),
    ],

];
