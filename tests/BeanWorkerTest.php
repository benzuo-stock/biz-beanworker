<?php

namespace Tests;

use BeanWorker\BeanWorkerBootstrap;
use BeanWorker\Process\ProcessManager;
use PHPUnit\Framework\TestCase;
use BeanWorker\BeanProducer;
use BeanWorker\BeanWorker;

class BeanWorkerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        $beanWorker = new BeanWorker(static::getContainer());
        $beanWorker->start();
    }

    public static function tearDownAfterClass()
    {
        $beanWorker = new BeanWorker(static::getContainer());
        $beanWorker->stop();
    }

    public function testStart()
    {
        $beanWorker = new BeanWorker(static::getContainer());

        $this->assertEquals(true, $beanWorker->processManager->isRunning());

        $PIDs = $this->getMasterAndWorkerPIDs();
        $masterPid = $PIDs['master'];
        $workerPIDs = $PIDs['workers'];

        $this->assertCount(6, $workerPIDs);
        $this->assertEquals(true, ProcessManager::kill($masterPid, 0));
        foreach ($workerPIDs as $workerPID) {
            $this->assertEquals(true, ProcessManager::kill($workerPID, 0));
        }

        $this->assertEquals($masterPid, $beanWorker->processManager->getPid());

        // The phpunit process will never exit if master process had registered ProcessManager::signal(SIGCHLD, Fn)
        ProcessManager::kill($workerPIDs[0]);
        $PIDs1 = $this->getMasterAndWorkerPIDs();
        $this->assertEquals(false, isset($PIDs1['workers'][$workerPIDs[0]]));
        $this->assertCount(5, $PIDs1['workers']);

        ProcessManager::kill($workerPIDs[1]);
        $PIDs2 = $this->getMasterAndWorkerPIDs();
        $this->assertEquals(false, isset($PIDs2['workers'][$workerPIDs[1]]));
        $this->assertCount(4, $PIDs2['workers']);
    }

    public function testPut()
    {
        $content1 = md5(microtime(true));
        $test1BeanProducer = $this->getTest1BeanProducer();
        $test1BeanProducer->put(['content' => $content1]);

        $content2 = md5(microtime(true));
        $test2BeanProducer = $this->getTest2BeanProducer();
        $test2BeanProducer->put(['content' => $content2]);

        sleep(1);
        $container = static::getContainer();
        $test1File = $container['biz']['data_directory'].'/test1.job';
        $this->assertEquals(true, file_exists($test1File));
        $this->assertEquals($content1, file_get_contents($test1File));

        $test2File = $container['biz']['data_directory'].'/test2.job';
        $this->assertEquals(true, file_exists($test2File));
        $this->assertEquals($content2, file_get_contents($test2File));
    }

    private function getMasterAndWorkerPIDs()
    {
        $cmd = 'ps -ef |grep \'%s\' |awk \'$0 !~ /grep/ {print $2}\'';
        exec(sprintf($cmd, 'beanworker: master'), $masterPids);
        exec(sprintf($cmd, 'beanworker: worker'), $workerPids);
        $masterPid = empty($masterPids) ? 0 : array_shift($masterPids);

        if (!$masterPid) {
            exec(sprintf($cmd, 'phpunit'), $PIDs);
            if (false !== strpos(php_uname(), 'Darwin')) {
                // fix OSX bug, there are 5 processes when run phpunit in OSX
                array_shift($PIDs);
            }
            $masterPid = array_shift($PIDs);
            $workerPids = $PIDs;
        }

        return [
            'master' => $masterPid,
            'workers' => $workerPids,
        ];
    }

    /**
     * @return BeanProducer
     */
    private function getTest1BeanProducer()
    {
        $container = static::getContainer();
        return $container['biz']['queue.producer.test1'];
    }

    /**
     * @return BeanProducer
     */
    private function getTest2BeanProducer()
    {
        $container = static::getContainer();
        return $container['biz']['queue.producer.test2'];
    }

    public static function getContainer()
    {
        $biz = require __DIR__.'/TestProject/app/biz.php';
        $bootstrap = new BeanWorkerBootstrap($biz);
        $container = $bootstrap->boot();
        return $container;
    }
}