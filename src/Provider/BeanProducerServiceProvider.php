<?php

namespace BeanWorker\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use BeanWorker\BeanProducer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class BeanProducerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $biz)
    {
        $options = $biz['queue.options'];
        $workerOptions = $options['worker'];
        $tubes = array_keys($workerOptions['tubes']);

        $biz['queue.logger'] = function ($biz) {
            return new Logger('beanworker_producer', [new StreamHandler(realpath($biz['log_directory']).'/beanworker_producer'.date('Ymd', time()).'.log')]);
        };

        foreach ($tubes as $tube) {
            $biz["queue.producer.{$tube}"] = function ($biz) use ($options, $tube) {
                return new BeanProducer($options, $tube, $biz['queue.logger']);
            };
        }
    }
}
