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

        $this->assertCount(3, $workerPIDs);
        $this->assertEquals(true, ProcessManager::kill($masterPid, 0));
        $this->assertEquals(true, ProcessManager::kill($workerPIDs[0], 0));
        $this->assertEquals(true, ProcessManager::kill($workerPIDs[1], 0));
        $this->assertEquals(true, ProcessManager::kill($workerPIDs[2], 0));

        $this->assertEquals($masterPid, $beanWorker->processManager->getPid());

        // The phpunit process will never exit if master process had registered ProcessManager::signal(SIGCHLD, Fn)
        ProcessManager::kill($workerPIDs[0]);
        $PIDs1 = $this->getMasterAndWorkerPIDs();
        $this->assertEquals(false, isset($PIDs1['workers'][$workerPIDs[0]]));
        $this->assertCount(2, $PIDs1['workers']);

        ProcessManager::kill($workerPIDs[1]);
        $PIDs2 = $this->getMasterAndWorkerPIDs();
        $this->assertEquals(false, isset($PIDs2['workers'][$workerPIDs[1]]));
        $this->assertCount(1, $PIDs2['workers']);
    }

    public function testPut()
    {
        $beanProducer = $this->getBeanProducer();
        $beanProducer->connect();

        $content = time();
        $beanProducer->putInTube('test', ['content' => $content]);

        sleep(1);
        $container = static::getContainer();
        $testFile = $container['biz']['data_directory'].'/test.job';
        $this->assertEquals(true, file_exists($testFile));
        $this->assertEquals($content, file_get_contents($testFile));
    }

    private function getMasterAndWorkerPIDs()
    {
        $cmd = 'ps -ef |grep \'%s\' |awk \'$0 !~ /grep/ {print $2}\'';
        $PIDs = [];
        exec(sprintf($cmd, 'beanworker:'), $PIDs);

        if (empty($PIDs)) {
            exec(sprintf($cmd, 'phpunit'), $PIDs);

            if (false !== strpos(php_uname(), 'Darwin')) {
                // fix OSX bug, there are 5 processes when run phpunit in OSX
                array_shift($PIDs);
            }
        }

        return [
            'master' => array_shift($PIDs),
            'workers' => $PIDs,
        ];
    }

    /**
     * @return BeanProducer
     */
    private function getBeanProducer()
    {
        $container = static::getContainer();
        return $container['biz']['queue.producer'];
    }

    public static function getContainer()
    {
        $biz = require __DIR__.'/TestProject/app/biz.php';
        $bootstrap = new BeanWorkerBootstrap($biz);
        $container = $bootstrap->boot();
        return $container;
    }
}