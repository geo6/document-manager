<?php

declare (strict_types = 1);

namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template;

class LoginHandler implements MiddlewareInterface
{
    private $containerName;

    private $router;

    private $template;

    public function __construct(
        Router\RouterInterface $router,
        Template\TemplateRendererInterface $template = null,
        string $containerName
    ) {
        $this->router = $router;
        $this->template = $template;
        $this->containerName = $containerName;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $route = $request->getAttribute(RouteResult::class);

        if ($session->has(UserInterface::class)) {
            if ($route->getMatchedRouteName() === 'logout') {
                $session->clear();
            }

            return new RedirectResponse($this->router->generateUri('home'));
        }

        $error = '';
        if ($request->getMethod() === 'POST') {
            $response = $handler->handle($request);

            if ($response->getStatusCode() !== 302) {
                return new RedirectResponse($this->router->generateUri('home'));
            }

            $error = 'Login Failure, please try again';
        }

        $data = [
            'error' => $error,
        ];
        return new HtmlResponse($this->template->render('app::login', $data));
    }
}
