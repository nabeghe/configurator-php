<?php namespace Nabeghe\Configurator\Test;

use Nabeghe\Configurator\Configurator;

/**
 * @property Types\Db $app
 * @property Types\Db $db
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
    ];
}
