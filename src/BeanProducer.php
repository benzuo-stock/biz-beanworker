<?php

namespace BeanWorker;

use Beanstalk\Client;
use Psr\Log\LoggerInterface;

class BeanProducer
{
    protected $client;

    protected $tube;

    public function __construct(array $options, $tube = 'default', LoggerInterface $logger = null)
    {
        $options = array_merge([
            'host' => '127.0.0.1',
            'port' => 11300,
            'timeout' => 3,
            'persistent' => true,
            'logger' => $logger,
        ], $options);

        $this->client = new Client($options);
        $this->tube = $tube;
    }

    public function put(array $data, $ttr = 60, $delay = 0, $pri = 1024)
    {
        return $this->proxy('put', [$pri, $delay, $ttr, json_encode($data)]);
    }

    public function statsJob($jobId)
    {
        return $this->proxy('statsJob', [$jobId]);
    }

    public function kickJob($jobId)
    {
        return $this->proxy('kickJob', [$jobId]);
    }

    public function stats()
    {
        return $this->proxy('stats');
    }

    public function listTubes()
    {
        return $this->proxy('listTubes');
    }

    public function statsTube($tube = null)
    {
        if (empty($tube)) {
            $tube = $this->tube;
        }

        return $this->proxy('statsTube', [$tube]);
    }

    protected function proxy($cmd, array $args = [])
    {
        if (!$this->client->connected) {
            $this->client->connect();
            $this->client->useTube($this->tube);
        }

        return \call_user_func_array([&$this->client, $cmd], $args);
    }
}
