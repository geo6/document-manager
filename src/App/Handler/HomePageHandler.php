<?php

declare (strict_types = 1);

namespace App\Handler;

use App\Model\Document;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template;

class HomePageHandler implements RequestHandlerInterface
{
    private $authentication;

    private $containerName;

    private $router;

    private $template;

    public function __construct(
        Router\RouterInterface $router,
        Template\TemplateRendererInterface $template = null,
        string $containerName,
        bool $authentication
    ) {
        $this->router = $router;
        $this->template = $template;
        $this->containerName = $containerName;
        $this->authentication = $authentication;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->authentication !== false) {
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            if (!$session->has(UserInterface::class)) {
                return new RedirectResponse($this->router->generateUri('login'));
            }

            $user = $session->get(UserInterface::class);
        }

        $data = [
            'directories' => [
                'public' => null,
                'roles' => [],
                'user' => null,
            ],
        ];

        if (is_dir('data/public') && is_readable('data/public')) {
            $data['directories']['public'] = new Document('data/public');
        }
        if (isset($user)) {
            foreach ($user['roles'] as $role) {
                if (is_dir('data/roles/' . $role) && is_readable('data/roles/' . $role)) {
                    $data['directories']['roles'][] = new Document('data/roles/' . $role);
                }
            }
            if (is_dir('data/users/' . $user['username']) && is_readable('data/users/' . $user['username'])) {
                $data['directories']['user'] = new Document('data/users/' . $user['username']);
            }
        }

        return new HtmlResponse($this->template->render('app::home-page', $data));
    }
}
