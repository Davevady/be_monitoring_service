<?php

declare(strict_types=1);

namespace App\Factory;

use Elasticsearch\{Client, ClientBuilder};
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ElasticsearchClientFactory
{
    public function __invoke(ContainerInterface $container): Client
    {
        $config = $container->get(ConfigInterface::class);
        $esConfig = $config->get('elasticsearch.default', []);

        $hosts = [$esConfig['host']];

        $builder = ClientBuilder::create()->setHosts($hosts);

        // Set auth jika ada
        if (!empty($esConfig['username']) && !empty($esConfig['password'])) {
            $builder->setBasicAuthentication($esConfig['username'], $esConfig['password']);
        }

        return $builder->build();
    }
}
