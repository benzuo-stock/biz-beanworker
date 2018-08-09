<?php

namespace Tests;

use BeanWorker\BeanWorkerBootstrap;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use BeanWorker\BeanWorker;
use \swoole_process;

class BeanWorkerTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    private $beanWorker;

    public function __construct()
    {
        parent::__construct(null, [], '');
        $biz = require_once __DIR__.'/TestProject/app/biz.php';
        $bootstrap = new BeanWorkerBootstrap($biz);
        $container = $bootstrap->boot();

        $this->container = $container;
        $this->beanWorker = new BeanWorker($this->container);
    }

    public function testAll()
    {
        $beanWorker = $this->beanWorker;

        // $this->assertEquals(0, $beanWorker->status());

        $pid = $beanWorker->start();
        // $this->assertEquals(1, $beanWorker->status());
        $this->assertEquals($pid, $beanWorker->masterPidManager->get());

        $this->assertEquals(3, count($beanWorker->workerProcesses));
        $workerPIDs = array_keys($beanWorker->workerProcesses);
        foreach ($workerPIDs as $pid) {
            $this->assertEquals(true, swoole_process::kill($pid, 0));
        }

        // var_dump(swoole_process::kill($workerPIDs[0], 0));
        //
        // exec("kill -15 {$workerPIDs[0]}");
        // var_dump($workerPIDs[0]);
        // var_dump(swoole_process::kill($workerPIDs[0], 0));
        // $this->assertEquals(2, count($beanWorker->workerProcesses));
        // swoole_process::kill($workerPIDs[1], SIGKILL);
        // $this->assertEquals(1, count($beanWorker->workerProcesses));
        // // kill all workers will recreate 3 new workers
        // swoole_process::kill($workerPIDs[2], SIGKILL);
        // $this->assertEquals(3, count($beanWorker->workerProcesses));

        // sleep(5);
        // $beanWorker->stop();
        // $this->assertEquals(0, $beanWorker->status());
    }
}