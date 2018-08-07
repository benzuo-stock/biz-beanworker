<?php

namespace Biz\BeanWorker\Worker;

interface JobInterface
{
    public function getId();

    public function getData();
}
