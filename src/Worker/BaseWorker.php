<?php

namespace Biz\BeanWorker\Worker;

use Benzuo\Biz\Base\Context\BizAware;

class BaseWorker extends BizAware implements WorkerInterface
{
    public function execute(JobInterface $job)
    {
    }
}
