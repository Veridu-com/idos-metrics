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
     * The database connection/
     *
     * @var \Illuminate\Database\Connection
     */
    private $dbConnection;
    /**
     * The salts to use in hash functions.
     *
     * @var string
     */
    private $saltConfig;

    /**
     * Constructor.
     *
     * @param \Illuminate\Database\Connection $dbConnection
     * @param array $saltConfig
     */
    public function __construct(DbConnection $dbConnection, array $saltConfig) {
        $this->dbConnection = $dbConnection;
        $this->saltConfig = $saltConfig;
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
                $userId = $payload['user_id'];
                $credential = $payload['credential'];
                $source = $payload['source'];
                $data = [
                    'user_id' => $userId,
                    'provider' => $source['name'],
                    'sso' => (isset($source['tags']['sso']) && $source['tags']['sso'] === true) ? true : false
                ];

                $success = $this
                            ->dbConnection
                            ->transaction(function (DbConnection $dbConnection) use ($payload, $action, $created, $userId, $credential, $source, $data) {
                                $success = $dbConnection
                                    ->table('metrics')
                                    ->insert([
                                        'credential_public' => $credential['public'],
                                        'endpoint'          => $payload['endpoint'],
                                        'action'            => $action,
                                        'data'              => json_encode($data),
                                        'created_at'        => date('Y-m-d H:i:s', $created)
                                    ]);

                                $success = $success && $dbConnection
                                    ->statement(
                                        'INSERT INTO metrics_user (
                                        hash,
                                        credential_public,
                                        sources,
                                        data,
                                        gates,
                                        flags,
                                        created_at,
                                        updated_at
                                    ) VALUES (
                                        :hash,
                                        :credential_public,
                                        :sources,
                                        :data,
                                        :gates,
                                        :flags,
                                        :created_at,
                                        NULL
                                    )
                                    ON CONFLICT (hash)
                                    DO UPDATE SET sources = jsonb_set(metrics_user.sources, :source, \'true\', true), updated_at = :updated_at',
                                        [
                                            'hash'              => md5($this->saltConfig['user'] . (string) $userId),
                                            'credential_public' => $credential['public'],
                                            'sources'           => '{"' . $source['name'] . '": true}',
                                            'source'            => '{"' . $source['name'] . '"}',
                                            'data'              => '{}',
                                            'gates'             => '{}',
                                            'flags'             => '{}',
                                            'created_at'        => date('Y-m-d H:i:s', $created),
                                            'updated_at'        => date('Y-m-d H:i:s', $created)
                                        ]
                                    );

                                return $success;
                            });
                break;

            case 'profile:gate':
                $userId = $payload['user_id'];
                $credential = $payload['credential'];
                $gate = $payload['gate'];

                $success = $this
                            ->dbConnection
                            ->statement(
                                'INSERT INTO metrics_user (
                                hash,
                                credential_public,
                                sources,
                                data,
                                gates,
                                flags,
                                created_at,
                                updated_at
                            ) VALUES (
                                :hash,
                                :credential_public,
                                :sources,
                                :data,
                                :gates,
                                :flags,
                                :created_at,
                                NULL
                            )
                            ON CONFLICT (hash)
                            DO UPDATE SET gates = jsonb_set(metrics_user.gates, :gate, :pass, true), updated_at = :updated_at',
                                [
                                    'hash'              => md5($this->saltConfig['user'] . (string) $userId),
                                    'credential_public' => $credential['public'],
                                    'sources'           => '{}',
                                    'data'              => '{}',
                                    'gates'             => '{"' . $gate['name'] . '.' . $gate['confidence_level'] .'": ' . ($gate['pass'] === true ? 'true' : 'false') . '}',
                                    'flags'             => '{}',
                                    'gate'              => '{"' . $gate['name'] . '.' . $gate['confidence_level'] . '"}',
                                    'pass'              => ($gate['pass'] === true ? 'true' : 'false'),
                                    'created_at'        => date('Y-m-d H:i:s', $created),
                                    'updated_at'        => date('Y-m-d H:i:s', $created)
                                ]
                            );
                break;

            case 'profile:flag':
                $userId = $payload['user_id'];
                $credential = $payload['credential'];
                $flag = $payload['flag'];

                $success = $this
                            ->dbConnection
                            ->statement(
                                'INSERT INTO metrics_user (
                                hash,
                                credential_public,
                                sources,
                                data,
                                gates,
                                flags,
                                created_at,
                                updated_at
                            ) VALUES (
                                :hash,
                                :credential_public,
                                :sources,
                                :data,
                                :gates,
                                :flags,
                                :created_at,
                                NULL
                            )
                            ON CONFLICT (hash)
                            DO UPDATE SET flags = jsonb_set(metrics_user.flags, :flag, :attribute, true), updated_at = :updated_at',
                                [
                                    'hash'              => md5($this->saltConfig['user'] . (string) $userId),
                                    'credential_public' => $credential['public'],
                                    'sources'           => '{}',
                                    'data'              => '{}',
                                    'gates'             => '{}',
                                    'flags'             => '{"' . $flag['slug'] . '": "' . $flag['attribute'] . '"}',
                                    'flag'              => '{"' . $flag['slug'] . '"}',
                                    'attribute'         => '"' . $flag['attribute'] . '"',
                                    'created_at'        => date('Y-m-d H:i:s', $created),
                                    'updated_at'        => date('Y-m-d H:i:s', $created)
                                ]
                            );
                break;

            case 'profile:attribute':
                $userId = $payload['user_id'];
                $credential = $payload['credential'];
                $attribute = $payload['attribute'];

                $success = $this
                            ->dbConnection
                            ->statement(
                                'INSERT INTO metrics_user (
                                hash,
                                credential_public,
                                sources,
                                data,
                                gates,
                                flags,
                                created_at,
                                updated_at
                            ) VALUES (
                                :hash,
                                :credential_public,
                                :sources,
                                :data,
                                :gates,
                                :flags,
                                :created_at,
                                NULL
                            )
                            ON CONFLICT (hash)
                            DO UPDATE SET data = jsonb_set(metrics_user.data, :attribute, :attributeValue, true), updated_at = :updated_at',
                                [
                                    'hash'              => md5($this->saltConfig['user'] . (string) $userId),
                                    'credential_public' => $credential['public'],
                                    'sources'           => '{}',
                                    'attribute'         => '{"' . $attribute['name'] . '"}',
                                    'attributeValue'    => '"' . $attribute['value'] . '"',
                                    'data'              => '{"' . $attribute['name'] . '": "' . $attribute['value'] . '"}',
                                    'gates'             => '{}',
                                    'flags'             => '{}',
                                    'created_at'        => date('Y-m-d H:i:s', $created),
                                    'updated_at'        => date('Y-m-d H:i:s', $created)
                                ]
                            );
                break;

            default:
                return false;
        }

        return $success;
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
                        'INSERT INTO metrics_hourly ("credential_public", "endpoint", "action", "data", "count", "created_at")
                            SELECT
                                "credential_public",
                                "endpoint",
                                "action",
                                json_build_object(
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
                                "credential_public",
                                "data"->>\'sso\',
                                "data"->>\'provider\',
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
                        'INSERT INTO metrics_daily ("credential_public", "endpoint", "action", "data", "count", "created_at")
                            SELECT
                                "credential_public",
                                "endpoint",
                                "action",
                                json_build_object(
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
                                "credential_public",
                                "data"->>\'sso\',
                                "data"->>\'provider\',
                                "action",
                                DATE_TRUNC(\'day\', "created_at")'
                    );
                break;
        }
    }
}
