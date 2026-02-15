<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Listmonk API Configuration
    |--------------------------------------------------------------------------
    |
    | Base URL and credentials for your Listmonk instance.
    |
    */

    'base_url' => env('LISTMONK_BASE_URL', 'http://localhost:9000'),
    'api_user' => env('LISTMONK_API_USER'),
    'api_token' => env('LISTMONK_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    |
    | preconfirm_subscriptions: Automatically confirm new subscriptions without
    | requiring email confirmation. Set to false for public signup forms.
    |
    */

    'preconfirm_subscriptions' => env('LISTMONK_PRECONFIRM_SUBSCRIPTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Laravel queue behavior for sync operations.
    |
    */

    'queue' => [
        'enabled' => env('LISTMONK_QUEUE_ENABLED', true),
        'connection' => env('LISTMONK_QUEUE_CONNECTION', null),
        'queue' => env('LISTMONK_QUEUE_NAME', null),
        'delay' => env('LISTMONK_QUEUE_DELAY', 0),
        'tries' => env('LISTMONK_QUEUE_TRIES', 3),
        'backoff' => env('LISTMONK_QUEUE_BACKOFF', '10,30,60'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Lists
    |--------------------------------------------------------------------------
    |
    | Default list IDs to subscribe users to. Can be overridden per-model
    | by implementing getNewsletterLists() method.
    |
    */

    'default_lists' => [],

    /*
    |--------------------------------------------------------------------------
    | Passive List
    |--------------------------------------------------------------------------
    |
    | When a subscriber is deleted or soft-deleted, they can be moved to a
    | "passive" list instead of being unsubscribed. Set to null to unsubscribe.
    | Can be overridden per-model by implementing getNewsletterPassiveListId().
    |
    */

    'passive_list_id' => env('LISTMONK_PASSIVE_LIST_ID', null),
];
