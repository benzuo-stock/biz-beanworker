<?php

namespace BeanWorker\Process;

use Pimple\Container;
use Psr\Log\LoggerInterface;

/**
 * The class to handle job after worker process created.
 */
class MasterProcessHandler
{
    /**
     * @var string
     */
    private $projectId;

    /**
     * @var int
     */
    private $pid;

    /**
     * @var \swoole_process
     */
    private $process;

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

    public function __construct($process, Container $container)
    {
        $this->pid = $process->pid;
        $this->process = $process;
        $this->container = $container;
        $this->projectId = $container['worker.project_id'];
        $this->logger = $container['logger'];
        $this->processManager = $container['process_manager'];
    }

    public function start()
    {
        $pid = posix_getpid();
        $this->processManager->savePid($pid);

        $this->logger->info("master#{$pid} started.");

        $tubes = array_keys($this->container['worker.tubes']);
        foreach ($tubes as $tube) {
            $this->createTubeWorkerProcesses($tube);
        }

        $this->registerSignal();

        if (false !== strpos(php_uname(), 'Darwin')) {
            while (true) {
                sleep(3600);
            }
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

        $workerProcess = ProcessManager::createProcess(function ($process) use ($tube, $workerClass) {
            ProcessManager::setProcessName("{$this->projectId} beanworker: worker tube#{$tube}");
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

    private function registerSignal()
    {
        // listen to child process terminated signal
        ProcessManager::signal(SIGCHLD, function ($signo) {
            while ($result = ProcessManager::wait(false)) {
                $this->logger->info("worker#{$result['pid']} terminated({$signo}).", $result);

                $tube = $this->workerProcesses[$result['pid']]['tube'];
                unset($this->workerProcesses[$result['pid']]);

                if (0 == count($this->workerProcesses) && $this->processManager->isRunning()) {
                    $this->logger->warning("tube#{$tube} workers are all terminated, workers recreating...");
                    $this->createTubeWorkerProcesses($tube);
                }
            }
        });

        // maybe never work here when master process be killed
        $onTerminated = function ($signo) {
            $this->logger->info("master terminated({$signo}), workers terminating");

            $PIDs = self::getWorkerPIDs();

            foreach ($PIDs as $pid) {
                ProcessManager::kill($pid, SIGKILL);
                $this->logger->info("worker#{$pid} terminated");
            }

            $this->processManager->clear();
        };

        ProcessManager::signal(SIGINT, $onTerminated);
        ProcessManager::signal(SIGTERM, $onTerminated);
        ProcessManager::signal(SIGKILL, $onTerminated);
    }

    public static function getWorkerPIDs($projectId, $masterPid = -1)
    {
        $cmd = 'ps -ef |grep \'%s\' |awk \'$0 !~ /grep/ {print $2}\'';

        $PIDs = [];
        exec(sprintf($cmd, "{$projectId} beanworker: worker"), $PIDs);

        // if process rename failed, default name is `php bin/beanworker start`
        if (empty($PIDs)) {
            $PIDs = [];
            exec(sprintf($cmd, 'bin/beanworker start'), $PIDs);
            foreach ($PIDs as $key => $pid) {
                if ($pid <= $masterPid) {
                    unset($PIDs[$key]);
                }
            }
        }

        if (empty($PIDs)) {
            return [];
        }

        return $PIDs;
    }
}
