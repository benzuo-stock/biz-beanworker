<?php

namespace Biz\BeanWorker;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class BeanWorker
{
    /**
     * @var ContainerInterface
     */
    protected $biz;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $pidManager;

    public function __construct(ContainerInterface $biz)
    {
        $options = $biz['queue.options'];

        $this->biz = $biz;
        $this->pidManager = new PidManager($options['worker_pid']);
    }

    public function start()
    {
        if ($this->pidManager->isRunning()) {
            echo "ERROR: BeanWorker pid#{$this->pidManager->get()} is already running.\n";
            return;
        }

        $master = new Master($this->container);
        $master->run();
    }

    public function stop()
    {
        $pid = $this->pidManager->get();
        exec("kill -9 $pid");
        unlink($this->masterPidFilePath);
        $this->logger->info('Stoped', ['pid' => $pid]);
    }
}
