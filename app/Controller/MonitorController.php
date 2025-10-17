<?php

namespace App\Controller;

use Elasticsearch\ClientBuilder;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface as HttpResponse};
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * @AutoController()
 */
class MonitorController
{
    protected $es;

    public function __construct()
    {
        $this->es = ClientBuilder::create()
            ->setHosts(['elasticsearch7:9200']) // sesuaikan host docker
            ->build();
    }

    public function server(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        $logType = $request->query('log', null);
        $size    = (int) $request->query('size', 50);
        $level   = $request->query('level', null);
        $page    = max(1, (int) $request->query('page', 1));
        $search  = $request->query('search', null);

        // Ambil semua index
        $allIndices = $this->es->cat()->indices(['format' => 'json']);
        $indices = [];
        foreach ($allIndices as $index) {
            if ($logType) {
                if (strpos($index['index'], $logType) !== false) {
                    $indices[] = $index['index'];
                }
            } else {
                $indices[] = $index['index'];
            }
        }

        if (empty($indices)) {
            return $response->json([
                'status' => 'error',
                'message' => 'No indices found for this log type',
            ]);
        }

        // Build query
        $must = [];

        // Filter level jika ada
        if ($level) {
            $must[] = ['match' => ['level_name' => strtoupper($level)]];
        }

        // 👇 SEARCH QUERY - Simple Query String (Works on all ES versions)
        if ($search && trim($search) !== '') {
            $must[] = [
                'simple_query_string' => [
                    'query' => $search,
                    'fields' => [
                        'message^3',
                        'app_name^2',
                        'level_name',
                        'extra.correlation_id',
                        'context'
                    ],
                    'default_operator' => 'OR',
                    'lenient' => true
                ]
            ];
        } else {
            $must[] = ['match_all' => new \stdClass()];
        }

        // Filter exclude app_name null
        $mustNot = [
            ['bool' => [
                'should' => [
                    ['bool' => ['must_not' => ['exists' => ['field' => 'app_name']]]],
                    ['term' => ['app_name' => '']],
                    ['term' => ['app_name.keyword' => 'null']]
                ]
            ]]
        ];

        $from = ($page - 1) * $size;

        $params = [
            'index' => $indices,
            'body' => [
                'from' => $from,
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => $must,
                        'must_not' => $mustNot
                    ]
                ],
                'sort' => [
                    '@timestamp' => ['order' => 'desc']
                ],
                'track_total_hits' => true
            ]
        ];

        try {
            $result = $this->es->search($params);
            $logs = [];

            foreach ($result['hits']['hits'] as $hit) {
                $src = $hit['_source'];

                $appName = $src['app_name'] ?? null;
                if (empty($appName) || $appName === 'null') {
                    continue;
                }

                // 👇 Decode context dengan benar
                $context = null;
                if (isset($src['context'])) {
                    if (is_string($src['context'])) {
                        // Kalau context berupa string JSON, decode dulu
                        $context = json_decode($src['context'], true);
                    } elseif (is_array($src['context'])) {
                        // Kalau sudah array, pakai langsung
                        $context = $src['context'];
                    }
                }

                // 👇 Decode extra juga kalau perlu
                $extra = null;
                if (isset($src['extra'])) {
                    if (is_string($src['extra'])) {
                        $extra = json_decode($src['extra'], true);
                    } elseif (is_array($src['extra'])) {
                        $extra = $src['extra'];
                    }
                }

                $logs[] = [
                    'timestamp' => $src['@timestamp'] ?? null,
                    'app'       => $appName,
                    'level'     => $src['level_name'] ?? null,
                    'message'   => $src['message'] ?? null,
                    'context'   => $context,  // 👈 Sekarang jadi object/array
                    'extra'     => $extra,    // 👈 Sekarang jadi object/array
                    'file'      => $src['log']['file']['path'] ?? null,
                    'correlation_id' => $src['extra']['correlation_id'] ?? $extra['correlation_id'] ?? null,
                    'duration_ms'    => $src['extra']['duration_ms'] ?? $extra['duration_ms'] ?? null,
                ];
            }

            $total = $result['hits']['total']['value'];
            $totalPages = ceil($total / $size);

            return $response->json([
                'status' => 'success',
                'data'   => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $size,
                    'total'        => $total,
                    'total_pages'  => $totalPages,
                    'from'         => $from + 1,
                    'to'           => $from + count($logs),
                    'has_next'     => $page < $totalPages,
                    'has_prev'     => $page > 1,
                ],
                'filters' => [
                    'log_type' => $logType,
                    'level'    => $level,
                    'search'   => $search,
                ]
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function traceByCorrelation(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        $correlationId = $request->query('correlation_id');

        if (!$correlationId) {
            return $response->json([
                'status' => 'error',
                'message' => 'Parameter correlation_id wajib diisi'
            ], 400);
        }

        try {
            // Ambil semua index
            $allIndices = $this->es->cat()->indices(['format' => 'json']);
            $allowedKeywords = ['core', 'merchant', 'transaction', 'vendor'];
            $indices = [];

            foreach ($allIndices as $index) {
                foreach ($allowedKeywords as $keyword) {
                    if (strpos($index['index'], $keyword) !== false) {
                        $indices[] = $index['index'];
                        break; // Avoid duplicate
                    }
                }
            }

            if (empty($indices)) {
                return $response->json([
                    'status' => 'error',
                    'message' => 'Tidak ada index yang cocok dengan core/merchant/transaction/vendor'
                ], 404);
            }

            $params = [
                'index' => $indices,
                'body'  => [
                    'size' => 1000,
                    'query' => [
                        'term' => [
                            'extra.correlation_id.keyword' => $correlationId
                        ]
                    ],
                    'sort' => [
                        'datetime' => ['order' => 'asc']
                    ]
                ]
            ];

            $esResult = $this->es->search($params);
            $hits = $esResult['hits']['hits'] ?? [];

            // 👇 MAPPING DATA - Decode context & extra di dalam source
            $data = array_map(function ($hit) {
                $source = $hit['_source'];

                // 👇 Decode context jika berupa string JSON
                if (isset($source['context']) && is_string($source['context'])) {
                    $decoded = json_decode($source['context'], true);
                    if ($decoded !== null) {
                        $source['context'] = $decoded;
                    }
                }

                // 👇 Decode extra jika berupa string JSON
                if (isset($source['extra']) && is_string($source['extra'])) {
                    $decoded = json_decode($source['extra'], true);
                    if ($decoded !== null) {
                        $source['extra'] = $decoded;
                    }
                }

                return [
                    'index'     => $hit['_index'],
                    'id'        => $hit['_id'],
                    'timestamp' => $source['datetime'] ?? ($source['@timestamp'] ?? null),
                    'app_name'  => $source['app_name'] ?? null,
                    'message'   => $source['message'] ?? null,
                    'source'    => $source,  // 👈 Source dengan context & extra yang sudah di-decode
                ];
            }, $hits);

            // Fallback: sort lagi di PHP
            usort($data, function ($a, $b) {
                return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
            });

            return $response->json([
                'status' => 'success',
                'correlation_id' => $correlationId,
                'total' => $esResult['hits']['total']['value'] ?? 0,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function violationsByApp(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        $logType = $request->query('log', null);
        $size    = (int) $request->query('size', 10000);
        $level   = $request->query('level', null);

        // Ambil semua index
        $allIndices = $this->es->cat()->indices(['format' => 'json']);
        $indices = [];

        foreach ($allIndices as $index) {
            if ($logType) {
                if (strpos($index['index'], $logType) !== false) {
                    $indices[] = $index['index'];
                }
            } else {
                $indices[] = $index['index'];
            }
        }

        if (empty($indices)) {
            return $response->json([
                'status' => 'error',
                'message' => 'No indices found for this log type',
            ]);
        }

        // Build query dasar
        $must = [['match_all' => new \stdClass()]];
        if ($level) {
            $must[] = ['match' => ['level_name' => strtoupper($level)]];
        }

        $params = [
            'index' => $indices,
            'body' => [
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => $must
                    ]
                ],
                'sort' => [
                    '@timestamp' => ['order' => 'desc']
                ]
            ]
        ];

        try {
            $result = $this->es->search($params);

            // Ambil aturan dari DB (app_rules)
            $appRules = \App\Model\AppRule::all();
            $appLimits = [];
            foreach ($appRules as $r) {
                $appLimits[$r->app_name] = (int) $r->max_duration;
            }

            $violations = [];
            foreach ($result['hits']['hits'] as $hit) {
                $src = $hit['_source'];
                $app = $src['app_name'] ?? null;
                $duration = $src['extra']['duration_ms'] ?? null;

                if (!$app || $duration === null) {
                    continue;
                }

                if (isset($appLimits[$app]) && $duration > $appLimits[$app]) {
                    $violations[] = [
                        'timestamp' => $src['@timestamp'] ?? null,
                        'app'       => $app,
                        'level'     => $src['level_name'] ?? null,
                        'message'   => $src['message'] ?? null,
                        'duration_ms' => $duration,
                        'limit' => $appLimits[$app],
                        'correlation_id' => $src['extra']['correlation_id'] ?? null,
                    ];
                }
            }

            usort($violations, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);

            return $response->json([
                'status' => 'success',
                'total'  => count($violations),
                'data'   => $violations,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function violationsByMessage(RequestInterface $request, HttpResponse $response): PsrResponse
    {
        $logType = $request->query('log', null);
        $size    = (int) $request->query('size', 10000);
        $level   = $request->query('level', null);

        // Ambil semua index
        $allIndices = $this->es->cat()->indices(['format' => 'json']);
        $indices = [];

        foreach ($allIndices as $index) {
            if ($logType) {
                if (strpos($index['index'], $logType) !== false) {
                    $indices[] = $index['index'];
                }
            } else {
                $indices[] = $index['index'];
            }
        }

        if (empty($indices)) {
            return $response->json([
                'status' => 'error',
                'message' => 'No indices found for this log type',
            ]);
        }

        // Query dasar
        $must = [['match_all' => new \stdClass()]];
        if ($level) {
            $must[] = ['match' => ['level_name' => strtoupper($level)]];
        }

        $params = [
            'index' => $indices,
            'body' => [
                'size' => $size,
                'query' => [
                    'bool' => ['must' => $must]
                ],
                'sort' => [
                    '@timestamp' => ['order' => 'desc']
                ]
            ]
        ];

        try {
            $result = $this->es->search($params);

            // Ambil aturan dari DB (message_rules)
            $messageRules = \App\Model\MessageRule::all();
            $messageLimits = [];
            foreach ($messageRules as $r) {
                $messageLimits[$r->app_name][$r->message_key] = (int) $r->max_duration;
            }

            $violations = [];
            foreach ($result['hits']['hits'] as $hit) {
                $src = $hit['_source'];

                // adaptasi field yang mungkin berbeda antar log
                $app     = $src['app_name'] ?? ($src['app'] ?? null);
                $msgKey  = $src['message_key']
                    ?? ($src['context']['message_key'] ?? null)
                    ?? ($src['message'] ?? null);
                $duration = $src['extra']['duration_ms'] ?? ($src['duration_ms'] ?? null);

                if (!$app || !$msgKey || $duration === null) {
                    continue;
                }

                if (isset($messageLimits[$app][$msgKey]) && $duration > $messageLimits[$app][$msgKey]) {
                    $violations[] = [
                        'timestamp'      => $src['@timestamp'] ?? $src['timestamp'] ?? null,
                        'app'            => $app,
                        'message_key'    => $msgKey,
                        'level'          => $src['level_name'] ?? $src['level'] ?? null,
                        'message'        => $src['message'] ?? null,
                        'duration_ms'    => $duration,
                        'limit'          => $messageLimits[$app][$msgKey],
                        'correlation_id' => $src['extra']['correlation_id'] ?? ($src['correlation_id'] ?? null),
                    ];
                }
            }

            usort($violations, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);

            return $response->json([
                'status' => 'success',
                'total'  => count($violations),
                'data'   => $violations,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
