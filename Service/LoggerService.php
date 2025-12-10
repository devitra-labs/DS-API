<?php
class LoggerService {
    protected $logdir;
    public function __construct($logdir = null) {
        $this->logdir = $logdir ?: base_path('logs');
        if (!is_dir($this->logdir)) mkdir($this->logdir, 0755, true);
    }

    public function info($msg) { $this->write('info', $msg); }
    public function error($msg) { $this->write('error', $msg); }

    protected function write($level, $msg) {
        $file = $this->logdir . '/desaverse_' . date('Y-m-d') . '.log';
        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $msg);
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}