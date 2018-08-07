<?php

namespace Biz\BeanWorker;

use Pheanstalk\Pheanstalk;
use Biz\BeanWorker\Worker\JobInterface;

class BeanProducer
{
    protected $client;

    public function __construct(array $config)
    {
        $config = array_merge([
            'host' => '127.0.0.1',
            'port' => 11300,
            'timeout' => 1,
            'persistent' => true,
        ], $config);

        $this->client = new Pheanstalk($config['host'], $config['port'], $config['timeout'], $config['persistent']);
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

    public function ignore($tube)
    {
        $this->client->ignore($tube);

        return $this;
    }

    public function put($data)
    {
        $this->client->put($data);

        return $this;
    }

    public function statsJob(JobInterface $job)
    {
        $this->client->statsJob($job);

        return $this;
    }
}
