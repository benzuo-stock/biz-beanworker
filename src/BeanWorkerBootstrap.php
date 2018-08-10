<?php

namespace BeanWorker;

use Pimple\Container;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use BeanWorker\Process\ProcessManager;

class BeanWorkerBootstrap
{
    protected $biz;

    public function __construct(Container $biz)
    {
        $this->biz = $biz;
    }

    public function boot()
    {
        $this->checkBiz();

        $container = new Container();
        $options = $this->biz['queue.options'];
        $workerOptions = $options['worker'];
        unset($options['worker']);

        $container['biz'] = $this->biz;
        $container['options'] = $options;
        $container['worker.tubes'] = $workerOptions['tubes'];
        $container['worker.reserve_timeout'] = $workerOptions['reserve_timeout'] ?? 60;

        $container['process_manager'] = function () use ($container) {
            return new ProcessManager(realpath($this->biz['data_directory']).'/beanworker.pid');
        };
        $container['logger'] = function () use ($container) {
            return new Logger('beanworker_worker', [new StreamHandler(realpath($container['biz']['log_directory']).'/beanworker_worker'.date('Ymd', time()).'.log')]);
        };

        return $container;
    }

    private function checkBiz()
    {
        if (empty($this->biz['queue.options'])) {
            exit("biz['queue.options'] does not exist \n");
        }

        if (empty($this->biz['data_directory']) || !is_dir($this->biz['data_directory'])) {
            exit("biz['data_directory'] does not exist \n");
        }

        if (empty($this->biz['log_directory']) || !is_dir($this->biz['log_directory'])) {
            exit("biz['log_directory'] does not exist \n");
        }
    }
}
