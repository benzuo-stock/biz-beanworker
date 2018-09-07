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

    public function onFinish($jobId, array $data, $executeMicroTime)
    {

    }

    public function onRetry($jobId, array $data, $pri, $delay, $executeMicroTime)
    {

    }

    public function onBury($jobId, array $data, $pri, $executeMicroTime)
    {

    }

    public function onError($jobId, array $data, $message)
    {

    }

    protected function finish()
    {
        return ['code' => self::FINISH];
    }

    protected function retry($pri = 1024, $delay = 3)
    {
        return ['code' => self::RETRY, 'pri' => $pri, 'delay' => $delay];
    }

    protected function bury($pri = 1024)
    {
        return ['code' => self::BURY, 'pri' => $pri];
    }
}
