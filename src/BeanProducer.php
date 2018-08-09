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

    public function connect()
    {
        return $this->client->connect();
    }

    public function disconnect()
    {
        return $this->client->disconnect();
    }

    public function stats()
    {
        return $this->client->stats();
    }

    public function listTubes()
    {
        return $this->client->listTubes();
    }

    public function statsTube($tube)
    {
        return $this->client->statsTube($tube);
    }

    public function useTube($tube)
    {
        return $this->client->useTube($tube);
    }

    public function put(array $data, $ttr = 60, $delay = 0, $pri = 1024)
    {
        return $this->client->put($pri, $delay, $ttr, json_encode($data));
    }

    public function putInTube($tube, array $data, $ttr = 60, $delay = 0, $pri = 1024)
    {
        $this->client->useTube($tube);
        return $this->client->put($pri, $delay, $ttr, json_encode($data));
    }

    public function statsJob($jobId)
    {
        return $this->client->statsJob($jobId);
    }

    public function kickJob($jobId)
    {
        return $this->client->kickJob($jobId);
    }
}
