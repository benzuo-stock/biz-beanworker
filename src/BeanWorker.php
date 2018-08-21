<?php

namespace BeanWorker;

use Pimple\Container;
use Psr\Log\LoggerInterface;
use BeanWorker\Process\ProcessManager;
use BeanWorker\Process\MasterProcessHandler;
use BeanWorker\Process\MetricProcessHandler;

class BeanWorker
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var string
     */
    private $projectId;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProcessManager
     */
    public $processManager;

    /**
     * @var ProcessManager
     */
    public $metricProcessManager;

    /**
     * @var array
     */
    public $workerProcesses = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->projectId = $container['worker.project_id'];
        $this->logger = $container['worker.logger'];
        $this->processManager = $container['worker.process_manager'];
        $this->metricProcessManager = $container['metric.process_manager'];
    }

    public function cmd($func)
    {
        $this->{$func}();
    }

    public function start()
    {
        echo "beanworker starting...\n";
        $this->logger->info('beanworker starting...');

        if ($this->processManager->isRunning()) {
            echo "ERROR: beanworker#{$this->processManager->getPid()} is already running.\n";

            return;
        }

        $masterProcess = ProcessManager::createProcess(function ($process) {
            ProcessManager::setProcessName("{$this->projectId} beanworker: master");
            $masterProcessHandler = new MasterProcessHandler($process, $this->container);
            $masterProcessHandler->start();
        });

        $masterPid = $masterProcess->start();

        $workerPIDs = MasterProcessHandler::getWorkerPIDs($this->projectId, $masterPid);
        $workerCount = \count($workerPIDs);
        $workerPIDs = implode(',', $workerPIDs);
        echo "beanworker started, master#{$masterProcess->pid}, workers({$workerCount})#{$workerPIDs} \n";

        if ($this->container['metric.enabled']) {
            $metricProcess = ProcessManager::createProcess(function ($process) {
                ProcessManager::setProcessName("{$this->projectId} beanworker: metric");
                $metricProcessHandler = new MetricProcessHandler($process, $this->container);
                $metricProcessHandler->start();
            });
            $metricProcess->start();
        }
    }

    public function stop()
    {
        echo "beanworker stopping...\n";
        $this->logger->info('beanworker stopping...');

        $masterPid = $this->processManager->getPid();
        if ($this->processManager->isRunning()) {
            ProcessManager::kill($masterPid, SIGKILL);

            $this->processManager->clearPid();

            echo "master#{$masterPid} stopped.\n";
            $this->logger->info("master#{$masterPid} stopped");
        } else {
            echo "WARNING: master is not running.\n";
            $this->logger->warning("WARNING: master is not running");
        }

        $workerPIDs = MasterProcessHandler::getWorkerPIDs($this->projectId, $masterPid);
        if (!empty($workerPIDs)) {

            $this->logger->info("workers stopping");

            foreach ($workerPIDs as $workerPid) {
                ProcessManager::kill($workerPid, SIGKILL);
                echo "worker#{$workerPid} stopped.\n";
                $this->logger->info("worker#{$workerPid} stopped");
            }

        } else {
            echo "WARNING: workers are not running.\n";
            $this->logger->warning("WARNING: workers are not running");
        }

        if ($this->container['metric.enabled']) {
            $metricPid = $this->metricProcessManager->getPid();
            if ($this->metricProcessManager->isRunning()) {
                ProcessManager::kill($metricPid, SIGKILL);
                $this->metricProcessManager->clearPid();

                exec('kill -9 $(ps -ef|grep \'test_project beanworker: metric\'|awk \'$0 !~ /grep/ {print $2}\')');

                echo "metric#{$metricPid} stopped.\n";
                $this->logger->info("metric#{$metricPid} stopped");
            } else {
                echo "WARNING: metric is not running.\n";
                $this->logger->warning('WARNING: metric is not running');
            }
        }
    }

    public function restart()
    {
        $this->stop();
        sleep(1);
        $this->start();
    }

    public function status()
    {
        $masterPid = $this->processManager->getPid();
        if ($this->processManager->isRunning()) {
            echo "master#{$masterPid} is running.\n";
        } else {
            echo "master is not running.\n";
        }

        $workerPIDs = MasterProcessHandler::getWorkerPIDs($this->projectId, $masterPid);
        if (!empty($workerPIDs)) {
            $workerCount = \count($workerPIDs);
            $workerPIDs = implode(',', $workerPIDs);
            echo "workers({$workerCount})#{$workerPIDs} are running.\n";
        } else {
            echo "workers are not running.\n";
        }

        $metricPid = $this->metricProcessManager->getPid();
        if ($this->metricProcessManager->isRunning()) {
            echo "metric#{$metricPid} is running.\n";
        } else {
            echo "metric is not running.\n";
        }
    }
}
