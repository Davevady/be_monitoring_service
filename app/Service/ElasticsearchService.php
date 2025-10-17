<?php

namespace App\Service;

use Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts(['http://elasticsearch7:9200']) // nama container Elasticsearch di network Docker
            ->build();
    }

    public function search($index, array $params = [])
    {
        $params['index'] = $index;
        return $this->client->search($params);
    }

    public function getDocument($index, $id)
    {
        return $this->client->get([
            'index' => $index,
            'id' => $id
        ]);
    }
}
