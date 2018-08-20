<?php

namespace BeanWorker;

use \swoole_http_server;

class MetricExporter
{
    public function __construct(array $options = [])
    {
        $options = $options + [
            'port' => 9527
            ];

        $http = new swoole_http_server("127.0.0.1", $options['port']);
        $http->on('request', function ($request, $response) {
            $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."</h1>");
        });

        $http->start();
    }
}