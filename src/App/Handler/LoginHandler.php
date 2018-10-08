<?php

declare(strict_types=1);

namespace App\Handler;

use App\Log;
use Blast\BaseUrl\BaseUrlMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Log\Logger;

class LoginHandler implements MiddlewareInterface
{
    /**
     * @var string $containerName
     */
    private $containerName;

    /**
     * @var RouterInterface $router
     */
    private $router;

    /**
     * @var TemplateRendererInterface $template
     */
    private $template;

    public function __construct(
        RouterInterface $router,
        TemplateRendererInterface $template,
        string $containerName
    ) {
        $this->router = $router;
        $this->template = $template;
        $this->containerName = $containerName;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $basePath = $request->getAttribute(BaseUrlMiddleware::BASE_PATH);
        $route = $request->getAttribute(RouteResult::class);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        if ($session->has(UserInterface::class)) {
            if ($route->getMatchedRouteName() === 'logout') {
                $session->clear();
            }

            return new RedirectResponse($basePath.$this->router->generateUri('home'));
        }

        $error = '';
        if ($request->getMethod() === 'POST') {
            $response = $handler->handle($request);

            if ($response->getStatusCode() !== 302) {
                $user = $session->get(UserInterface::class);

                (new Log())->write('User "{username}" logged in.', ['username' => $user['username']], Logger::INFO);

                return new RedirectResponse($basePath.$this->router->generateUri('home'));
            }

            (new Log())->write('User "{username}" failed to log in.', ['username' => $_POST['username']], Logger::WARN);

            $error = 'Login failure, please try again.';
        }

        $data = [
            'error' => $error,
        ];

        return new HtmlResponse($this->template->render('app::login', $data));
    }
}
