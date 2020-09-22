<?php

declare(strict_types=1);

namespace App;

use Geo6\Laminas\Log\Log as Geo6Log;
use Laminas\Log\Logger;
use Psr\Http\Message\ServerRequestInterface;

class Log
{
    const DIRECTORY = 'data/log';

    /** @var string */
    private $path;

    public function __construct(string $message, array $extra = [], int $priority = Logger::INFO, ?ServerRequestInterface $request = null)
    {
        $this->path = self::DIRECTORY . '/' . date('Ym') . '.log';

        Geo6Log::write($this->path, $message, $extra, $priority, $request);
    }
}
