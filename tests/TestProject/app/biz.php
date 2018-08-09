<?php

// mock biz.yml config
$bizConfig = [
    'data_directory' => __DIR__.'/data',
    'log_directory' => __DIR__.'/logs',
    'queue.options' => [
        'host' => "127.0.0.1",
        'port' => 11300,
        'worker' => [
            'daemonize' => true,
            'tubes' => [
                'test' => [
                    'worker_num' => 3,
                    'class' => 'TestProject\Biz\Worker\TestWorker',
                ]
            ]
        ]
    ]
];

//mock biz
$biz = new Pimple\Container($bizConfig);
$biz->register(new \BeanWorker\Provider\BeanProducerServiceProvider());

return $biz;