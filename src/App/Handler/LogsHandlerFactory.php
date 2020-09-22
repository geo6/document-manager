<?php

declare(strict_types=1);

namespace App\Handler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;

class LogsHandlerFactory
{
    public function __invoke(ContainerInterface $container): LogsHandler
    {
        return new LogsHandler($container->get(TemplateRendererInterface::class));
    }
}
