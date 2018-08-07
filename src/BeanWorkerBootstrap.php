<?php

namespace Biz\BeanWorker;

use Pimple\Container;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class BeanWorkerBootstrap
{
    protected $biz;

    protected $options;

    public function __construct(Container $biz, array $options)
    {
        $this->biz = $biz;
        $this->options = $options;
    }

    public function boot()
    {
        $container = new Container();
        $container['options'] = $this->options;
        $container['biz'] = $this->biz;
        $container['pid_file'] = realpath($this->biz['data_directory']).'/beanworker.pid';
        $container['logger'] = function ($container) {
            return new Logger('beanWorker', new StreamHandler(realpath($container['biz']['log_directory']).'/beanworker-'.date('Ymd', time()).'.log'));
        };

        return $container;
    }
}
