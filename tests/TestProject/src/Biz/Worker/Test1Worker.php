<?php

namespace TestProject\Biz\Worker;

use BeanWorker\Worker\AbstractWorker;

class Test1Worker extends AbstractWorker
{
    public function execute($jobId, array $data)
    {
        $testFile = $this->container['biz']['data_directory'].'/test1.job';
        file_put_contents($testFile, $data['content']);

        return ['code' => self::FINISH];
    }
}
