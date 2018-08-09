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

    // public static function tearDownAfterClass()
    // {
    //     $beanWorker = new BeanWorker(static::getContainer());
    //     $beanWorker->stop();
    // }

    public function testStart()
    {
        $beanWorker = new BeanWorker(static::getContainer());
        $getPidCmd = 'ps -A |grep phpunit |awk \'$0 !~ /grep/ {print $1}\'';

        $this->assertEquals(true, $beanWorker->masterPidManager->isRunning());

        exec($getPidCmd, $PIDs);

        // 1 master + 3 worker
        $this->assertCount(4, $PIDs);

        foreach ($PIDs as $pid) {
            $this->assertEquals(true, swoole_process::kill($pid, 0));
        }

        $masterPid = array_shift($PIDs);
        $workerPIDs = $PIDs;
        $this->assertEquals($masterPid, $beanWorker->masterPidManager->get());

        swoole_process::kill($workerPIDs[0], SIGKILL);
        exec($getPidCmd, $PIDs1);
        $this->assertEquals(false, isset($PIDs1[$workerPIDs[0]]));
        $this->assertEquals(2, \count($PIDs1) - 1);

        swoole_process::kill($workerPIDs[1], SIGKILL);
        exec($getPidCmd, $PIDs2);
        $this->assertEquals(false, isset($PIDs2[$workerPIDs[1]]));
        $this->assertEquals(1, \count($PIDs2) - 1);

        swoole_process::kill($workerPIDs[2], SIGKILL);
        exec($getPidCmd, $PIDs3);
        $this->assertEquals(false, isset($PIDs3[$workerPIDs[2]]));
        $this->assertEquals(0, \count($PIDs3) - 1);
    }

    public function testStop()
    {
        $beanWorker = new BeanWorker(static::getContainer());
        $beanWorker->stop();
        $this->assertEquals(false, $beanWorker->masterPidManager->isRunning());
    }

    public static function getContainer()
    {
        $biz = require __DIR__.'/TestProject/app/biz.php';
        $bootstrap = new BeanWorkerBootstrap($biz);
        return $bootstrap->boot();
    }
}