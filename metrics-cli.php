#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

require_once __DIR__ . '/vendor/autoload.php';


// Loads .env if available
if (is_file(__DIR__ . '/.env')) {
    $dotEnv = new Dotenv\Dotenv(__DIR__);
    $dotEnv->load();
}

// Load application settings
$config = require_once __DIR__ . '/config/settings.php';

date_default_timezone_set('UTC');
setlocale(LC_ALL, 'en_US.UTF8');
mb_http_output('UTF-8');
mb_internal_encoding('UTF-8');

$application = new Symfony\Component\Console\Application();
$application->add(new Cli\Command\Daemon($config));
$application->add(new Cli\Command\Hourly($config));
$application->add(new Cli\Command\Daily($config));
$application->run();
