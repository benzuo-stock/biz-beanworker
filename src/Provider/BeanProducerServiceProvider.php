<?php

namespace Biz\BeanWorker\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Biz\BeanWorker\BeanProducer;

class BeanProducerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $biz)
    {
        $options = $biz['queue.options'];

        $biz['queue.producer'] = function ($biz) use ($options) {
            return new BeanProducer($options);
        };
    }
}
