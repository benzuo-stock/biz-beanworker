<?php

namespace BeanWorker\Process;

use \swoole_process;

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
        if (file_exists($this->pidFile)) {
            $pid = (int) file_get_contents($this->pidFile);
            if ($pid && swoole_process::kill($pid, 0)) {
                return $pid;
            }
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
