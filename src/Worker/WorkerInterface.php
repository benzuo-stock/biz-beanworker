<?php

namespace Biz\BeanWorker\Worker;

interface WorkerInterface
{
    public function execute(JobInterface $job);
}
