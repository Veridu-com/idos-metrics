<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

use Cli\Utils\Env;

if (! defined('__VERSION__')) {
    define('__VERSION__', '1.0');
}

return [
    'db' => [
        'driver'    => Env::asString('IDOS_SQL_DRIVER', 'pgsql'),
        'host'      => Env::asString('IDOS_SQL_HOST', 'localhost'),
        'port'      => Env::asInteger('IDOS_SQL_PORT', 5432),
        'database'  => Env::asString('IDOS_SQL_NAME', 'idos-api'),
        'username'  => Env::asString('IDOS_SQL_USER', 'idos-api'),
        'password'  => Env::asString('IDOS_SQL_PASS', 'idos-api'),
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
        'options'   => [
            \PDO::ATTR_TIMEOUT    => 5,
            \PDO::ATTR_PERSISTENT => true
        ]
    ],

     'salt' => [
         'user' => Env::asString('IDOS_SALT_USER', '')
     ]
];
