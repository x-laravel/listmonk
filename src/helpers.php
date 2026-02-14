<?php

use XLaravel\Listmonk\Listmonk;

if (!function_exists('listmonk')) {
    /**
     * Get the Listmonk service instance.
     *
     * @return \XLaravel\Listmonk\Listmonk
     */
    function listmonk(): Listmonk
    {
        return app('listmonk');
    }
}
