<?php

namespace BeanWorker;

use Pimple\Container;
use Psr\Log\LoggerInterface;
use BeanWorker\Process\PidManager;
use BeanWorker\Process\WorkerProcessHandler;
use \swoole_process;

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
     * @var PidManager
     */
    public $masterPidManager;

    /**
     * @var array
     */
    public $workerProcesses = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->logger = $container['logger'];
        $this->masterPidManager = $container['master_pid_manager'];
    }

    public function cmd($func)
    {
        $this->{$func}();
    }

    public function start()
    {
        echo "beanworker starting...\n";
        $this->logger->info('beanworker starting...');

        if ($this->masterPidManager->isRunning()) {
            echo "ERROR: beanworker#{$this->masterPidManager->get()} is already running.\n";

            return;
        }

        if ($this->container['worker.daemonize']) {
            swoole_process::daemon();
        }

        $this->setProcessName('beanworker: master');

        $pid = posix_getpid();
        $this->masterPidManager->save($pid);

        echo "master#{$pid} started...\n";
        $this->logger->info("master#{$pid} started.");

        $tubes = array_keys($this->container['worker.tubes']);
        foreach ($tubes as $tube) {
            $this->createTubeWorkerProcesses($tube);
        }

        $workerPIDs = array_keys($this->workerProcesses);
        $workerPIDs = implode(' ', $workerPIDs);
        echo "workers#{$workerPIDs} started...\n";
        $this->logger->info("workers#{$workerPIDs} started.");

        $this->registerSignal();

        return $pid;
    }

    public function stop()
    {
        echo "beanworker stopping...\n";
        $this->logger->info("beanworker stopping...");

        if ($this->masterPidManager->isRunning()) {
            $pid = $this->masterPidManager->get();

            swoole_process::kill($pid, SIGKILL);

            $this->masterPidManager->clear();

            echo "master#{$pid} stopped.\n";
            $this->logger->info("master#{$pid} stopped");
        } else {
            echo "WARNING: master is not running.\n";
            $this->logger->warning("WARNING: master is not running");
        }

        $workerPIDs = $this->terminateWorkerProcesses();

        if (!empty($workerPIDs)) {
            $workerPIDs = implode(' ', $workerPIDs);
            echo "workers {$workerPIDs} stopped.\n";
            $this->logger->info("workers {$workerPIDs} stopped");
        } else {
            echo "WARNING: workers are not running.\n";
            $this->logger->warning("WARNING: workers are not running");
        }
    }

    public function status()
    {
        if ($this->masterPidManager->isRunning()) {
            echo "master is running.\n";
        } else {
            echo "master is not running.\n";
        }

        $workerPIDs = $this->getWorkerProcesses();
        if (!empty($workerPIDs)) {
            $workerPIDs = implode(' ', $workerPIDs);
            echo "workers#{$workerPIDs} are running.\n";
        } else {
            echo "workers are not running.\n";
        }
    }

    private function createTubeWorkerProcesses($tube)
    {
        $tubeConfig = $this->container['worker.tubes'][$tube];

        for ($i=0; $i<$tubeConfig['worker_num']; $i++) {
            $this->createWorkerProcess($tube);
        }
    }

    private function createWorkerProcess($tube)
    {
        $workerClass = $this->container['worker.tubes'][$tube]['class'];

        $workerProcess = new swoole_process(function ($process) use ($tube, $workerClass) {
            $this->setProcessName("beanworker: worker tube#{$tube}");
            $workerProcessHandler = new WorkerProcessHandler($process, $this->container, $tube, $workerClass);
            $workerProcessHandler->start();
        });

        $workerProcess->start();
        // swoole_event_add($workerProcess->pipe, function ($pipe) use ($tube, $workerProcess) {
        //     $resp = $workerProcess->read();
        //     echo "worker#{$workerProcess->pid} tube#{$tube} received: {$resp} {$pipe} \n";
        //     $this->logger->info("worker#{$workerProcess->pid} tube#{$tube} received: {$resp} {$pipe}");
        // });

        // $workerProcess->pid property will be available after process start
        $this->workerProcesses[$workerProcess->pid] = [
            'tube' => $tube,
            'process' => $workerProcess,
        ];

        return $workerProcess;
    }

    private function setProcessName($name)
    {
        try {
            if (function_exists('cli_set_process_title')) {
                @cli_set_process_title($name);
            } else {
                swoole_set_process_name($name);
            }
        } catch (\Exception $e) {
            $this->logger->warning("process name set `{$name}` failed. {$e->getMessage()}");
        }
    }

    private function registerSignal()
    {
        // listen to child process terminated signal
        swoole_process::signal(SIGCHLD, function ($signo) {
            while ($result = swoole_process::wait(false)) {
                $this->logger->info("worker#{$result['pid']} terminated({$signo}).", $result);

                $tube = $this->workerProcesses[$result['pid']]['tube'];
                unset($this->workerProcesses[$result['pid']]);

                if (0 == count($this->workerProcesses) && $this->masterPidManager->isRunning()) {
                    $this->logger->warning("tube#{$tube} workers are all terminated, workers recreating...");
                    $this->createTubeWorkerProcesses($tube);
                }
            }
        });

        // maybe never work here when master process be killed
        // $onTerminated = function ($signo) {
        //     $this->logger->info("master terminated({$signo})");
        //     $this->terminateWorkerProcesses();
        //
        //     $this->masterPidManager->clear();
        // };
        //
        // swoole_process::signal(SIGINT, $onTerminated);
        // swoole_process::signal(SIGTERM, $onTerminated);
        // swoole_process::signal(SIGKILL, $onTerminated);
    }

    private function terminateWorkerProcesses()
    {
        $this->logger->info("workers terminating");

        $PIDs = $this->getWorkerProcesses();

        foreach ($PIDs as $pid) {
            swoole_process::kill($pid, SIGKILL);
            $this->logger->info("worker#{$pid} terminated");
        }

        return $PIDs;
    }

    private function getWorkerProcesses()
    {
        $cmd = 'ps -ef |grep \'%s\' |awk \'$0 !~ /grep/ {print $2}\'';
        $PIDs = [];
        exec(sprintf($cmd, 'beanworker: worker'), $PIDs);

        // if process rename failed, default name is `php bin/beanworker start`
        if (empty($PIDs)) {
            exec(sprintf($cmd, 'bin/beanworker start'), $PIDs);
            $PIDs = array_values(array_diff($PIDs, [$this->masterPidManager->get()]));
        }

        return $PIDs;
    }
}
