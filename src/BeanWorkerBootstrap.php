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
        $metricOptions = $options['metric'] ?? [];
        unset($options['worker'], $options['metric']);

        $container['biz'] = $this->biz;
        $container['env'] = $this->biz['env'] ?? 'prod';
        $container['options'] = $options;
        $container['worker.project_id'] = $workerOptions['project_id'];
        $container['worker.tubes'] = $workerOptions['tubes'];
        $container['worker.reserve_timeout'] = $workerOptions['reserve_timeout'] ?? 60;
        $container['worker.process_manager'] = function () {
            return new ProcessManager(realpath($this->biz['data_directory']).'/beanworker.pid');
        };
        $container['worker.logger'] = $container->factory(function () use ($container) {
            return new Logger('beanworker_worker', [new StreamHandler(realpath($container['biz']['log_directory']).'/beanworker_worker'.date('Ymd').'.log')]);
        });

        $container['metric.enabled'] = empty($metricOptions['enabled']) ? 0 : 1;
        $container['metric.port'] = $metricOptions['port'] ?? '';
        $container['metric.process_manager'] = function () {
            return new ProcessManager(realpath($this->biz['data_directory']).'/beanworker_metric.pid');
        };
        $container['metric.logger'] = $container->factory(function () use ($container) {
            return new Logger('beanworker_metric', [new StreamHandler(realpath($container['biz']['log_directory']).'/beanworker_metric'.date('Ymd').'.log')]);
        });

        //force disable metric exporter in OSX, as OSX cannot modify process name
        if (false !== strpos(php_uname(), 'Darwin')) {
            $container['metric.enabled'] = 0;
        }

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
