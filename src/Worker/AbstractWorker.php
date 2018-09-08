<?php

namespace BeanWorker\Worker;

use Pimple\Container;
use Psr\Log\LoggerInterface;

abstract class AbstractWorker implements WorkerInterface
{
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->logger = $container['worker.logger'];
    }

    abstract public function execute($jobId, array $data);

    public function beforeExecute($jobId, array $data)
    {

    }

    public function onFinish($jobId, array $data, array $resp, $executeMicroTime)
    {

    }

    public function onRetry($jobId, array $data, array $resp, $executeMicroTime)
    {

    }

    public function onBury($jobId, array $data, array $resp, $executeMicroTime)
    {

    }

    public function onError($jobId, array $data, array $resp, $executeMicroTime)
    {

    }

    protected function finish(array $resp = [])
    {
        return ['code' => self::FINISH, 'resp' => $resp];
    }

    protected function retry(array $resp = [], $pri = 1024, $delay = 3)
    {
        return ['code' => self::RETRY, 'pri' => $pri, 'delay' => $delay, 'resp' => $resp];
    }

    protected function bury(array $resp = [], $pri = 1024)
    {
        return ['code' => self::BURY, 'pri' => $pri, 'resp' => $resp];
    }
}
