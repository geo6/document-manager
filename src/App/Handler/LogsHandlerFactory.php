<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Container\ContainerInterface;
use Mezzio\Template\TemplateRendererInterface;

class LogsHandlerFactory
{
    public function __invoke(ContainerInterface $container): LogsHandler
    {
        return new LogsHandler($container->get(TemplateRendererInterface::class));
    }
}
