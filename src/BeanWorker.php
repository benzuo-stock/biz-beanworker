<?php

namespace BeanWorker;

use Pimple\Container;
use Psr\Log\LoggerInterface;
use BeanWorker\Process\ProcessManager;
use BeanWorker\Process\MasterProcessHandler;

class BeanWorker
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProcessManager
     */
    public $processManager;

    /**
     * @var array
     */
    public $workerProcesses = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->logger = $container['logger'];
        $this->processManager = $container['process_manager'];
    }

    public function cmd($func)
    {
        $this->{$func}();
    }

    public function start()
    {
        echo "beanworker starting...\n";
        $this->logger->info("beanworker starting...");

        if ($this->processManager->isRunning()) {
            echo "ERROR: beanworker#{$this->processManager->getPid()} is already running.\n";

            return;
        }

        $masterProcess = ProcessManager::createProcess(function ($process) {
            ProcessManager::setProcessName("{$this->container['worker.project_id']} beanworker: master");
            $masterProcessHandler = new MasterProcessHandler($process, $this->container);
            $masterProcessHandler->start();
        });

        $masterPid = $masterProcess->start();

        $workerPIDs = MasterProcessHandler::getWorkerPIDs($masterPid);
        $workerCount = \count($workerPIDs);
        $workerPIDs = implode(',', $workerPIDs);
        echo "beanworker started, master#{$masterProcess->pid}, workers({$workerCount})#{$workerPIDs} \n";
    }

    public function stop()
    {
        echo "beanworker stopping...\n";
        $this->logger->info("beanworker stopping...");

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

        $workerPIDs = MasterProcessHandler::getWorkerPIDs($masterPid);
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

        $workerPIDs = MasterProcessHandler::getWorkerPIDs($masterPid);
        if (!empty($workerPIDs)) {
            $workerCount = \count($workerPIDs);
            $workerPIDs = implode(',', $workerPIDs);
            echo "workers({$workerCount})#{$workerPIDs} are running.\n";
        } else {
            echo "workers are not running.\n";
        }
    }
}
