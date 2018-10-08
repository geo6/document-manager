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
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template;

class ScanHandler implements RequestHandlerInterface
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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $basePath = $request->getAttribute(BaseUrlMiddleware::BASE_PATH);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

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
        $write = false;
        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                $access = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ);
                $delete = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_DELETE);
                $write = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_WRITE);
            } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_READ);
                $delete = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
                $write = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_WRITE);
            } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_READ);
                $delete = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
                $write = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_WRITE);
            }
        }
        if ($access !== true) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
        }

        if (is_dir('data/'.$path) && is_readable('data/'.$path)) {
            $finder = new Finder();
            $finder->ignoreUnreadableDirs();
            $finder->in('data/'.$path);
            $finder->depth(0);
            $finder->notName('*.info');
            $finder->sortByName();

            if ($path === 'roles') {
                if ($session->has(UserInterface::class)) {
                    foreach ($finder->directories() as $d) {
                        $document = new Document($d->getPathname());

                        if ($acl->isAllowed(
                            $user['username'],
                            'directory.roles.'.$document->getFilename(),
                            AclMiddleware::PERM_READ
                        )) {
                            $content['directories'][] = $document;
                        }
                    }
                }
            } elseif ($path === 'users') {
                if ($session->has(UserInterface::class)) {
                    foreach ($finder->directories() as $d) {
                        $document = new Document($d->getPathname());

                        if ($acl->isAllowed(
                            $user['username'],
                            'directory.users.'.$document->getFilename(),
                            AclMiddleware::PERM_READ
                        )) {
                            $content['directories'][] = $document;
                        }
                    }
                }
            } else {
                foreach ($finder as $f) {
                    $document = new Document($f->getPathname());

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
                AclMiddleware::PERM_WRITE  => $write,
            ],
        ];

        return new HtmlResponse($this->template->render('app::scan', $data));
    }
}
