<?php

namespace BeanWorker;

use Beanstalk\Client;
use Psr\Log\LoggerInterface;

class BeanProducer
{
    protected $client;

    public function __construct(array $options, LoggerInterface $logger = null)
    {
        $options = array_merge([
            'host' => '127.0.0.1',
            'port' => 11300,
            'timeout' => 3,
            'persistent' => true,
            'logger' => $logger,
        ], $options);

        $this->client = new Client($options);
    }

    public function stats()
    {
        $this->client->stats();

        return $this;
    }

    public function listTubes()
    {
        $this->client->listTubes();

        return $this;
    }

    public function statsTube($tube)
    {
        $this->client->statsTube($tube);

        return $this;
    }

    public function useTube($tube)
    {
        $this->client->useTube($tube);

        return $this;
    }

    public function put($data, $ttr = 60, $delay = 0, $pri = 1024)
    {
        $this->client->put($pri, $delay, $ttr, $data);

        return $this;
    }

    public function statsJob($jobId)
    {
        $this->client->statsJob($jobId);

        return $this;
    }

    public function kickJob($jobId)
    {
        $this->client->kickJob($jobId);

        return $this;
    }
}
