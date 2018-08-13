<?php

namespace BeanWorker\Process;

use \swoole_process;

class ProcessManager
{
    private $pidFile;

    public function __construct($pidFile)
    {
        $this->pidFile = $pidFile;
    }

    public function isRunning()
    {
        return $this->getPid() > 0;
    }

    public function getPid()
    {
        if (file_exists($this->pidFile)) {
            $pid = (int) file_get_contents($this->pidFile);
            if ($pid && swoole_process::kill($pid, 0)) {
                return $pid;
            }
        }

        return -1;
    }

    public function savePid($pid)
    {
        file_put_contents($this->pidFile, (int) $pid);
    }

    public function clearPid()
    {
        if (!file_exists($this->pidFile)) {
            return;
        }

        unlink($this->pidFile);
    }

    public static function createProcess(callable $callback)
    {
        return new swoole_process($callback);
    }

    public static function setProcessName($name)
    {
        try {
            if (function_exists('cli_set_process_title')) {
                @cli_set_process_title($name);
            } else {
                swoole_set_process_name($name);
            }
        } catch (\Exception $e) {
        }
    }

    public static function signal($signal, callable $callback)
    {
        return swoole_process::signal($signal, $callback);
    }

    public static function daemon()
    {
        return swoole_process::daemon();
    }

    public static function kill($pid, $signal = SIGKILL)
    {
        return swoole_process::kill($pid, $signal);
    }

    public static function wait($blocking)
    {
        return swoole_process::wait($blocking);
    }
}
