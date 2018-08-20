<?php

// mock biz.yml config
$bizConfig = [
    'env' => 'test',
    'data_directory' => __DIR__.'/data',
    'log_directory' => __DIR__.'/logs',
    'queue.options' => [
        'host' => '127.0.0.1',
        'port' => 11300,
        'metric' => [
            'enabled' => 1,
            'port' => 9527,
        ],
        'worker' => [
            'project_id' => 'test_project',
            'reserve_timeout' => 5,
            'tubes' => [
                'test1' => [
                    'worker_num' => 3,
                    'class' => 'TestProject\Biz\Worker\Test1Worker',
                ],
                'test2' => [
                    'worker_num' => 3,
                    'class' => 'TestProject\Biz\Worker\Test2Worker',
                ]
            ]
        ]
    ]
];

//mock biz
$biz = new Pimple\Container($bizConfig);
$biz->register(new \BeanWorker\Provider\BeanProducerServiceProvider());

return $biz;