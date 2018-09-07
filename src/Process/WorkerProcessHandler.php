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
        $this->logger = $container['worker.logger'];
        $this->processManager = $container['worker.process_manager'];
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
            return false;
        }

        $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} job#{$job['id']} reserved.", $job);

        return $job;
    }

    private function executeJob($jobId, $jobBody)
    {
        $startTime = $this->getMicroTime();

        $data = json_decode($jobBody, true);
        try {
            $this->worker->beforeExecute($jobId, $data);
            $result = $this->worker->execute($jobId, $data);
        } catch (\Exception $e) {
            $this->beanstalk->bury($jobId, 1024);

            $message = "worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} failed and buried, error: {$e->getMessage()}";
            $this->logger->error($message, $data);
            $this->worker->onError($jobId, $data, $message);

            return;
        }

        $code = $result['code'] ?? -1;
        $pri = $result['pri'] ?? 1024;
        $delay = $result['delay'] ?? 3;
        switch ($code) {
            case WorkerInterface::FINISH:
                $this->beanstalk->delete($jobId);

                $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} finished");
                $this->worker->onFinish($jobId, $data, $this->getMicroTime() - $startTime);

                break;
            case WorkerInterface::RETRY:
                $this->beanstalk->release($jobId, $pri, $delay);

                $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} once and retrying");
                $this->worker->onRetry($jobId, $data, $pri, $delay, $this->getMicroTime() - $startTime);

                break;
            case WorkerInterface::BURY:
                $this->beanstalk->bury($jobId, $pri);

                $this->logger->info("worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} once and buried");
                $this->worker->onBury($jobId, $data, $pri, $this->getMicroTime() - $startTime);

                break;
            default:
                $this->beanstalk->bury($jobId, $pri);

                $message = "worker#{$this->process->pid} tube#{$this->tubeName} execute job#{$jobId} result#{$result} is invalid, job buried";
                $this->logger->error($message);
                $this->worker->onError($jobId, $data, $message);
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

        return new Client($options);
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

    private function getMicroTime()
    {
        return (int) round(microtime(true) * 1000);
    }
}
