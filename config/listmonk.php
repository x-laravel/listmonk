<?php

use XLaravel\Listmonk\Models\Subscriber;

return [

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | enabled   Sync models with Listmonk. Nothing is sent while this is
    |           false, or while the url or the token is empty.
    | url       Address of the Listmonk instance.
    | username  Name of the Listmonk API user.
    | token     Token of the Listmonk API user.
    | timeout   Seconds to wait for a response.
    |
    */

    'enabled' => env('LISTMONK_ENABLED', true),

    'url' => env('LISTMONK_API_URL'),

    'username' => env('LISTMONK_API_USERNAME'),

    'token' => env('LISTMONK_API_TOKEN'),

    'timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | preconfirm  Add subscribers to their lists as confirmed, so double
    |             opt-in lists send them no confirmation e-mail.
    |
    */

    'preconfirm' => true,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | connection  Queue connection of the sync jobs. Null uses the default
    |             connection.
    | name        Queue of the sync jobs. Null uses the default queue of
    |             the connection.
    |
    */

    'queue' => [
        'connection' => null,
        'name' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | connection  Database connection of the subscriber table. Null uses the
    |             default connection.
    | table       Name of the subscriber table.
    | model       Eloquent model of the table. It must extend the package's
    |             own model: XLaravel\Listmonk\Models\Subscriber.
    |
    */

    'connection' => null,

    'subscriber' => [
        'table' => 'listmonk_subscribers',
        'model' => Subscriber::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Subscribers whose model no longer exists are left behind by deletes
    | that skip model events, such as bulk queries and database cascades.
    | The package schedules model:prune to remove them from Listmonk.
    |
    | enabled   Schedule the pruning.
    | schedule  When the pruning runs, written in one of two ways:
    |           A cron expression:
    |             "30 3 * * *"        every day at 03:30
    |
    |           The name of a Laravel schedule frequency method, with its
    |           arguments after a colon, separated by commas:
    |             "daily"             every day at 00:00
    |             "dailyAt:03:30"     every day at 03:30
    |             "twiceDaily:1,13"   every day at 01:00 and 13:00
    |             "weeklyOn:1,04:00"  every Monday at 04:00
    | chunk     Rows removed by each query.
    |
    */

    'prune' => [
        'enabled' => true,
        'schedule' => 'daily',
        'chunk' => 1000,
    ],

];
