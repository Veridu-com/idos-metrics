<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace Cli\Command;

use Cli\Handler;
use Cli\Utils\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command definition for metrics daemon.
 */
class Daemon extends AbstractCommand {
    /**
     * Command configuration.
     *
     * @return void
     */
    protected function configure() {
        $this
            ->setName('metrics:daemon')
            ->setDescription('idOS Metrics - Daemon')
            ->addOption(
                'logFile',
                'l',
                InputOption::VALUE_REQUIRED,
                'Path to log file'
            )
            ->addArgument(
                'serverList',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Gearman server host list (separate values by space)'
            );
    }

    /**
     * Command execution.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $outpput
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $logFile = $input->getOption('logFile') ?? 'php://stdout';
        $monolog = new Monolog('Metrics');
        $monolog->pushHandler(new StreamHandler($logFile, Monolog::DEBUG));
        $logger  = new Logger($monolog);

        $logger->debug('Initializing idOS Metrics Daemon');

        // Server List setup
        $servers = $input->getArgument('serverList');

        $gearman = new \GearmanWorker();
        foreach ($servers as $server) {
            if (strpos($server, ':') === false) {
                $logger->debug(sprintf('Adding Gearman Server: %s', $server));
                $gearman->addServer($server);
            } else {
                $server    = explode(':', $server);
                $server[1] = intval($server[1]);
                $logger->debug(sprintf('Adding Gearman Server: %s:%d', $server[0], $server[1]));
                $gearman->addServer($server[0], $server[1]);
            }
        }

        // Run the worker in non-blocking mode
        $gearman->addOptions(\GEARMAN_WORKER_NON_BLOCKING);

        // 1 second I/O timeout
        $gearman->setTimeout(1000);

        $logger->debug('Registering Worker Function "metrics"');

        $gearman->addFunction(
            sprintf('idos-metrics-%s', str_replace('.', '', __VERSION__)),
            function (\GearmanJob $job) use ($logger) {
                $time = microtime(true);

                $logger->debug('Got a new job!');
                $jobData = json_decode($job->workload(), true);
                if ($jobData === null) {
                    $logger->debug('Invalid Job Workload!');
                    $job->sendComplete('invalid');

                    return;
                }

                $handler = new Handler\Metrics($this->getDbConnection(), $this->getSaltConfig());
                if (! $handler->handleNewMetric($jobData)) {
                    $logger->debug('There was an error handling the new metric.');
                    $job->sendComplete('invalid');

                    return;
                }

                $time = microtime(true) - $time;
                $logger->debug('Job done! (' . $time . ')');
                $job->sendComplete('ok');
            }
        );

        $logger->debug('Registering Ping Function "ping"');

        // Register Thread's Ping Function
        $gearman->addFunction(
            'ping',
            function (\GearmanJob $job) use ($logger) {
                $logger->debug('Ping!');

                return 'pong';
            }
        );

        $logger->debug('Entering Gearman Worker Loop');

        // Gearman's Loop
        while ($gearman->work()
                || ($gearman->returnCode() == \GEARMAN_IO_WAIT)
                || ($gearman->returnCode() == \GEARMAN_NO_JOBS)
                || ($gearman->returnCode() == \GEARMAN_TIMEOUT)
        ) {
            if ($gearman->returnCode() == \GEARMAN_SUCCESS) {
                continue;
            }

            if (! @$gearman->wait()) {
                if ($gearman->returnCode() == \GEARMAN_NO_ACTIVE_FDS) {
                    // No server connection, sleep before reconnect
                    $logger->debug('No active server, sleep before retry');
                    sleep(5);
                    continue;
                }

                if ($gearman->returnCode() == \GEARMAN_TIMEOUT) {
                    // Job wait timeout, sleep before retry
                    sleep(1);
                    continue;
                }
            }
        }

        $logger->debug('Leaving Gearman Worker Loop');
    }
}
