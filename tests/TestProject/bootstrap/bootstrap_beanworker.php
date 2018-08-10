<?php
use BeanWorker\BeanWorkerBootstrap;

$biz = require __DIR__.'/../app/biz.php';
$bootstrap = new BeanWorkerBootstrap($biz);

return $bootstrap->boot();
