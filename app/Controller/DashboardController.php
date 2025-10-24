<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\{AppRule, MessageRule, AlertLog, CronExecutionLog};
use Elasticsearch\Client;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface as HttpResponse};
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * @AutoController()
 */
class DashboardController
{
    #[Inject]
    protected Client $es;

    private const BATCH_SIZE = 1000; // Ukuran batch untuk scan
    private const SCROLL_TIMEOUT = '2m'; // Timeout untuk scroll context

    /**
     * Get dashboard overview statistics (Full scan - optimized)
     */
    public function overview(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $startTime = microtime(true);

            // Get all service indices
            $allIndices = $this->es->cat()->indices(['format' => 'json']);
            $serviceIndices = $this->filterServiceIndices($allIndices);

            if (empty($serviceIndices)) {
                return $response->json([
                    'status' => 'error',
                    'message' => 'No service indices found'
                ]);
            }

            // Parallel data gathering
            $metrics = [
                'total_logs' => $this->getTotalLogsOptimized($serviceIndices),
                'active_apps' => $this->getActiveAppsFromDatabase(),
                'violations' => $this->getViolationsOptimized($serviceIndices),
                'avg_duration' => $this->getAvgDurationOptimized($serviceIndices),
                'log_level_distribution' => $this->getLogLevelDistribution($serviceIndices),
                'recent_violations' => $this->getRecentViolations($serviceIndices),
            ];

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return $response->json([
                'status' => 'success',
                'data' => $metrics,
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'indices_scanned' => count($serviceIndices),
                    'scan_type' => 'full'
                ]
            ]);

        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get log trends by time range and level for multi-line chart
     */
    public function logTrends(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $startTime = microtime(true);
            
            // Get range parameter (24h, 7d, 30d)
            $range = $request->input('range', '24h');
            $interval = $this->getIntervalForRange($range);
            $timeRange = $this->getTimeRangeForPeriod($range);
            
            // Get all service indices
            $allIndices = $this->es->cat()->indices(['format' => 'json']);
            $serviceIndices = $this->filterServiceIndices($allIndices);

            if (empty($serviceIndices)) {
                return $response->json([
                    'status' => 'error',
                    'message' => 'No service indices found'
                ]);
            }

            // Build Elasticsearch query for time-based aggregation
            $params = [
                'index' => implode(',', $serviceIndices),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['exists' => ['field' => 'app_name']],
                                [
                                    'range' => [
                                        '@timestamp' => [
                                            'gte' => $timeRange['from'],
                                            'lte' => $timeRange['to']
                                        ]
                                    ]
                                ]
                            ],
                            'must_not' => [
                                ['term' => ['app_name' => '']],
                                ['term' => ['app_name.keyword' => 'null']]
                            ]
                        ]
                    ],
                    'aggs' => [
                        'by_time' => [
                            'date_histogram' => [
                                'field' => '@timestamp',
                                'calendar_interval' => $interval,
                                'min_doc_count' => 0,
                                'extended_bounds' => [
                                    'min' => $timeRange['from'],
                                    'max' => $timeRange['to']
                                ]
                            ],
                            'aggs' => [
                                'by_level' => [
                                    'terms' => [
                                        'field' => 'level_name.keyword',
                                        'size' => 10
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $result = $this->es->search($params);
            $buckets = $result['aggregations']['by_time']['buckets'] ?? [];

            // Process data for multi-line chart
            $chartData = $this->processTrendDataForChart($buckets, $range);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return $response->json([
                'status' => 'success',
                'data' => $chartData,
                'meta' => [
                    'range' => $range,
                    'interval' => $interval,
                    'time_range' => $timeRange,
                    'execution_time_ms' => $executionTime,
                    'indices_scanned' => count($serviceIndices)
                ]
            ]);

        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Endpoint: App performance summary with configurable range (24h, 7d, 30d)
     */
    public function appPerformance(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        try {
            $range = $request->input('range', '24h');

            // Get indices as in overview
            $allIndices = $this->es->cat()->indices(['format' => 'json']);
            $serviceIndices = $this->filterServiceIndices($allIndices);
            if (empty($serviceIndices)) {
                return $response->json([
                    'status' => 'error',
                    'message' => 'No service indices found'
                ]);
            }

            $data = $this->getAppPerformanceSummaryByRange($serviceIndices, $range);
            return $response->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [ 'range' => $range ]
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getTotalLogsOptimized(array $indices): int
    {
        try {
            $params = [
                'index' => implode(',', $indices),
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['exists' => ['field' => 'app_name']]
                            ],
                            'must_not' => [
                                ['term' => ['app_name' => '']],
                                ['term' => ['app_name.keyword' => 'null']]
                            ]
                        ]
                    ]
                ]
            ];

            $result = $this->es->count($params);
            return $result['count'] ?? 0;

        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getActiveAppsFromDatabase(): array
    {
        try {
            $appRules = AppRule::where('is_active', true)->get();
            
            $apps = [];
            foreach ($appRules as $rule) {
                $apps[] = [
                    'name' => $rule->app_name,
                    'max_duration' => $rule->max_duration,
                ];
            }

            return [
                'total' => count($apps),
                'apps' => $apps
            ];

        } catch (\Exception $e) {
            return ['total' => 0, 'apps' => []];
        }
    }

    private function getViolationsOptimized(array $indices): array
    {
        try {
            // Load rules from database
            $appRules = AppRule::where('is_active', true)->get()->keyBy('app_name');
            $messageRules = MessageRule::where('is_active', true)->get();
            
            // Index message rules untuk lookup lebih cepat
            $messageLimits = [];
            foreach ($messageRules as $rule) {
                $messageLimits[$rule->app_name][$rule->message_key] = (int) $rule->max_duration;
            }

            $appViolations = 0;
            $messageViolations = 0;
            $violationsByApp = [];

            // Initialize scroll
            $params = [
                'index' => implode(',', $indices),
                'scroll' => self::SCROLL_TIMEOUT,
                'size' => self::BATCH_SIZE,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['exists' => ['field' => 'app_name']],
                                ['exists' => ['field' => 'extra.duration_ms']]
                            ],
                            'must_not' => [
                                ['term' => ['app_name' => '']],
                                ['term' => ['app_name.keyword' => 'null']]
                            ]
                        ]
                    ],
                    '_source' => ['app_name', 'extra.duration_ms', 'message', 'context.message_key', 'message_key']
                ]
            ];

            $result = $this->es->search($params);
            $scrollId = $result['_scroll_id'];
            $totalProcessed = 0;

            // Process batches
            while (true) {
                $hits = $result['hits']['hits'] ?? [];
                
                if (empty($hits)) {
                    break;
                }

                foreach ($hits as $hit) {
                    $src = $hit['_source'];
                    $app = $src['app_name'] ?? null;
                    
                    if (!$app) continue;

                    $extra = is_string($src['extra'] ?? null) 
                        ? json_decode($src['extra'], true) 
                        : ($src['extra'] ?? []);
                    
                    $duration = $extra['duration_ms'] ?? null;
                    
                    if ($duration === null) continue;

                    // Check app rule violation
                    if (isset($appRules[$app])) {
                        $maxDuration = (int) $appRules[$app]->max_duration;
                        if ($duration > $maxDuration) {
                            $appViolations++;
                            $violationsByApp[$app] = ($violationsByApp[$app] ?? 0) + 1;
                        }
                    }

                    // Check message rule violation
                    $msgKey = $src['message_key'] 
                        ?? ($src['context']['message_key'] ?? null)
                        ?? ($src['message'] ?? null);

                    if ($msgKey && isset($messageLimits[$app][$msgKey])) {
                        if ($duration > $messageLimits[$app][$msgKey]) {
                            $messageViolations++;
                            $violationsByApp[$app] = ($violationsByApp[$app] ?? 0) + 1;
                        }
                    }
                }

                $totalProcessed += count($hits);

                // Get next batch
                $result = $this->es->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => self::SCROLL_TIMEOUT
                ]);

                $scrollId = $result['_scroll_id'];
            }

            // Clear scroll context
            try {
                $this->es->clearScroll(['scroll_id' => $scrollId]);
            } catch (\Exception $e) {
                // Ignore clear scroll errors
            }

            // Sort violations by app
            arsort($violationsByApp);
            $topViolations = array_slice($violationsByApp, 0, 10, true);
            
            $byAppFormatted = [];
            foreach ($topViolations as $appName => $count) {
                $byAppFormatted[] = [
                    'app_name' => $appName,
                    'count' => $count
                ];
            }

            $totalViolations = $appViolations + $messageViolations;
            $violationRate = $totalProcessed > 0 
                ? round(($totalViolations / $totalProcessed) * 100, 2) 
                : 0;

            return [
                'total' => $totalViolations,
                'by_type' => [
                    'app' => $appViolations,
                    'message' => $messageViolations
                ],
                'by_app' => $byAppFormatted,
                'violation_rate' => $violationRate,
                'logs_processed' => $totalProcessed
            ];

        } catch (\Exception $e) {
            return [
                'total' => 0,
                'by_type' => ['app' => 0, 'message' => 0],
                'by_app' => [],
                'violation_rate' => 0,
                'logs_processed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getAvgDurationOptimized(array $indices): array
    {
        try {
            // Gunakan aggregation untuk menghitung avg (lebih cepat dari scroll)
            $params = [
                'index' => implode(',', $indices),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['exists' => ['field' => 'extra.duration_ms']]
                            ]
                        ]
                    ],
                    'aggs' => [
                        'avg_duration' => [
                            'avg' => ['field' => 'extra.duration_ms']
                        ],
                        'max_duration' => [
                            'max' => ['field' => 'extra.duration_ms']
                        ],
                        'min_duration' => [
                            'min' => ['field' => 'extra.duration_ms']
                        ],
                        'count_with_duration' => [
                            'value_count' => ['field' => 'extra.duration_ms']
                        ],
                        'by_app' => [
                            'terms' => [
                                'field' => 'app_name.keyword',
                                'size' => 50,
                                'order' => ['avg_duration' => 'desc']
                            ],
                            'aggs' => [
                                'avg_duration' => [
                                    'avg' => ['field' => 'extra.duration_ms']
                                ],
                                'max_duration' => [
                                    'max' => ['field' => 'extra.duration_ms']
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $result = $this->es->search($params);
            $aggs = $result['aggregations'] ?? [];

            $byApp = [];
            foreach ($aggs['by_app']['buckets'] ?? [] as $bucket) {
                $byApp[] = [
                    'app' => $bucket['key'],
                    'avg_duration_ms' => round($bucket['avg_duration']['value'] ?? 0, 2),
                    'max_duration_ms' => round($bucket['max_duration']['value'] ?? 0, 2),
                    'count' => $bucket['doc_count']
                ];
            }

            return [
                'overall_ms' => round($aggs['avg_duration']['value'] ?? 0, 2),
                'max_ms' => round($aggs['max_duration']['value'] ?? 0, 2),
                'min_ms' => round($aggs['min_duration']['value'] ?? 0, 2),
                'total_records' => $aggs['count_with_duration']['value'] ?? 0,
                'by_app' => $byApp
            ];

        } catch (\Exception $e) {
            return [
                'overall_ms' => 0,
                'max_ms' => 0,
                'min_ms' => 0,
                'total_records' => 0,
                'by_app' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    private function getLogLevelDistribution(array $indices): array
    {
        try {
            $params = [
                'index' => implode(',', $indices),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['exists' => ['field' => 'app_name']]
                            ],
                            'must_not' => [
                                ['term' => ['app_name' => '']],
                                ['term' => ['app_name.keyword' => 'null']]
                            ]
                        ]
                    ],
                    'aggs' => [
                        'by_level' => [
                            'terms' => [
                                'field' => 'level_name.keyword',
                                'size' => 20,
                                'order' => ['_count' => 'desc']
                            ]
                        ],
                        'total_count' => [
                            'value_count' => ['field' => 'level_name.keyword']
                        ]
                    ]
                ]
            ];

            $result = $this->es->search($params);
            $aggs = $result['aggregations'] ?? [];
            $buckets = $aggs['by_level']['buckets'] ?? [];
            $totalLogs = $aggs['total_count']['value'] ?? 0;

            $distribution = [];
            $colors = [
                'INFO' => '#10b981',      // Green
                'WARNING' => '#f59e0b',   // Orange
                'ERROR' => '#ef4444',     // Red
                'DEBUG' => '#6366f1',     // Indigo
                'CRITICAL' => '#dc2626',  // Dark Red
                'NOTICE' => '#06b6d4',    // Cyan
                'ALERT' => '#f97316',     // Orange-Red
                'EMERGENCY' => '#991b1b', // Very Dark Red
            ];

            foreach ($buckets as $bucket) {
                $level = strtoupper($bucket['key']);
                $count = $bucket['doc_count'];
                $percentage = $totalLogs > 0 ? round(($count / $totalLogs) * 100, 2) : 0;

                $distribution[] = [
                    'level' => $level,
                    'count' => $count,
                    'percentage' => $percentage,
                    'color' => $colors[$level] ?? '#94a3b8' // Default gray
                ];
            }

            // Sort by count descending
            usort($distribution, fn($a, $b) => $b['count'] <=> $a['count']);

            return [
                'total' => $totalLogs,
                'distribution' => $distribution
            ];

        } catch (\Exception $e) {
            return [
                'total' => 0,
                'distribution' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    private function processTrendDataForChart(array $buckets, string $range): array
    {
        // Define log levels we want to track
        $logLevels = ['INFO', 'WARNING', 'ERROR', 'DEBUG', 'CRITICAL'];
        $levelColors = [
            'INFO' => '#10b981',      // Green
            'WARNING' => '#f59e0b',   // Orange
            'ERROR' => '#ef4444',     // Red
            'DEBUG' => '#6366f1',     // Indigo
            'CRITICAL' => '#dc2626',  // Dark Red
        ];

        // Initialize data structure
        $datasets = [];
        $labels = [];
        $timeFormat = $this->getTimeFormatForRange($range);

        // Initialize datasets for each log level
        foreach ($logLevels as $level) {
            $datasets[] = [
                'label' => $level,
                'data' => [],
                'borderColor' => $levelColors[$level] ?? '#94a3b8',
                'backgroundColor' => ($levelColors[$level] ?? '#94a3b8') . '20',
                'fill' => false,
                'tension' => 0.1
            ];
        }

        // Process each time bucket
        foreach ($buckets as $bucket) {
            $timestamp = $bucket['key_as_string'];
            $labels[] = $this->formatTimestampForLabel($timestamp, $timeFormat);

            // Initialize count for each level
            $levelCounts = array_fill_keys($logLevels, 0);

            // Count logs by level in this time bucket
            foreach ($bucket['by_level']['buckets'] as $levelBucket) {
                $level = strtoupper($levelBucket['key']);
                if (in_array($level, $logLevels)) {
                    $levelCounts[$level] = $levelBucket['doc_count'];
                }
            }

            // Add data to each dataset
            foreach ($datasets as $index => $dataset) {
                $level = $logLevels[$index];
                $datasets[$index]['data'][] = $levelCounts[$level];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'summary' => $this->calculateTrendSummary($datasets, $logLevels)
        ];
    }

    private function calculateTrendSummary(array $datasets, array $logLevels): array
    {
        $summary = [];

        foreach ($datasets as $index => $dataset) {
            $level = $logLevels[$index];
            $data = $dataset['data'];
            
            if (!empty($data)) {
                $summary[$level] = [
                    'total' => array_sum($data),
                    'average' => round(array_sum($data) / count($data), 2),
                    'max' => max($data),
                    'min' => min($data),
                    'trend' => $this->calculateTrendDirection($data)
                ];
            } else {
                $summary[$level] = [
                    'total' => 0,
                    'average' => 0,
                    'max' => 0,
                    'min' => 0,
                    'trend' => 'stable'
                ];
            }
        }

        return $summary;
    }

    private function calculateTrendDirection(array $data): string
    {
        if (count($data) < 2) {
            return 'stable';
        }

        $firstHalf = array_slice($data, 0, (int) floor(count($data) / 2));
        $secondHalf = array_slice($data, (int) floor(count($data) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        // Avoid division by zero
        if ($firstAvg == 0) {
            // If first half average is 0, check if second half has any data
            if ($secondAvg > 0) {
                return 'up'; // Going from 0 to something is an increase
            } else {
                return 'stable'; // Both are 0
            }
        }

        $changePercent = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        if ($changePercent > 10) {
            return 'up';
        } elseif ($changePercent < -10) {
            return 'down';
        } else {
            return 'stable';
        }
    }

    private function getIntervalForRange(string $range): string
    {
        return match ($range) {
            '24h' => '1h',
            '7d' => '1d',
            '30d' => '1d',
            default => '1h'
        };
    }

    private function getTimeRangeForPeriod(string $range): array
    {
        $now = new \DateTime();
        $from = clone $now;

        switch ($range) {
            case '24h':
                $from->modify('-24 hours');
                break;
            case '7d':
                $from->modify('-7 days');
                break;
            case '30d':
                $from->modify('-30 days');
                break;
            default:
                $from->modify('-24 hours');
        }

        return [
            'from' => $from->format('Y-m-d\TH:i:s\Z'),
            'to' => $now->format('Y-m-d\TH:i:s\Z')
        ];
    }

    private function getTimeFormatForRange(string $range): string
    {
        return match ($range) {
            '24h' => 'H:i',
            '7d' => 'M d',
            '30d' => 'M d',
            default => 'H:i'
        };
    }

    private function formatTimestampForLabel(string $timestamp, string $format): string
    {
        $date = new \DateTime($timestamp);
        return $date->format($format);
    }

    private function filterServiceIndices(array $allIndices): array
    {
        $keywords = ['core', 'merchant', 'transaction', 'vendor'];
        $indices = [];

        foreach ($allIndices as $index) {
            foreach ($keywords as $keyword) {
                if (strpos($index['index'], $keyword) !== false) {
                    $indices[] = $index['index'];
                    break;
                }
            }
        }

        return $indices;
    }
    private function getAppPerformanceSummary(array $indices): array
    {
        try {
            // Ambil daftar app dari DB (hanya yang terdaftar di app_rules)
            $rules = AppRule::where('is_active', true)->get(['app_name', 'max_duration']);
            if ($rules->isEmpty()) {
                return ['total' => 0, 'items' => []];
            }

            $apps = [];
            $thresholds = [];
            foreach ($rules as $r) {
                $apps[] = $r->app_name;
                $thresholds[$r->app_name] = (int) $r->max_duration;
            }

            // Window waktu: 24 jam terakhir dan 24 jam sebelumnya
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $from = (clone $now)->modify('-24 hours');
            $prevFrom = (clone $from)->modify('-24 hours');

            $formatIso = fn($d) => $d->format('Y-m-d\TH:i:s\Z');

            $params = [
                'index' => implode(',', $indices),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['terms' => ['app_name.keyword' => $apps]],
                                ['exists' => ['field' => 'extra.duration_ms']],
                                [
                                    'range' => [
                                        '@timestamp' => [
                                            'gte' => $formatIso($from),
                                            'lte' => $formatIso($now)
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'aggs' => [
                        'by_app' => [
                            'terms' => [
                                'field' => 'app_name.keyword',
                                'size' => count($apps)
                            ],
                            'aggs' => [
                                'avg_duration' => ['avg' => ['field' => 'extra.duration_ms']],
                                'total_logs' => ['value_count' => ['field' => 'extra.duration_ms']]
                            ]
                        ],
                        // previous 24h
                        'prev' => [
                            'filter' => [
                                'range' => [
                                    '@timestamp' => [
                                        'gte' => $formatIso($prevFrom),
                                        'lte' => $formatIso($from)
                                    ]
                                ]
                            ],
                            'aggs' => [
                                'by_app' => [
                                    'terms' => [
                                        'field' => 'app_name.keyword',
                                        'size' => count($apps)
                                    ],
                                    'aggs' => [
                                        'avg_duration' => ['avg' => ['field' => 'extra.duration_ms']]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $result = $this->es->search($params);
            $currBuckets = $result['aggregations']['by_app']['buckets'] ?? [];
            $prevBuckets = $result['aggregations']['prev']['by_app']['buckets'] ?? [];

            // Mapkan hasil ES supaya mudah diakses, tapi jangan batasi hanya pada app yang ada log-nya
            $currMap = [];
            foreach ($currBuckets as $b) {
                $currMap[$b['key']] = [
                    'avg' => (float) ($b['avg_duration']['value'] ?? 0),
                    'total' => (int) ($b['total_logs']['value'] ?? 0),
                ];
            }

            $prevMap = [];
            foreach ($prevBuckets as $b) {
                $prevMap[$b['key']] = (float) ($b['avg_duration']['value'] ?? 0);
            }

            $items = [];
            // Bentuk items untuk SEMUA app yang ada di rules, meski tidak ada log (isi default)
            foreach ($apps as $app) {
                $avg = $currMap[$app]['avg'] ?? 0.0;
                $total = $currMap[$app]['total'] ?? 0;
                $threshold = $thresholds[$app] ?? 0;
                $status = $avg <= $threshold ? 'good' : 'bad';

                $prevAvg = $prevMap[$app] ?? 0.0;
                $trend = 'stable';
                if ($prevAvg > 0) {
                    $diff = $prevAvg - $avg; // durasi turun = performa naik
                    $changePct = ($diff / $prevAvg) * 100;
                    if ($changePct > 10) {
                        $trend = 'up'; // performa naik
                    } elseif ($changePct < -10) {
                        $trend = 'down'; // performa turun
                    }
                }

                $items[] = [
                    'app_name' => $app,
                    'total_logs' => $total,
                    'avg_duration_ms' => round($avg, 2),
                    'status' => $status,
                    'trend' => $trend,
                    'threshold_ms' => $threshold,
                ];
            }

            // Urutkan dari performa terbaik (avg kecil) ke terburuk (avg besar)
            usort($items, fn($a, $b) => $a['avg_duration_ms'] <=> $b['avg_duration_ms']);

            return [
                'total' => count($items),
                'items' => $items
            ];

        } catch (\Exception $e) {
            return [
                'total' => 0,
                'items' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    private function getAppPerformanceSummaryByRange(array $indices, string $range): array
    {
        try {
            // Ambil daftar app dari DB (hanya yang terdaftar di app_rules)
            $rules = AppRule::where('is_active', true)->get(['app_name', 'max_duration']);
            if ($rules->isEmpty()) {
                return ['total' => 0, 'items' => []];
            }

            $apps = [];
            $thresholds = [];
            foreach ($rules as $r) {
                $apps[] = $r->app_name;
                $thresholds[$r->app_name] = (int) $r->max_duration;
            }

            // Tentukan window berdasarkan range
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $from = clone $now;
            switch ($range) {
                case '7d': $from->modify('-7 days'); break;
                case '30d': $from->modify('-30 days'); break;
                default: $from->modify('-24 hours');
            }
            $prevFrom = clone $from;
            switch ($range) {
                case '7d': $prevFrom->modify('-7 days'); break;
                case '30d': $prevFrom->modify('-30 days'); break;
                default: $prevFrom->modify('-24 hours');
            }
            $formatIso = fn($d) => $d->format('Y-m-d\TH:i:s\Z');

            $params = [
                'index' => implode(',', $indices),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['terms' => ['app_name.keyword' => $apps]],
                                ['exists' => ['field' => 'extra.duration_ms']],
                                [ 'range' => ['@timestamp' => [ 'gte' => $formatIso($from), 'lte' => $formatIso($now) ]] ]
                            ]
                        ]
                    ],
                    'aggs' => [
                        'by_app' => [
                            'terms' => [ 'field' => 'app_name.keyword', 'size' => count($apps) ],
                            'aggs' => [
                                'avg_duration' => ['avg' => ['field' => 'extra.duration_ms']],
                                'total_logs' => ['value_count' => ['field' => 'extra.duration_ms']]
                            ]
                        ],
                        'prev' => [
                            'filter' => [ 'range' => ['@timestamp' => [ 'gte' => $formatIso($prevFrom), 'lte' => $formatIso($from) ]] ],
                            'aggs' => [
                                'by_app' => [
                                    'terms' => [ 'field' => 'app_name.keyword', 'size' => count($apps) ],
                                    'aggs' => [ 'avg_duration' => ['avg' => ['field' => 'extra.duration_ms']] ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $result = $this->es->search($params);
            $currBuckets = $result['aggregations']['by_app']['buckets'] ?? [];
            $prevBuckets = $result['aggregations']['prev']['by_app']['buckets'] ?? [];

            $currMap = [];
            foreach ($currBuckets as $b) {
                $currMap[$b['key']] = [
                    'avg' => (float) ($b['avg_duration']['value'] ?? 0),
                    'total' => (int) ($b['total_logs']['value'] ?? 0),
                ];
            }
            $prevMap = [];
            foreach ($prevBuckets as $b) {
                $prevMap[$b['key']] = (float) ($b['avg_duration']['value'] ?? 0);
            }

            $items = [];
            foreach ($apps as $app) {
                $avg = $currMap[$app]['avg'] ?? 0.0;
                $total = $currMap[$app]['total'] ?? 0;
                $threshold = $thresholds[$app] ?? 0;
                $status = $avg <= $threshold ? 'good' : 'bad';
                $prevAvg = $prevMap[$app] ?? 0.0;
                $trend = 'stable';
                if ($prevAvg > 0) {
                    $diff = $prevAvg - $avg;
                    $changePct = ($diff / $prevAvg) * 100;
                    if ($changePct > 10) { $trend = 'up'; } elseif ($changePct < -10) { $trend = 'down'; }
                }
                $items[] = [
                    'app_name' => $app,
                    'total_logs' => $total,
                    'avg_duration_ms' => round($avg, 2),
                    'status' => $status,
                    'trend' => $trend,
                    'threshold_ms' => $threshold,
                ];
            }

            usort($items, fn($a, $b) => $a['avg_duration_ms'] <=> $b['avg_duration_ms']);
            return ['total' => count($items), 'items' => $items];
        } catch (\Exception $e) {
            return ['total' => 0, 'items' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Get recent violations from Elasticsearch (last 24 hours)
     */
    private function getRecentViolations(array $indices): array
    {
        try {
            // Load rules from database
            $appRules = AppRule::where('is_active', true)->get()->keyBy('app_name');
            $messageRules = MessageRule::where('is_active', true)->get();
            
            // Index message rules untuk lookup lebih cepat
            $messageLimits = [];
            foreach ($messageRules as $rule) {
                $messageLimits[$rule->app_name][$rule->message_key] = (int) $rule->max_duration;
            }

            // Window waktu: 24 jam terakhir
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $from = (clone $now)->modify('-24 hours');
            $formatIso = fn($d) => $d->format('Y-m-d\TH:i:s\Z');

            $params = [
                'index' => implode(',', $indices),
                'body' => [
                    'size' => 100, // Ambil 100 log terbaru untuk diproses
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['exists' => ['field' => 'app_name']],
                                ['exists' => ['field' => 'extra.duration_ms']],
                                [
                                    'range' => [
                                        '@timestamp' => [
                                            'gte' => $formatIso($from),
                                            'lte' => $formatIso($now)
                                        ]
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
                        '@timestamp' => ['order' => 'desc']
                    ],
                    '_source' => ['@timestamp', 'app_name', 'extra.duration_ms', 'message', 'context.message_key', 'message_key']
                ]
            ];

            $result = $this->es->search($params);
            $hits = $result['hits']['hits'] ?? [];

            $violations = [];
            foreach ($hits as $hit) {
                $src = $hit['_source'];
                $app = $src['app_name'] ?? null;
                
                if (!$app) continue;

                $extra = is_string($src['extra'] ?? null) 
                    ? json_decode($src['extra'], true) 
                    : ($src['extra'] ?? []);
                
                $duration = $extra['duration_ms'] ?? null;
                
                if ($duration === null) continue;

                $violation = null;

                // Check app rule violation
                if (isset($appRules[$app])) {
                    $maxDuration = (int) $appRules[$app]->max_duration;
                    if ($duration > $maxDuration) {
                        $violation = [
                            'app' => $app,
                            'message' => $src['message'] ?? 'Unknown',
                            'time' => $src['@timestamp'],
                            'overage' => $duration - $maxDuration,
                            'type' => 'app',
                            'threshold' => $maxDuration,
                            'duration' => $duration
                        ];
                    }
                }

                // Check message rule violation (prioritize message rule over app rule)
                $msgKey = $src['message_key'] 
                    ?? ($src['context']['message_key'] ?? null)
                    ?? ($src['message'] ?? null);

                if ($msgKey && isset($messageLimits[$app][$msgKey])) {
                    if ($duration > $messageLimits[$app][$msgKey]) {
                        $violation = [
                            'app' => $app,
                            'message' => $msgKey,
                            'time' => $src['@timestamp'],
                            'overage' => $duration - $messageLimits[$app][$msgKey],
                            'type' => 'message',
                            'threshold' => $messageLimits[$app][$msgKey],
                            'duration' => $duration
                        ];
                    }
                }

                if ($violation) {
                    $violations[] = $violation;
                }
            }

            // Sort by time descending and limit to 10 most recent
            usort($violations, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
            $violations = array_slice($violations, 0, 10);

            // Format untuk frontend
            $formattedViolations = [];
            foreach ($violations as $violation) {
                $overage = $violation['overage'];
                
                // Determine icon and color based on overage severity
                if ($overage > 100) {
                    $icon = '🔥';
                    $color = '#dc2626'; // Red
                } elseif ($overage > 50) {
                    $icon = '⚠️';
                    $color = '#ea580c'; // Orange-red
                } else {
                    $icon = '⚡';
                    $color = '#f59e0b'; // Orange
                }

                // Calculate time ago
                $timeAgo = $this->getTimeAgo($violation['time']);

                $formattedViolations[] = [
                    'app' => $violation['app'],
                    'message' => $violation['message'],
                    'time' => $timeAgo,
                    'overage' => $overage,
                    'icon' => $icon,
                    'color' => $color,
                    'type' => $violation['type'],
                    'threshold' => $violation['threshold'],
                    'duration' => $violation['duration']
                ];
            }

            return $formattedViolations;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calculate time ago string
     */
    private function getTimeAgo(string $timestamp): string
    {
        $now = new \DateTime();
        $time = new \DateTime($timestamp);
        $diff = $now->diff($time);

        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' min ago';
        } else {
            return 'Just now';
        }
    }
}