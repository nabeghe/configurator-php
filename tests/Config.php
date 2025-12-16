<?php namespace Nabeghe\Configurator\Test;

use Nabeghe\Configurator\Configurator;

/**
 * @property Types\Db $app
 * @property Types\Db $db
 */
class Config extends Configurator
{
    const array DEFAULTS = [
        'app' => [
            'env' => 'production',
            'log_level' => 'info',
        ],
        'db' => [
            'type' => 'sqlite',
            'host' => 'localhost',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ];
}
