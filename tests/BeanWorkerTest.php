<?php

namespace Tests;

use BeanWorker\BeanWorkerBootstrap;
use PHPUnit\Framework\TestCase;
use BeanWorker\BeanWorker;
use \swoole_process;

class BeanWorkerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        $beanWorker = new BeanWorker(static::getContainer());
        $beanWorker->start();
    }

    public function testStart()
    {
        $beanWorker = new BeanWorker(static::getContainer());

        $this->assertEquals(true, $beanWorker->masterPidManager->isRunning());

        $PIDs = $this->getMasterAndWorkerPIDs();
        $masterPid = $PIDs['master'];
        $workerPIDs = $PIDs['workers'];

        $this->assertCount(3, $workerPIDs);
        $this->assertEquals(true, swoole_process::kill($masterPid, 0));
        $this->assertEquals(true, swoole_process::kill($workerPIDs[0], 0));
        $this->assertEquals(true, swoole_process::kill($workerPIDs[1], 0));
        $this->assertEquals(true, swoole_process::kill($workerPIDs[2], 0));

        $this->assertEquals($masterPid, $beanWorker->masterPidManager->get());

        swoole_process::kill($workerPIDs[0], SIGKILL);
        $PIDs1 = $this->getMasterAndWorkerPIDs();
        $this->assertEquals(false, isset($PIDs1['workers'][$workerPIDs[0]]));
        $this->assertCount(2, $PIDs1['workers']);

        swoole_process::kill($workerPIDs[1], SIGKILL);
        $PIDs2 = $this->getMasterAndWorkerPIDs();
        $this->assertEquals(false, isset($PIDs2['workers'][$workerPIDs[1]]));
        $this->assertCount(1, $PIDs2['workers']);
    }

    public static function getContainer()
    {
        $biz = require __DIR__.'/TestProject/app/biz.php';
        $bootstrap = new BeanWorkerBootstrap($biz);
        $container = $bootstrap->boot();
        $container['worker.daemonize'] = false;
        return $container;
    }

    private function getMasterAndWorkerPIDs()
    {
        $cmd = 'ps -ef |grep \'%s\' |awk \'$0 !~ /grep/ {print $2}\'';
        $PIDs = [];
        exec(sprintf($cmd, 'beanworker:'), $PIDs);

        if (empty($PIDs)) {
            exec(sprintf($cmd, 'phpunit'), $PIDs);
        }

        return [
            'master' => array_shift($PIDs),
            'workers' => $PIDs,
        ];
    }
}