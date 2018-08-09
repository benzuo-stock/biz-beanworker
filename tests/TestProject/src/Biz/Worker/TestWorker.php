<?php

namespace TestProject\Biz\Worker;

use BeanWorker\Worker\AbstractWorker;

class TestWorker extends AbstractWorker
{
    public function execute($jobId, array $data)
    {
        echo "Job#{$jobId} executed with data ".json_encode($data);

        return ['code' => self::FINISH];
    }
}
