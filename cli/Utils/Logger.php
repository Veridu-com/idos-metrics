<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace Cli\Utils;

use Monolog\Logger as Monolog;

/**
 * Handles logging.
 */
class Logger {
    /**
     * Monolog instance.
     *
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * Class constructor.
     *
     * @param \Monolog\Logger $logger
     *
     * @return void
     */
    public function __construct(Monolog $logger) {
        $this->logger = $logger;
    }

    /**
     * Logger method call.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed|null
     */
    public function __call(string $name, array $arguments) {
        call_user_func_array([$this->logger, $name], $arguments);
    }
}
