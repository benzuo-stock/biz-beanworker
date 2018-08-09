<?php

namespace TestProject\Biz\Worker;

use BeanWorker\Worker\AbstractWorker;

class TestWorker extends AbstractWorker
{
    public function execute($jobId, array $data)
    {
        $testFile = $this->container['biz']['data_directory'].'/test.job';
        file_put_contents($testFile, $data['content']);

        return ['code' => self::FINISH];
    }
}
