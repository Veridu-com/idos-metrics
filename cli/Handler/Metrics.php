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
        'company'            => 'company_metrics',
        'company:credential' => 'credential_metrics',
        'company:hook'       => 'hook_metrics',
        'company:invitation' => 'invitation_metrics',
        'company:member'     => 'member_metrics',
        'company:permission' => 'permission_metrics',
        'company:setting'    => 'setting_metrics',
        'profile:attribute'  => 'attribute_metrics',
        'profile:candidate'  => 'candidate_metrics',
        'profile:feature'    => 'feature_metrics',
        'profile:flag'       => 'flag_metrics',
        'profile:gate'       => 'gate_metrics',
        'profile:process'    => 'process_metrics',
        'profile:raw'        => 'raw_metrics',
        'profile:reference'  => 'reference_metrics',
        'profile:review'     => 'review_metrics',
        'profile:score'      => 'score_metrics',
        'profile:source'     => 'source_metrics',
        'profile:tag'        => 'tag_metrics',
        'profile:task'       => 'task_metrics'
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

        $table = $this->endpoints[$data['endpoint']];

        return $this->dbConnection->table($table)->insert([
            'actor_id' => $data['actor_id'],
            'entity_id' => $data['entity_id'],
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
        foreach ($this->endpoints as $endpoint => $table) {
            $endpoint = 'profile:source';
            $table = 'source_metrics';
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
                $group = $metric->actor_id . '-' . $metric->action;
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

                    unset($metric->id);
                    unset($metric->entity_id);
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
