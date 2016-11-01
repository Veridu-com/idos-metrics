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
     * Metrics endpoint list.
     *
     * @var array
     */
    private $endpoints = [
        'company' => [
            'metricsTable' => 'company_metrics',
            'actor' => 'identity',
            'idColumn'    => 'company'
        ],
        'company:credential' => [
            'metricsTable' => 'credential_metrics',
            'actor' => 'identity',
            'idColumn'    => 'credential'
        ],
        'company:hook' => [
            'metricsTable' => 'hook_metrics',
            'actor' => 'identity',
            'idColumn'    => 'hook'
        ],
        'company:invitation' => [
            'metricsTable' => 'invitation_metrics',
            'actor' => 'identity',
            'idColumn'    => 'invitation'
        ],
        'company:member' => [
            'metricsTable' => 'member_metrics',
            'actor' => 'identity',
            'idColumn'    => 'member'
        ],
        'company:permission' => [
            'metricsTable' => 'permission_metrics',
            'actor' => 'identity',
            'idColumn'    => 'permission'
        ],
        'company:setting' => [
            'metricsTable' => 'setting_metrics',
            'actor' => 'identity',
            'idColumn'    => 'setting'
        ],
        'profile:attribute' => [
            'metricsTable' => 'attribute_metrics',
            'actor' => 'credential',
            'idColumn'    => 'attribute'
        ],
        'profile:candidate' => [
            'metricsTable' => 'candidate_metrics',
            'actor' => 'credential',
            'idColumn'    => 'candidate'
        ],
        'profile:feature' => [
            'metricsTable' => 'feature_metrics',
            'actor' => 'credential',
            'idColumn'    => 'feature'
        ],
        'profile:flag' => [
            'metricsTable' => 'flag_metrics',
            'actor' => 'credential',
            'idColumn'    => 'flag'
        ],
        'profile:gate' => [
            'metricsTable' => 'gate_metrics',
            'actor' => 'credential',
            'idColumn'    => 'gate'
        ],
        'profile:process' => [
            'metricsTable' => 'process_metrics',
            'actor' => 'credential',
            'idColumn'    => 'process'
        ],
        'profile:raw' => [
            'metricsTable' => 'raw_metrics',
            'actor' => 'credential',
            'idColumn'    => 'raw'
        ],
        'profile:reference' => [
            'metricsTable' => 'reference_metrics',
            'actor' => 'credential',
            'idColumn'    => 'reference'
        ],
        'profile:review' => [
            'metricsTable' => 'review_metrics',
            'actor' => 'identity',
            'idColumn'    => 'review'
        ],
        'profile:score' => [
            'metricsTable' => 'score_metrics',
            'actor' => 'credential',
            'idColumn'    => 'score'
        ],
        'profile:source' => [
            'metricsTable' => 'source_metrics',
            'actor' => 'credential',
            'idColumn'    => 'source'
        ],
        'profile:tag' => [
            'metricsTable' => 'tag_metrics',
            'actor' => 'identity',
            'idColumn'    => 'tag'
        ],
        'profile:task' => [
            'metricsTable' => 'task_metrics',
            'actor' => 'credential',
            'idColumn'    => 'task'
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
        $table = $properties['metricsTable'];
        $actor = $properties['actor'];
        $idColumn = $properties['idColumn'];

        return $this->dbConnection->table($table)->insert([
            $actor . '_id' => $data[$actor . '_id'],
            $idColumn . '_id' => $data[$idColumn . '_id'],
            'action' => $data['action'],
            'created_at' => date('Y-m-d H:i:s', $data['created_at'])
        ]);
    }

    /**
     * Separate metrics by a given interval in seconds. Note that this method also
     * deletes metrics older than the specified seconds from the *_metrics table
     *
     * @param int    $seconds
     * @param string $toTablePostfix
     * @param string $fromTablePostfix
     */
    public function handleIntervalMetrics(int $seconds, string $toTablePostfix, string $fromTablePostfix = null) {
        foreach ($this->endpoints as $endpoint => $properties) {
            $table = $properties['metricsTable'];
            $actor = $properties['actor'];

            $metrics = $this
                ->dbConnection
                ->table($table . $fromTablePostfix)
                ->where('created_at', '<=', date('Y-m-d H:i:s', (time() - $seconds)))
                ->get(['*']);

            if (empty($metrics)) {
                continue;
            }

            $lastInsertedTimestamp = [];
            $intervalMetrics = [];
            foreach ($metrics as $metric) {
                $actorColumn = $actor . '_id';
                $group = $metric->$actorColumn . '-' . $metric->action;
                $metric->created_at = ((int) (strtotime($metric->created_at) / $seconds)) * $seconds;

                if (! isset($lastInsertedTimestamp[$group]) || $metric->created_at > ($lastInsertedTimestamp[$group] + $seconds - 1)) {
                    if (! property_exists($metric, 'count')) {
                        $metric->count = 1;
                    }

                    $intervalMetrics[$group][] = $metric;
                    $lastInsertedTimestamp[$group] = $metric->created_at;

                    continue;
                }

                $intervalMetrics[$group][count($intervalMetrics[$group]) - 1]->count += property_exists($metric, 'count') ? $metric->count : 1;
            }

            $deleteIds = [];
            foreach ($intervalMetrics as $group => $metrics) {
                foreach ($metrics as $metric) {
                    $deleteIds[] = $metric->id;
                    $idColumn = $properties['idColumn'] . '_id';

                    unset($metric->id);
                    unset($metric->$idColumn);
                    unset($metric->updated_at);

                    $metric->created_at = date('Y-m-d H:i:s', $metric->created_at);

                    $this
                        ->dbConnection
                        ->table($table . $toTablePostfix)
                        ->insert((array) $metric);
                }
            }

            $this
                ->dbConnection
                ->table($table . $fromTablePostfix)
                ->whereIn('id', $deleteIds)
                ->delete();
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
