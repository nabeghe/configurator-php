<?php namespace Nabeghe\Configurator\Test;

use Nabeghe\Configurator\Configurator;

/**
 * @property Types\Db $db
 * @property Types\RateLimit $rateLimit
 */
class Config extends Configurator
{
    const array DEFAULTS = [
        'db' => [
            'type' => 'sqlite',
            'host' => 'localhost',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'max_connections' => 100,
            'connection_timeout' => 30,
        ],
        'rate_limit' => [
            'enabled' => false,
            'requests_per_minute' => 60,
        ],
    ];
}
