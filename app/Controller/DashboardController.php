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
     * Get total logs count - Optimized using count API
     */
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

    /**
     * Get active apps from database (app_rules table)
     */
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

    /**
     * Get violations - Optimized with batching using scroll API
     */
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

    /**
     * Get average duration - Optimized with aggregation
     */
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

    /**
     * Get log level distribution for donut chart
     */
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

    /**
     * Filter service indices
     */
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
}