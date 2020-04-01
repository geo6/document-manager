<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Template\TemplateRendererInterface;

class UIMiddleware implements MiddlewareInterface
{
    /**
     *  @var RouterInterface
     */
    private $router;

    /**
     *  @var TemplateRendererInterface
     */
    private $template;

    public function __construct(RouterInterface $router, TemplateRendererInterface $template)
    {
        $this->router = $router;
        $this->template = $template;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            $this->template->addDefaultParam(
                $this->template::TEMPLATE_ALL,
                'user',
                $user
            );

            $user = $session->get(UserInterface::class);
            $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);

            $this->template->addDefaultParam(
                $this->template::TEMPLATE_ALL,
                'permissions',
                [
                    'logs' => $acl->isAllowed($user['username'], 'logs'),
                ]
            );
        }

        return $handler->handle($request);
    }
}
