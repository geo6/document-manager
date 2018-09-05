<?php

declare(strict_types=1);

namespace App;

use Zend\Log\Logger;
use Zend\Log\Processor\PsrPlaceholder;
use Zend\Log\Writer\Stream;

class Log
{
    private $path;

    public function __construct()
    {
        $this->path = 'data/log/'.date('Y').'/'.date('Ym').'.log';

        $directory = dirname($this->path);
        if (!file_exists($directory) || !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    public function write(string $message, array $data = [], int $level = Logger::INFO)
    {
        //if (file_exists($this->path) && is_writable($this->path)) {
        $logger = new Logger();
        $logger->addWriter(new Stream($this->path));
        $logger->addProcessor(new PsrPlaceholder());
        $logger->log($level, $message, $data);
        //}
    }

    public static function read(string $file): array
    {
        $directory = realpath(dirname($file));
        if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
            throw new ErrorException(
                sprintf(
                    'The directory "%s" is not a valid directory to read log files.',
                    $directory
                )
            );
        }
        $logs = [];
        $fp = fopen($file, 'r');
        if ($fp) {
            while (($r = fgets($fp, 10240)) !== false) {
                // Zend\Log : %timestamp% %priorityName% (%priority%): %message% %extra%
                if (preg_match(
                    '/^(.+) (DEBUG|INFO|NOTICE|WARN|ERR|CRIT|ALERT|EMERG) \(([0-9])\): (.+) (\{.+\})$/',
                    $r,
                    $matches
                ) === 1) {
                    $logs[] = [
                        'timestamp'     => strtotime($matches[1]), // ISO 8601
                        'priority_name' => $matches[2],
                        'priority'      => $matches[3],
                        'message'       => $matches[4],
                        'extra'         => json_decode($matches[5], true),
                    ];
                } else {
                    throw new ErrorException(
                        sprintf(
                            'Invalid log record format for "%s".',
                            $r
                        )
                    );
                }
            }
        }
        fclose($fp);

        return $logs;
    }
}
