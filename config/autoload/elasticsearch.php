<?php

declare(strict_types=1);

return [
    'default' => [
        'host' => getenv('ELASTICSEARCH_HOST') ?: 'elasticsearch:9200',
        'username' => getenv('ELASTICSEARCH_USERNAME') ?: '',
        'password' => getenv('ELASTICSEARCH_PASSWORD') ?: '',
    ],
];
