<?php

declare(strict_types = 1);

namespace Cli\Handler;

use Illuminate\Database\Connection as DbConnection;

/*
 * This class handles the metrics cron and daemon commands.
 */
class Metrics
{
    /**
     * @var \Illuminate\Database\Connection
     */
    private $dbConnection;

    /**
     * Constructor.
     *
     * @param \Illuminate\Database\Connection $dbConnection
     */
    public function __construct(DbConnection $dbConnection) {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Handles the incoming event from the idOS API.
     *
     * @param array $data
     * @return bool
     */
    public function handleNewMetric(array $data) : bool {
        switch ($data['endpoint']) {
            case 'profile:source':
                $credential = $data['credential'];
                $source = $data['source'];
                $action = $data['action'];
                $created = $data['created'];

                return $this
                    ->dbConnection
                    ->table('source_metrics')
                    ->insert([
                        'credential_id' => $credential['id'],
                        'provider' => $source['name'],
                        'sso' => (isset($source['tags']['sso']) && $source['tags']['sso'] === true) ? true : false,
                        'action' => $action,
                        'created_at' => date('Y-m-d H:i:s', $created)
                    ]);
                break;

            case 'profile:gate':
                $credential = $data['credential'];
                $gate = $data['gate'];
                $action = $data['action'];
                $created = $data['created'];

                return $this
                    ->dbConnection
                    ->table('gate_metrics')
                    ->insert([
                        'credential_id' => $credential['id'],
                        'name' => $gate['name'],
                        'pass' => $gate['pass'] === true ?: false,
                        'action' => $action,
                        'created_at' => date('Y-m-d H:i:s', $created)
                    ]);
                break;

            default:
        }

        return false;
    }

    /**
     * Separate metrics
     */
    public function handleHourlyMetrics($endpoint) {
        switch ($endpoint) {
            case 'profile:source':
                $this
                    ->dbConnection
                    ->unprepared(
                        'INSERT INTO source_metrics_hourly ("credential_id", "provider", "sso", "action", "created_at", "count")
                            SELECT "credential_id", "provider", "sso", "action", DATE_TRUNC(\'hour\', "created_at"), COUNT(*) as count
                            FROM "source_metrics"
                            WHERE "created_at" < \'' . date('Y-m-d H:i:s', time() - 3600) . '\'
                            GROUP BY "credential_id", "sso", "provider", "action", DATE_TRUNC(\'hour\', "created_at")'
                    );
                break;

            case 'profile:gate':
                $this
                    ->dbConnection
                    ->unprepared(
                        'INSERT INTO gate_metrics_hourly ("credential_id", "name", "pass", "action", "created_at", "count")
                            SELECT "credential_id", "name", "pass", "action", DATE_TRUNC(\'hour\', "created_at"), COUNT(*) as count
                            FROM "gate_metrics"
                            WHERE "created_at" < \'' . date('Y-m-d H:i:s', time() - 3600) . '\'
                            GROUP BY "credential_id", "name", "pass", "action", DATE_TRUNC(\'hour\', "created_at")'
                    );
                break;
        }
    }

    /**
     * Handles metrics that
     */
    public function handleDailyMetrics() {
        $this->handleIntervalMetrics(24 * 3600, '_daily', '_hourly');
    }
}
