Biz BeanWorker
=========

[![Build Status](https://travis-ci.org/benzuo-stock/biz-beanworker.svg?branch=master)](https://travis-ci.org/benzuo-stock/biz-beanworker)

A beanstalkd and swoole based queue worker framework.

## Runtime

 * PHP >= 7.1
 * Swoole >= 4.0
 * Beanstalk >= 1.10
 * Ubuntu 16.04 / OSX 10.13 tested

## Usage

### Bootstrap

 1. put a file named `beanworker.php` to the project root dir, see `demo/beanworker.php.dist`
 2. put the `bootstrap_beanworker.php` to the right place of your project, see `demo/bootstrap_beanworker.php.dist`
 3. write your Worker to handle job, see `demo/Worker/LogWorker.php.dist`
 4. config tubes and worker options, see `Config` chapter blow
 5. run `bin/beanworker start` from your project root dir

### Worker

```php
<?php

namespace Biz\Queue\Worker;

use BeanWorker\Worker\AbstractWorker;

class LogWorker extends AbstractWorker
{
    public function execute($jobId, array $data)
    {
        $this->getLogService()->info($data['module'], $data['action'], "Job#{$jobId} executed", $data['data']);

        // finish job
        return $this->finish();


        // retry job, default $pri = 1024, $delay = 3
        // return $this->retry(1024, 3);
        // bury job, default $pri = 1024
        // return $this->bury(1024);
    }

    /**
     * @return \Biz\System\Service\LogService
     */
    protected function getLogService()
    {
        return $this->container['biz']->service('System:LogService');
    }
}

```

### Producer

register BeanProducerServiceProvider

```php
// $biz is a instance of Pimple\Container
$biz->register(new \BeanWorker\Provider\BeanProducerServiceProvider());
```

use BeanProducer

```
$beanProducer = $biz['queue.producer'];
$beanProducer->connect();
$beanProducer->putInTube('test', arrayData);
```

## Config

biz.yml in Symfony-base project
```
parameters:
    biz_config:
        debug: "%kernel.debug%"
        db.options: "%biz_db_options%"
        queue.options: "%biz_queue_options%"
        root_directory: "%kernel.root_dir%/../"
        data_directory: "%app.data_directory%"
        cache_directory: "%kernel.cache_dir%"
        log_directory: "%kernel.logs_dir%"
        kernel.root_dir: "%kernel.root_dir%"

    biz_db_options:
        dbname: "%database_name%"
        user: "%database_user%"
        password: "%database_password%"
        host: "%database_host%"
        port: "%database_port%"
        driver: "%database_driver%"
        charset: UTF8

    biz_queue_options:
        host: "127.0.0.1"
        port: 11300
        worker:
          tubes:
            logger: {worker_num: 3, class: Biz\Queue\Worker\LogWorker}

services:
    biz:
        class: Benzuo\Biz\Base\Context\Biz
        arguments: ["%biz_config%"]
        public: true
```
