Biz BeanWorker
=========

A beanstalkd-based queue worker framework.

## Runtime

 * PHP >= 7.1
 * Swoole >= 4.0
 * Beanstalk >= 1.10

## Usage

 see demo dir

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
        port: "11300"

services:
    biz:
        class: Benzuo\Biz\Base\Context\Biz
        arguments: ["%biz_config%"]
        public: true
```