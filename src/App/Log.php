<?php

declare(strict_types=1);

namespace App;

use ErrorException;
use Zend\Log\Logger;
use Zend\Log\Processor\PsrPlaceholder;
use Zend\Log\Writer\Stream;

class Log
{
    /** @var int */
    private $year;

    /** @var int */
    private $month;

    /** @var string */
    private $path;

    public function __construct(?int $year = null, ?int $month = null)
    {
        $this->year = $year ?? intval(date('Y'));
        $this->month = $month ?? intval(date('n'));

        $this->path = 'data/log/'.$this->year.'/'.$this->year.str_pad((string) $this->month, 2, '0', STR_PAD_LEFT).'.log';

        $directory = dirname($this->path);
        if (!file_exists($directory) || !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    public function write(string $message, array $data = [], int $level = Logger::INFO): void
    {
        $data['ip'] = $_SERVER['REMOTE_ADDR'];

        //if (file_exists($this->path) && is_writable($this->path)) {
        $logger = new Logger();
        $logger->addWriter(new Stream($this->path));
        $logger->addProcessor(new PsrPlaceholder());
        $logger->log($level, $message, $data);
        //}
    }

    public function read(): array
    {
        $logs = [];

        if (file_exists($this->path) && is_readable($this->path)) {
            $fp = fopen($this->path, 'r');
            if ($fp !== false) {
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
                fclose($fp);
            }
        }

        return $logs;
    }
}
