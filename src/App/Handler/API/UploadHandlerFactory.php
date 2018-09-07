<?php

declare(strict_types=1);

namespace App\Handler\API;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UploadHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $authentication = isset($container->get('config')['authentication']['pdo']);

        return new UploadHandler($authentication);
    }
}
