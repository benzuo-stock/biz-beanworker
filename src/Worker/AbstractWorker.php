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
        $this->logger = $container['logger'];
    }

    abstract public function execute($jobId, array $data);
}
