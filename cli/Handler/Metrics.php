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
     * @param array $payload
     * @return bool
     */
    public function handleNewMetric(array $payload) : bool {
        $action = $payload['action'];
        $created = $payload['created'];

        switch ($payload['endpoint']) {
            case 'profile:source':
                $credential = $payload['credential'];
                $source = $payload['source'];
                $data = [
                    'credential_id' => $credential['id'],
                    'provider' => $source['name'],
                    'sso' => (isset($source['tags']['sso']) && $source['tags']['sso'] === true) ? true : false
                ];
                break;

            case 'profile:gate':
                $credential = $payload['credential'];
                $gate = $payload['gate'];
                $data = [
                    'credential_id' => $credential['id'],
                    'name' => $gate['name'],
                    'pass' => $gate['pass'] === true ?: false
                ];
                break;

            default:
                return false;
        }

        return $this
            ->dbConnection
            ->table('metrics')
            ->insert([
                'endpoint' => $payload['endpoint'],
                'action' => $action,
                'data' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s', $created)
            ]);
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
                        'INSERT INTO metrics_hourly ("endpoint", "action", "data", "count", "created_at")
                            SELECT
                                "endpoint",
                                "action",
                                json_build_object(
                                \'credential_id\', cast("data"->>\'credential_id\' as integer),
                                \'provider\', "data"->>\'provider\',
                                \'sso\', cast("data"->>\'sso\' as boolean)
                                ),
                                COUNT(*) as "count",
                                DATE_TRUNC(\'hour\', "created_at")
                            FROM "metrics"
                            WHERE 
                              "endpoint" = \'profile:source\' AND
                              "created_at" < \'' . date('Y-m-d H:i:s', time() - 3600) . '\'
                            GROUP BY
                                "endpoint",
                                "data"->>\'credential_id\',
                                "data"->>\'sso\',
                                "data"->>\'provider\',
                                "action",
                                DATE_TRUNC(\'hour\', "created_at")'
                    );
                break;

            case 'profile:gate':
                $this
                    ->dbConnection
                    ->unprepared(
                        'INSERT INTO metrics_hourly ("endpoint", "action", "data", "count", "created_at")
                            SELECT
                                "endpoint",
                                "action",
                                json_build_object(
                                \'credential_id\', cast("data"->>\'credential_id\' as integer),
                                \'name\', "data"->>\'name\',
                                \'pass\', cast("data"->>\'pass\' as boolean)
                                ),
                                COUNT(*) as "count",
                                DATE_TRUNC(\'hour\', "created_at")
                            FROM "metrics"
                            WHERE 
                              "endpoint" = \'profile:gate\' AND
                              "created_at" < \'' . date('Y-m-d H:i:s', time() - 3600) . '\'
                            GROUP BY
                                "endpoint",
                                "data"->>\'credential_id\',
                                "data"->>\'pass\',
                                "data"->>\'name\',
                                "action",
                                DATE_TRUNC(\'hour\', "created_at")'
                    );
                break;
        }
    }

    /**
     * Handles metrics that
     */
    public function handleDailyMetrics($endpoint) {
        switch ($endpoint) {
            case 'profile:source':
                $this
                    ->dbConnection
                    ->unprepared(
                        'INSERT INTO metrics_daily ("endpoint", "action", "data", "count", "created_at")
                            SELECT
                                "endpoint",
                                "action",
                                json_build_object(
                                \'credential_id\', cast("data"->>\'credential_id\' as integer),
                                \'provider\', "data"->>\'provider\',
                                \'sso\', cast("data"->>\'sso\' as boolean)
                                ),
                                SUM("count") as "count",
                                DATE_TRUNC(\'day\', "created_at")
                            FROM "metrics_hourly"
                            WHERE 
                              "endpoint" = \'profile:source\' AND
                              "created_at" < \'' . date('Y-m-d H:i:s', time() - 24 * 3600) . '\'
                            GROUP BY
                                "endpoint",
                                "data"->>\'credential_id\',
                                "data"->>\'sso\',
                                "data"->>\'provider\',
                                "action",
                                DATE_TRUNC(\'day\', "created_at")'
                    );
                break;

            case 'profile:gate':
                $this
                    ->dbConnection
                    ->unprepared(
                        'INSERT INTO metrics_daily ("endpoint", "action", "data", "count", "created_at")
                            SELECT
                                "endpoint",
                                "action",
                                json_build_object(
                                \'credential_id\', cast("data"->>\'credential_id\' as integer),
                                \'name\', "data"->>\'name\',
                                \'pass\', cast("data"->>\'pass\' as boolean)
                                ),
                                SUM("count") as "count",
                                DATE_TRUNC(\'day\', "created_at")
                            FROM "metrics_hourly"
                            WHERE 
                              "endpoint" = \'profile:gate\' AND
                              "created_at" < \'' . date('Y-m-d H:i:s', time() - 24 * 3600) . '\'
                            GROUP BY
                                "endpoint",
                                "data"->>\'credential_id\',
                                "data"->>\'pass\',
                                "data"->>\'name\',
                                "action",
                                DATE_TRUNC(\'day\', "created_at")'
                    );
                break;
        }
    }
}
