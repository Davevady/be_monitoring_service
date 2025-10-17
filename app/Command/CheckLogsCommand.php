<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\ElasticsearchService;

#[Command]
class CheckLogsCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('check:logs');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Check sample logs from Elasticsearch');
    }

    public function handle()
    {
        $this->info('Checking sample logs from Elasticsearch...');
        
        $esService = $this->container->get(ElasticsearchService::class);
        
        // Get list of indices
        $indices = $esService->getIndices();
        $this->info('Found ' . count($indices) . ' indices: ' . implode(', ', $indices));
        
        // Check first few indices for sample logs
        foreach (array_slice($indices, 0, 3) as $indexName) {
            $this->info("\n📊 Checking index: {$indexName}");
            
            try {
                $params = [
                    'index' => $indexName,
                    'body' => [
                        'size' => 5,
                        'query' => [
                            'match_all' => new \stdClass()
                        ],
                        'sort' => [
                            ['@timestamp' => ['order' => 'desc']]
                        ]
                    ]
                ];
                
                $response = $esService->getClient()->search($params);
                
                if (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
                    $this->info("  Found " . $response['hits']['total']['value'] . " total logs");
                    
                    foreach ($response['hits']['hits'] as $hit) {
                        $source = $hit['_source'];
                        $appName = $source['app_name'] ?? 'unknown';
                        $message = $source['message'] ?? 'no message';
                        $duration = $source['duration_ms'] ?? 'no duration';
                        $timestamp = $source['@timestamp'] ?? 'no timestamp';
                        
                        $this->line("  ├─ App: {$appName}, Duration: {$duration}ms, Message: " . substr($message, 0, 50) . "...");
                    }
                } else {
                    $this->warn("  No logs found in this index");
                }
                
            } catch (\Exception $e) {
                $this->error("  Error checking index {$indexName}: " . $e->getMessage());
            }
        }
    }
}
