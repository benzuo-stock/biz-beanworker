<?php

namespace Tests;

use BeanWorker\BeanProducer;
use BeanWorker\BeanWorkerBootstrap;
use PHPUnit\Framework\TestCase;
use BeanWorker\BeanWorker;
use \swoole_process;

class BeanProducerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        $beanWorker = new BeanWorker(static::getContainer());
        $beanWorker->start();
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

    public static function getContainer()
    {
        $biz = require __DIR__.'/TestProject/app/biz.php';
        $bootstrap = new BeanWorkerBootstrap($biz);
        $container = $bootstrap->boot();
        $container['worker.daemonize'] = false;
        return $container;
    }

    /**
     * @return BeanProducer
     */
    private function getBeanProducer()
    {
        $container = static::getContainer();
        return $container['biz']['queue.producer'];
    }
}