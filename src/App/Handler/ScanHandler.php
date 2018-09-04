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

class ScanHandler implements RequestHandlerInterface
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

        $path = $request->getAttribute('path');
        if (substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }

        $breadcrumb = [];
        $content = [
            'directories' => [],
            'files'       => [],
        ];
        $images = [];

        $pathExploded = explode('/', $path);
        $previous = [];
        foreach ($pathExploded as $p) {
            $previous[] = $p;
            $breadcrumb[] = [
                'name' => $p,
                'path' => implode('/', $previous),
            ];
        }

        $access = true;
        $delete = false;
        if (isset($user, $acl)) {
            if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                $access = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ);
                $delete = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_DELETE);
            } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_READ);
                $delete = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
            } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_READ);
                $delete = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
            }
        }
        if ($access !== true) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
        }

        if (is_dir('data/'.$path) && is_readable('data/'.$path)) {
            if ($path === 'roles') {
                if (isset($user)) {
                    foreach ($user['roles'] as $role) {
                        $directoryRole = 'data/roles/'.$role;

                        if (is_dir($directoryRole) &&
                            is_readable($directoryRole) &&
                            $acl->isAllowed($user['username'], 'directory.roles.'.$role, AclMiddleware::PERM_READ)
                        ) {
                            $content['directories'][] = new Document($directoryRole);
                        }
                    }
                }
            } elseif (isset($user) && $path === 'users') {
                if (isset($user)) {
                    $directoryUser = 'data/users/'.$user['username'];

                    if (is_dir($directoryUser) &&
                        is_readable($directoryUser) &&
                        $acl->isAllowed($user['username'], 'directory.users.'.$user['username'], AclMiddleware::PERM_READ)
                    ) {
                        $content['directories'][] = new Document($directoryUser);
                    }
                }
            } else {
                $finder = new Finder();
                $finder->ignoreUnreadableDirs();
                $finder->followLinks();
                $finder->in('data/'.$path);
                $finder->depth(0);
                $finder->notName('*.info');
                $finder->sortByName();

                foreach ($finder as $f) {
                    $document = new Document($f->getRealPath());

                    if ($document->isDir()) {
                        $content['directories'][] = $document;
                    } else {
                        $content['files'][] = $document;

                        if ($document->isImage()) {
                            $images[] = $document;
                        }
                    }
                }
            }
        } else {
            return (new HtmlResponse($this->template->render('error::404', [])))->withStatus(404);
        }

        $data = [
            'path'        => $path,
            'breadcrumb'  => $breadcrumb,
            'content'     => $content,
            'images'      => $images,
            'permissions' => [
                AclMiddleware::PERM_DELETE => $delete,
            ],
        ];

        return new HtmlResponse($this->template->render('app::scan', $data));
    }
}
