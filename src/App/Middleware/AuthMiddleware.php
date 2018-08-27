<?php

declare (strict_types = 1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Session\SessionMiddleware;

class AuthMiddleware implements MiddlewareInterface
{
    public const AUTH_ATTRIBUTE = 'auth';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            $request = $request->withAttribute(
                UserInterface::class,
                $user
            );
        }

        return $handler->handle($request/*->withAttribute(self::AUTH_ATTRIBUTE, [])*/);
    }
}
