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
            ProcessManager::setProcessName("beanworker: master");
            $masterProcessHandler = new MasterProcessHandler($process, $this->container);
            $masterProcessHandler->start();
        });

        $masterProcess->start();

        $workerPIDs = MasterProcessHandler::getWorkerPIDs();
        $workerCount = count($workerPIDs);
        $workerPIDs = implode(',', $workerPIDs);
        echo "beanworker started, master#{$masterProcess->pid}, {$workerCount} workers#{$workerPIDs} \n";
    }

    public function stop()
    {
        echo "beanworker stopping...\n";
        $this->logger->info("beanworker stopping...");

        if ($this->processManager->isRunning()) {
            $pid = $this->processManager->getPid();

            ProcessManager::kill($pid, SIGKILL);

            $this->processManager->clearPid();

            echo "master#{$pid} stopped.\n";
            $this->logger->info("master#{$pid} stopped");
        } else {
            echo "WARNING: master is not running.\n";
            $this->logger->warning("WARNING: master is not running");
        }

        $workerPIDs = MasterProcessHandler::getWorkerPIDs();

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
        if ($this->processManager->isRunning()) {
            echo "master#{$this->processManager->getPid()} is running.\n";
        } else {
            echo "master is not running.\n";
        }

        $workerPIDs = MasterProcessHandler::getWorkerPIDs();
        if (!empty($workerPIDs)) {
            $workerPIDs = implode(',', $workerPIDs);
            echo "workers#{$workerPIDs} are running.\n";
        } else {
            echo "workers are not running.\n";
        }
    }
}
