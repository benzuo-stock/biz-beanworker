<?php

namespace BeanWorker\Worker;

interface WorkerInterface
{
    const FINISH = 'finish';

    const RETRY = 'retry';

    const BURY = 'bury';

    /**
     * @param int $jobId
     * @param array $data (json to array)
     *
     * @return array
     *               [
     *               code => WorkerInterface::RETRY,
     *               pri => 1024, //可选
     *               delay => 10, //可选
     *               ]
     */
    public function execute($jobId, array $data);
}
