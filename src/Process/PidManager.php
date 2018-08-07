<?php

namespace Biz\BeanWorker\Process;

use Swoole\Process;

class PidManager
{
    private $pidFile;

    public function __construct($pidFile)
    {
        $this->pidFile = $pidFile;
    }

    public function isRunning()
    {
        return $this->get() > 0;
    }

    public function get()
    {
        $pid = (int) file_get_contents($this->pidFile);
        if ($pid && Process::kill($pid, 0)) {
            return $pid;
        }

        return -1;
    }

    public function save($pid)
    {
        file_put_contents($this->pidFile, (int) $pid);
    }

    public function clear()
    {
        if (!file_exists($this->pidFile)) {
            return;
        }

        unlink($this->pidFile);
    }
}
