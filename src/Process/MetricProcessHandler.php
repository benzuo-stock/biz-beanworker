<?php

namespace BeanWorker\Process;

use Pimple\Container;
use Psr\Log\LoggerInterface;
use \swoole_http_server;

class MetricProcessHandler
{
    protected $process;

    /**
     * @var Container
     */
    protected $container;

    protected $port;

    /**
     * @var ProcessManager;
     */
    protected $processManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct($process, Container $container)
    {
        $this->process = $process;
        $this->container = $container;
        $this->port = $container['metric.port'];
        $this->processManager = $container['metric.process_manager'];
        $this->logger = $container['metric.logger'];
    }

    public function start()
    {
        echo "metric starting...\n";
        $this->logger->info('metric starting...');

        if ($this->processManager->isRunning()) {
            echo "ERROR: metric#{$this->processManager->getPid()} is already running.\n";

            return;
        }

        $server = new swoole_http_server('0.0.0.0', $this->port);
        $server->set([
            'reactor_num' => 1,
            'worker_num' => 1,
            'log_file' => \dirname($this->processManager->pidFile).'/metric_server.log',
        ]);

        $pid = posix_getpid();
        $this->processManager->savePid($pid);

        echo "metric##{$pid} started...\n";
        $this->logger->info("metric#{$pid} started.");

        $server->on('request', function ($request, $response) {
            $this->logger->info(json_encode($request->header).json_encode($request->server));
            if ('/metrics' === $request->server['request_uri']) {
                $response->end($this->createMetricsResponse());
            } else {
                $response->end($this->createHomeResponse());
            }
        });

        $server->start();
    }

    protected function createHomeResponse()
    {
        return '<html>
                  <head>
                    <title>Beanworker Exporter</title>
                   </head>
                  <body>
                      <h1>Beanworker Exporter</h1>
                      <p><a href="/metrics">Metrics</a></p>
                  </body>
                </html>';
    }

    protected function createMetricsResponse()
    {
        $cmd = 'ps -eo pid,stat,rss,args |grep beanworker|awk \'$0 !~ /grep/ {print $0}\'';
        exec($cmd, $ps);

        $res = [];
        foreach ($ps as $rowString) {
            $arr = array_values(array_filter(explode(' ', $rowString)));
            $row = [
                'pid' => $arr[0],
                'stat' => $arr[1],
                'rss' => $arr[2],
                'project' => $arr[3],
                'process' => $arr[5],
                'tube' => $arr[6] ?? '',
            ];
            $res[] = $row;
        }

        return json_encode($res);
    }
}