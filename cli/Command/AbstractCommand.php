<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace Cli\Command;

use Illuminate\Database\Capsule\Manager as DatabaseManager;
use Symfony\Component\Console\Command\Command;

/**
 * Abstract command definition.
 */
abstract class AbstractCommand extends Command {
    /**
     * The application configuration.
     *
     * @var array
     */
    private $config;
    /**
     * The database manager instance.
     *
     * @var \Illuminate\Database\Capsule\Manager
     */
    private $dbManager;

    /**
     * Returns the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getDbConnection() {
        return $this->dbManager->getConnection();
    }

    /**
     * Constructor.
     *
     * @param array $config
     * @param string|null $name
     */
    public function __construct(array $config, string $name = null) {
        parent::__construct($name);

        $this->config = $config;
        $this->dbManager = new DatabaseManager();
        $this->dbManager->addConnection($this->config['db']);
    }
}
