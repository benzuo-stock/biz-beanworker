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

        $biz['queue.logger'] = function ($biz) {
            return new Logger('beanworker_producer', [new StreamHandler(realpath($biz['log_directory']).'/beanworker_producer'.date('Ymd', time()).'.log')]);
        };

        $biz['queue.producer'] = function ($biz) use ($options) {
            return new BeanProducer($options, $biz['queue.logger']);
        };
    }
}
