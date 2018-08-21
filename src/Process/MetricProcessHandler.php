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

    protected $projectId;

    public function __construct($process, Container $container)
    {
        $this->process = $process;
        $this->container = $container;
        $this->port = $container['metric.port'];
        $this->processManager = $container['metric.process_manager'];
        $this->logger = $container['metric.logger'];
        $this->projectId = $container['worker.project_id'];
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
                $response->header('Content-Type', 'text/plain; version=0.0.4');
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
        $cmd = 'ps -eo pid,stat,rss,etimes,args |grep \'%s beanworker\'|awk \'$0 !~ /grep/ {print $0}\'';
        exec(sprintf($cmd, $this->projectId), $processRows);

        $gaugeMetrics = [
            'up' => [],
            'process_total' => [],
            'process_stat' => [],
            'process_memory_bytes' => [],
        ];

        $counterMetrics = [
            'uptime_seconds' => [],
        ];

        $masterUp = 0;
        $totals = [
            'master' => 0,
            'worker' => 0,
            'metric' => 0,
        ];
        $metricProcessRssTotal = 0;
        foreach ($processRows as $processRow) {
            $parsed = array_values(array_filter(explode(' ', $processRow)));
            $stat = ord(strtoupper(substr($parsed[1], 0, 1)));
            $rss = $parsed[2];
            $upTimes = $parsed[3];
            $type = $parsed[6];
            $tube = $parsed[7] ?? '';

            $labels = [
                'project' => $this->projectId,
                'type' => $type,
            ];

            if ('master' === $type) {
                $masterUp = 1;
                $totals['master']++;
            } else if ('worker' === $type) {
                $labels['tube'] = $tube;
                $totals['worker']++;
            } else if ('metric' === $type) {
                $metricProcessRssTotal += $rss;
                $totals['metric']++;
            }

            if ('metric' !== $type) {
                $gaugeMetrics['process_stat'][] = $this->createMetricItem($stat, $labels);
                $gaugeMetrics['process_memory_bytes'][] = $this->createMetricItem($rss, $labels);
            }
            $counterMetrics['uptime_seconds'][] = $this->createMetricItem($upTimes, $labels);
        }

        $gaugeMetrics['process_memory_bytes'][] = $this->createMetricItem($metricProcessRssTotal, [
            'project' => $this->projectId,
            'type' => 'metric',
        ]);

        $gaugeMetrics['up'][] = $this->createMetricItem($masterUp, [
            'project' => $this->projectId
        ]);

        foreach ($totals as $type => $total) {
            $gaugeMetrics['process_total'][] = $this->createMetricItem($total, [
                'project' => $this->projectId,
                'type' => $type,
            ]);
        }

        $content = $this->renderMetrics($gaugeMetrics, 'gauge');
        $content .= $this->renderMetrics($counterMetrics, 'counter');
        return $content;
    }

    protected function createMetricItem($value, array $labels = [])
    {
        return [
            'labels' => $labels,
            'value' => $value
        ];
    }

    protected function renderMetrics($metrics, $type = 'untyped')
    {
        $lines = [];
        foreach ($metrics as $name => $items) {
            $lines[] = "# HELP {$name} statistics";
            $lines[] = "# TYPE {$name} {$type}";
            foreach ($items as $item) {
                $labels = [];
                foreach ($item['labels'] as $k => $v) {
                    $labels[] = $k.'="'.preg_replace('/\s/', '', $v).'"';
                }
                $lines[] = 'beanworker_'.$name.'{'.implode(',', $labels).'} '.$item['value'];
            }
        }

        return implode("\n", $lines) . "\n";
    }
}