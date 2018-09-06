<?php

declare(strict_types=1);

namespace App\Handler;

use App\Middleware\AclMiddleware;
use App\Model\Document;
use Blast\BaseUrl\BaseUrlMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Finder\Finder;
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
        $basePath = $request->getAttribute(BaseUrlMiddleware::BASE_PATH);

        if ($this->authentication !== false) {
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            if (!$session->has(UserInterface::class)) {
                return new RedirectResponse($basePath.$this->router->generateUri('login'));
            }

            $user = $session->get(UserInterface::class);
            $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        }

        $data = [
            'directories' => [
                'public' => null,
                'roles'  => [],
                'user'   => null,
            ],
        ];

        if (is_dir('data/public') && is_readable('data/public')) {
            if (!isset($acl) || $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ)) {
                $data['directories']['public'] = new Document('data/public');
            }
        }
        if (isset($user, $acl)) {
            $finder = new Finder();
            $finder->ignoreUnreadableDirs();
            $finder->in('data/roles');
            $finder->depth(0);
            $finder->sortByName();

            foreach ($finder->directories() as $d) {
                $document = new Document($d->getPathname());

                if ($acl->isAllowed(
                    $user['username'],
                    'directory.roles.'.$document->getFilename(),
                    AclMiddleware::PERM_READ
                )) {
                    $data['directories']['roles'][] = $document;
                }
            }

            $finder = new Finder();
            $finder->ignoreUnreadableDirs();
            $finder->in('data/users');
            $finder->depth(0);
            $finder->sortByName();

            foreach ($finder->directories() as $d) {
                $document = new Document($d->getPathname());

                if ($acl->isAllowed(
                    $user['username'],
                    'directory.users.'.$document->getFilename(),
                    AclMiddleware::PERM_READ
                )) {
                    $data['directories']['users'][] = $document;
                }
            }
        }

        return new HtmlResponse($this->template->render('app::home-page', $data));
    }
}
