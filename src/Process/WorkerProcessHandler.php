<?php

namespace BeanWorker\Process;

use BeanWorker\Worker\WorkerInterface;
use Pimple\Container;
use Beanstalk\Client;
use Psr\Log\LoggerInterface;

/**
 * The class to handle job after worker process created.
 */
class WorkerProcessHandler
{
    private $pid;

    private $process;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var ProcessManager
     */
    private $processManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $tubeName;

    /**
     * @var WorkerInterface
     */
    private $worker;

    /**
     * @var Client
     */
    private $beanstalk;

    public function __construct($process, Container $container, $tubeName, $workerClass)
    {
        $this->pid = $process->pid;
        $this->process = $process;
        $this->container = $container;
        $this->logger = $container['logger'];
        $this->processManager = $container['process_manager'];
        $this->tubeName = $tubeName;
        $this->worker = $this->initWorker($workerClass);
        $this->beanstalk = $this->initBeanstalk();
    }

    public function start()
    {
        if (!$this->beanstalk->connect()) {
            $this->logger->error("worker#{$this->process->pid} terminated, error: beanstalk connect failed on start");
            $this->process->exit(0);
            return;
        }

        // If the tube to watch doesn't exist, it will be created.
        if (!$this->beanstalk->watch($this->tubeName)) {
            $this->logger->error("worker#{$this->process->pid} terminated, error: beanstalk watch tube#{$this->tubeName} failed on start");
            $this->process->exit(0);
            return;
        }

        $this->logger->info("worker#{$this->process->pid} started, watching tube#{$this->tubeName}...");

        while (true) {
            if (!$this->processManager->isRunning()) {
                $this->logger->error("worker#{$this->process->pid} terminated, error: master is not running");
                $this->process->exit(0);
            }

            if (false === $job = $this->reserveJob()) {
                continue;
            }

            $this->executeJob($job['id'], $job['body']);
        }
    }

    private function reserveJob()
    {
        $job = $this->beanstalk->reserve($this->container['worker.reserve_timeout']);
        if (!$job) {
            $this->logger->error("worker#{$this->process->pid} tube#{$this->tubeName} reserve job failed");

            return false;
        }

        $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} job#{$job['id']} reserved.", $job);

        return $job;
    }

    private function executeJob($jobId, $data)
    {
        try {
            $result = $this->worker->execute($jobId, json_decode($data, true));
        } catch (\Exception $e) {
            $this->logger->error("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} failed, error: {$e->getMessage()}", $data);

            return;
        }

        $code = $result['code'] ?? 0;
        $pri = $result['pri'] ?? 1024;
        $delay = $result['delay'] ?? 3;
        switch ($code) {
            case WorkerInterface::FINISH:
                if ($this->beanstalk->delete($jobId)) {
                    $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} finished");
                } else {
                    $this->logger->warning("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} finished, but delete job failed");
                }
                break;
            case WorkerInterface::RETRY:
                if ($this->beanstalk->release($jobId, $pri, $delay)) {
                    $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} once, retry success");
                } else {
                    $this->logger->warning("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} once, retry failed");
                }
                break;
            case WorkerInterface::BURY:
                if ($this->beanstalk->bury($jobId, $pri)) {
                    $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} once, bury success");
                } else {
                    $this->logger->warning("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} once, bury failed");
                }
                break;
            default:
                $this->logger->warning("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} result is invalid", $result);
                break;
        }
    }

    /**
     * @return Client
     */
    private function initBeanstalk()
    {
        $options = array_merge([
            'host' => '127.0.0.1',
            'port' => 11300,
            'timeout' => 3,
            'persistent' => true,
            'logger' => $this->logger,
        ], $this->container['options']);

        $client = new Client($options);

        return $client;
    }

    /**
     * @param $workerClass
     *
     * @return WorkerInterface
     */
    private function initWorker($workerClass)
    {
        return new $workerClass($this->container);
    }
}
