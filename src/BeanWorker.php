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
        echo "BeanWorker master starting...\n";
        $this->logger->info('master starting...');

        if ($this->masterPidManager->isRunning()) {
            echo "ERROR: BeanWorker master#{$this->masterPidManager->get()} is already running.\n";

            return;
        }

        if ($this->container['worker.daemonize']) {
            swoole_process::daemon();
        }

        $this->setProcessName('BeanWorker: master');

        $tubes = array_keys($this->container['worker.tubes']);
        foreach ($tubes as $tube) {
            $this->createTubeWorkerProcesses($tube);
        }

        $pid = posix_getpid();
        $this->masterPidManager->save($pid);
        echo "BeanWorker master#{$pid} started...\n";
        $this->logger->info("master#{$pid} started.");
        $this->registerSignal();

        return $pid;
    }

    public function stop()
    {
        if (!$this->masterPidManager->isRunning()) {
            echo "ERROR: BeanWorker master is not running.\n";
            return;
        }

        $pid = $this->masterPidManager->get();

        echo "BeanWorker master#{$pid} stopping...\n";
        $this->logger->info("master#{$pid} stopping...");

        echo "BeanWorker master#{$pid} stopped.\n";
        $this->logger->info("master#{$pid} stopped.");

        $this->masterPidManager->clear();

        swoole_process::kill($pid, SIGTERM);
    }

    public function status()
    {
        if ($this->masterPidManager->isRunning()) {
            echo "BeanWorker master status is running.\n";
        } else {
            echo "BeanWorker master status is not running.\n";
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
            $this->setProcessName("BeanWorker: worker tube#{$tube}");
            $workerProcessHandler = new WorkerProcessHandler($process, $this->container, $tube, $workerClass);
            $workerProcessHandler->start();
        });

        $workerProcess->start();
        // swoole_event_add($workerProcess->pipe, function ($pipe) use ($tube, $workerProcess) {
        //     $resp = $workerProcess->read();
        //     echo "BeanWorker worker#{$workerProcess->pid} tube#{$tube} received: {$resp} {$pipe} \n";
        //     $this->logger->info("worker#{$workerProcess->pid} tube#{$tube} received: {$resp} {$pipe}");
        // });

        // 子进程创建成功后$process->pid属性为子进程的PID
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
            $this->logger->warning("BeanWorker: current process name set `{$name}` failed. {$e->getMessage()}");
        }
    }

    private function registerSignal()
    {
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

        $onTerminated = function ($signo) {
            echo "master terminated({$signo}).";
            $this->logger->info("master terminated({$signo}).");
            $this->masterPidManager->clear();
        };

        swoole_process::signal(SIGINT, $onTerminated);
        swoole_process::signal(SIGTERM, $onTerminated);
        swoole_process::signal(SIGKILL, $onTerminated);
    }
}
