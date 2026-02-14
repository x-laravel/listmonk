<?php

return [
    'base_url' => env('LISTMONK_BASE_URL', 'http://localhost:9000'),
    'api_user' => env('LISTMONK_API_USER'),
    'api_token' => env('LISTMONK_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    */
    'preconfirm_subscriptions' => env('LISTMONK_PRECONFIRM_SUBSCRIPTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Prevent too many API requests to Listmonk in a short time period.
    */
    'rate_limit' => [
        'enabled' => env('LISTMONK_RATE_LIMIT_ENABLED', false),
        'max_attempts' => env('LISTMONK_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('LISTMONK_RATE_LIMIT_DECAY', 1),
    ],

    'queue' => [
        'enabled' => env('LISTMONK_QUEUE_ENABLED', true),

        // Laravel queue connection name
        'connection' => env('LISTMONK_QUEUE_CONNECTION', null),

        // Queue name (örnek: emails, low, high vs.)
        'queue' => env('LISTMONK_QUEUE_NAME', null),

        // Job behaviour
        'delay' => env('LISTMONK_QUEUE_DELAY', 0), // seconds
        'tries' => env('LISTMONK_QUEUE_TRIES', 3),
        'backoff' => env('LISTMONK_QUEUE_BACKOFF', '10,30,60'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Lists
    |--------------------------------------------------------------------------
    */
    'default_lists' => [],
];
