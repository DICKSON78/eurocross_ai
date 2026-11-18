<?php

return [
    /**
     * Merchant of Record (MoR) Configuration
     * Choose which payment provider to use
     */
    'provider' => env('PAYMENT_PROVIDER', 'gumroad'), // gumroad, lemon_squeezy, or stripe

    /**
     * Lemon Squeezy Configuration (MoR - Recommended for non-EU)
     */
    'lemon_squeezy' => [
        'key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
        'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),

        // Product variant IDs for each plan
        'variant_starter' => env('LEMON_SQUEEZY_VARIANT_STARTER'),
        'variant_growth' => env('LEMON_SQUEEZY_VARIANT_GROWTH'),
        'variant_scale' => env('LEMON_SQUEEZY_VARIANT_SCALE'),
    ],

    /**
     * Gumroad Configuration (Merchant of Record - good for individuals)
     */
    'gumroad' => [
        'access_token' => env('GUMROAD_ACCESS_TOKEN'),
        'webhook_secret' => env('GUMROAD_WEBHOOK_SECRET'),
        'seller_id' => env('GUMROAD_SELLER_ID'),

        // Product permalinks (the part after gumroad.com/l/) for each plan
        'product_starter' => env('GUMROAD_PRODUCT_STARTER'),
        'product_growth' => env('GUMROAD_PRODUCT_GROWTH'),
        'product_scale' => env('GUMROAD_PRODUCT_SCALE'),

        // Gumroad product IDs (primary key for plan mapping)
        'product_id_starter' => env('GUMROAD_PRODUCT_ID_STARTER'),
        'product_id_growth' => env('GUMROAD_PRODUCT_ID_GROWTH'),
        'product_id_scale' => env('GUMROAD_PRODUCT_ID_SCALE'),

        // Checkout URLs
        'checkout_starter' => env('GUMROAD_CHECKOUT_STARTER'),
        'checkout_growth' => env('GUMROAD_CHECKOUT_GROWTH'),
        'checkout_scale' => env('GUMROAD_CHECKOUT_SCALE'),

        'trial_days' => (int) env('GUMROAD_TRIAL_DAYS', 7),
    ],

    /**
     * Stripe Configuration (OÜ + Stripe - For EU businesses)
     */
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'public' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // Price IDs for each plan
        'price_starter' => env('STRIPE_PRICE_STARTER'),
        'price_growth' => env('STRIPE_PRICE_GROWTH'),
        'price_scale' => env('STRIPE_PRICE_SCALE'),
    ],

    /**
     * Trial Configuration
     */
    'trial' => [
        'days' => (int) env('GUMROAD_TRIAL_DAYS', 7),
        'grace_period_days' => 3, // Days after expiration before suspension
    ],

    /**
     * Plan Pricing (EUR)
     */
    'plans' => [
        'starter' => [
            'price' => 12.00,
            'currency' => 'eur',
            'billing_cycle' => 'month',
            'rate_limit' => 60,
            'features' => [
                'words' => 50000,
                'languages' => 3,
                'vat' => 'basic',
                'chatbot' => false,
            ],
        ],
        'growth' => [
            'price' => 25.00,
            'currency' => 'eur',
            'billing_cycle' => 'month',
            'rate_limit' => 300,
            'features' => [
                'words' => 100000,
                'languages' => 5,
                'vat' => 'full',
                'chatbot' => true,
            ],
        ],
        'scale' => [
            'price' => 65.00,
            'currency' => 'eur',
            'billing_cycle' => 'month',
            'rate_limit' => 1000,
            'features' => [
                'words' => 300000,
                'languages' => 15,
                'vat' => 'full',
                'chatbot' => true,
            ],
        ],
    ],

    /**
     * Dunning Configuration (Payment failure recovery)
     */
    'dunning' => [
        'enabled' => true,
        'retry_attempts' => 3,
        'retry_days' => [0, 3, 7], // Days to retry after failure
    ],

    /**
     * Webhooks
     */
    'webhooks' => [
        'gumroad' => env('APP_URL').'/eurocross/webhook/gumroad',
        'lemon_squeezy' => env('APP_URL').'/eurocross/webhook/lemon-squeezy',
        'stripe' => env('APP_URL').'/eurocross/webhook/stripe',
    ],
];
