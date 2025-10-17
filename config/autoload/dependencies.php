<?php

declare(strict_types=1);

use Elasticsearch\Client;
use App\Factory\ElasticsearchClientFactory;
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    Elasticsearch\Client::class => App\Factory\ElasticsearchClientFactory::class,
    Client::class => ElasticsearchClientFactory::class,
];
