<?php

declare(strict_types = 1);

namespace Cli\Handler;

use Illuminate\Database\Connection as DbConnection;

/*
 * This class handles the metrics cron and daemon commands.
 */
class Metrics
{
    private $endpoints = [
        'company' => [
            'table' => 'company_metrics',
            'actor' => 'identity',
            'id'    => 'company'
        ],
        'company:credential' => [
            'table' => 'credential_metrics',
            'actor' => 'identity',
            'id'    => 'credential'
        ],
        'company:hook' => [
            'table' => 'hook_metrics',
            'actor' => 'identity',
            'id'    => 'hook'
        ],
        'company:invitation' => [
            'table' => 'invitation_metrics',
            'actor' => 'identity',
            'id'    => 'invitation'
        ],
        'company:member' => [
            'table' => 'member_metrics',
            'actor' => 'identity',
            'id'    => 'member'
        ],
        'company:permission' => [
            'table' => 'permission_metrics',
            'actor' => 'identity',
            'id'    => 'permission'
        ],
        'company:setting' => [
            'table' => 'setting_metrics',
            'actor' => 'identity',
            'id'    => 'setting'
        ],
        'profile:attribute' => [
            'table' => 'attribute_metrics',
            'actor' => 'credential',
            'id'    => 'attribute'
        ],
        'profile:candidate' => [
            'table' => 'candidate_metrics',
            'actor' => 'credential',
            'id'    => 'candidate'
        ],
        'profile:feature' => [
            'table' => 'feature_metrics',
            'actor' => 'credential',
            'id'    => 'feature'
        ],
        'profile:flag' => [
            'table' => 'flag_metrics',
            'actor' => 'credential',
            'id'    => 'flag'
        ],
        'profile:gate' => [
            'table' => 'gate_metrics',
            'actor' => 'credential',
            'id'    => 'gate'
        ],
        'profile:process' => [
            'table' => 'process_metrics',
            'actor' => 'credential',
            'id'    => 'process'
        ],
        'profile:raw' => [
            'table' => 'raw_metrics',
            'actor' => 'credential',
            'id'    => 'source'
        ],
        'profile:reference' => [
            'table' => 'reference_metrics',
            'actor' => 'credential',
            'id'    => 'reference'
        ],
        'profile:review' => [
            'table' => 'review_metrics',
            'actor' => 'identity',
            'id'    => 'review'
        ],
        'profile:score' => [
            'table' => 'score_metrics',
            'actor' => 'credential',
            'id'    => 'score'
        ],
        'profile:source' => [
            'table' => 'source_metrics',
            'actor' => 'credential',
            'id'    => 'source'
        ],
        'profile:tag' => [
            'table' => 'tag_metrics',
            'actor' => 'identity',
            'id'    => 'tag'
        ],
        'profile:task' => [
            'table' => 'task_metrics',
            'actor' => 'credential',
            'id'    => 'task'
        ]
    ];

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
        if (! array_key_exists($data['endpoint'], $this->endpoints)) {
            return false;
        }

        $properties = $this->endpoints[$data['endpoint']];

        return $this->dbConnection->table($properties['table'])->insert([
            $properties['actor'] . '_id' => $data['actor_id'],
            $properties['id'] . '_id' => $data['id'],
            'action' => $data['action'],
            'created_at' => date('Y-m-d H:i:s', $data['created_at'])
        ]);
    }

    /**
     * Separate metrics by a given interval in seconds. Note that this method also
     * deletes metrics older than the specified seconds from the *_metrics table
     *
     * @param int $seconds
     * @param string $toTablePostfix
     * @param string $fromTablePostfix
     */
    public function handleIntervalMetrics(int $seconds, string $toTablePostfix, string $fromTablePostfix = null) {
        foreach ($this->endpoints as $endpoint => $properties) {
            $query = '
            WITH summary AS (
                SELECT
                    c.id,
                    c.' . $properties['actor'] . '_id,
                    c.action,
                    c.created_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY to_timestamp(floor((extract(\'epoch\' from c.created_at) / ' . $seconds . ' )) * ' . $seconds . ') AT TIME ZONE \'UTC\'
                        ORDER BY c.id DESC) AS rowKey
                    FROM ' . $properties['table'] . $fromTablePostfix . ' c
                    WHERE c.created_at <= \'' . date('Y-m-d H:i:s', (time() - $seconds)) . '\'
            )
            SELECT s.* FROM summary s WHERE s.rowKey = 1';
            $hourlyMetrics = $this->dbConnection->select($query);

            if (empty($hourlyMetrics)) {
                continue;
            }

            foreach ($hourlyMetrics as $hourlyMetric) {
                unset($hourlyMetric->id);
                unset($hourlyMetric->rowkey);

                $this
                    ->dbConnection
                    ->table($properties['table'] . $toTablePostfix)
                    ->insert((array) $hourlyMetric);
            }

            $this->dbConnection->table($properties['table'] . $fromTablePostfix)->where('created_at', '<=', date('Y-m-d H:i:s', (time() - $seconds)))->delete();
        }
    }

    /**
     * Separate metrics
     */
    public function handleHourlyMetrics() {
        $this->handleIntervalMetrics(3600, '_hourly');
    }

    /**
     * Handles metrics that
     */
    public function handleDailyMetrics() {
        $this->handleIntervalMetrics(24 * 3600, '_daily', '_hourly');
    }
}
