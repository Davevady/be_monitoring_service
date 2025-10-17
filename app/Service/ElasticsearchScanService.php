<?php

declare(strict_types=1);

namespace App\Service;

use Elasticsearch\Client;
use Hyperf\Di\Annotation\Inject;
use App\Model\ScanCheckpoint;
use Carbon\Carbon;
use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class ElasticsearchScanService
{
    #[Inject]
    protected Client $es;

    #[Inject]
    protected LoggerInterface $logger;

    public function getActiveIndices(array $allowedKeywords = ['core', 'merchant', 'transaction', 'vendor']): array
    {
        $allIndices = $this->es->cat()->indices(['format' => 'json']);
        $indices = [];

        foreach ($allIndices as $index) {
            foreach ($allowedKeywords as $keyword) {
                if (strpos($index['index'], $keyword) !== false) {
                    $indices[] = $index['index'];
                    break;
                }
            }
        }

        return $indices;
    }

    public function scanLogs(string $indexName, ?ScanCheckpoint $checkpoint = null, int $batchSize = 500, ?array $searchAfter = null, ?string $fromTimestamp = null): array
    {
        // Tentukan titik mulai
        if ($fromTimestamp === null) {
            // null = full scan; bila ada checkpoint, pastikan berupa string datetime
            if ($checkpoint && $checkpoint->last_scanned_timestamp) {
                $ts = $checkpoint->last_scanned_timestamp;
                if ($ts instanceof \DateTimeInterface) {
                    $fromTimestamp = $ts->format('Y-m-d H:i:s');
                } else {
                    $fromTimestamp = (string) $ts;
                }
            } else {
                $fromTimestamp = null;
            }
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'size' => $batchSize,
                'query' => [
                    'bool' => [
                        'must' => [
                            // range by timestamp jika fromTimestamp ditentukan
                            ...(isset($fromTimestamp) && $fromTimestamp !== null ? [[
                                'range' => [
                                    '@timestamp' => [
                                        // gunakan gte untuk menjaga konsistensi pagination bersama search_after
                                        'gte' => $fromTimestamp
                                    ]
                                ]
                            ]] : []),
                            [
                                'exists' => [
                                    'field' => 'extra.duration_ms'
                                ]
                            ],
                            [
                                'exists' => [
                                    'field' => 'app_name'
                                ]
                            ]
                        ],
                        'must_not' => [
                            ['term' => ['app_name' => '']],
                            ['term' => ['app_name.keyword' => 'null']]
                        ]
                    ]
                ],
                'sort' => [
                    ['@timestamp' => ['order' => 'asc']],
                    ['_id' => ['order' => 'asc']]
                ],
                'track_total_hits' => true
            ]
        ];

        // Pagination dengan search_after jika tersedia
        if ($searchAfter !== null) {
            $params['body']['search_after'] = $searchAfter;
        }

        // Pastikan format tanggal kompatibel dengan ES (ISO 8601) bila fromTimestamp ada dalam format Y-m-d H:i:s
        if ($fromTimestamp !== null) {
            // Jika tidak mengandung 'T', convert ke ISO 8601 (UTC) untuk menghindari parse_exception
            if (strpos($fromTimestamp, 'T') === false) {
                $iso = gmdate('Y-m-d\TH:i:s\Z', strtotime($fromTimestamp));
                $params['body']['query']['bool']['must'][0]['range']['@timestamp']['gte'] = $iso;
            }
        }

        try {
            $result = $this->es->search($params);
            return $this->parseLogs($result['hits']['hits'] ?? []);
        } catch (\Exception $e) {
            $this->logger->error('ES Scan Error: ' . $e->getMessage());
            return [];
        }
    }

    private function parseLogs(array $hits): array
    {
        $logs = [];

        foreach ($hits as $hit) {
            $src = $hit['_source'];

            $context = $this->decodeJsonField($src['context'] ?? null);
            $extra = $this->decodeJsonField($src['extra'] ?? null);

            if (!isset($extra['duration_ms'])) {
                continue;
            }

            $logs[] = [
                'index' => $hit['_index'],
                'id' => $hit['_id'],
                'timestamp' => $src['@timestamp'] ?? $src['datetime'] ?? null,
                'app_name' => $src['app_name'] ?? null,
                'message' => $src['message'] ?? null,
                'level' => $src['level_name'] ?? null,
                'duration_ms' => (int) $extra['duration_ms'],
                'correlation_id' => $extra['correlation_id'] ?? $context['correlation_id'] ?? null,
                'context' => $context,
                'extra' => $extra,
            ];
        }

        return $logs;
    }

    private function decodeJsonField($field)
    {
        if ($field === null) {
            return null;
        }

        if (is_string($field)) {
            $decoded = json_decode($field, true);
            return $decoded !== null ? $decoded : $field;
        }

        return $field;
    }

    public function updateCheckpoint(string $indexName, string $lastTimestamp, string $lastId, int $logsScanned, int $alertsSent): void
    {
        try {
            // Pastikan timestamp dalam format yang benar
            $timestamp = $lastTimestamp;
            if (strpos($lastTimestamp, 'T') !== false) {
                // Convert ISO 8601 to MySQL datetime
                $timestamp = date('Y-m-d H:i:s', strtotime($lastTimestamp));
            }

            $checkpoint = ScanCheckpoint::firstOrNew(['index_name' => $indexName]);

            $checkpoint->last_scanned_timestamp = $timestamp;
            $checkpoint->last_scanned_id = $lastId;
            $checkpoint->last_scan_at = Carbon::now();

            if ($checkpoint->exists) {
                $checkpoint->total_logs_scanned += $logsScanned;
                $checkpoint->total_alerts_sent += $alertsSent;
            } else {
                $checkpoint->total_logs_scanned = $logsScanned;
                $checkpoint->total_alerts_sent = $alertsSent;
            }

            $checkpoint->save();
        } catch (\Exception $e) {
            $this->logger->error('Checkpoint update error: ' . $e->getMessage(), [
                'index' => $indexName,
                'timestamp' => $lastTimestamp,
            ]);
            throw $e;
        }
    }
}
