<?php

declare (strict_types = 1);

namespace App\Handler;

use App\Model\Document;
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
        if ($this->authentication !== false) {
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            if (!$session->has(UserInterface::class)) {
                return new RedirectResponse($this->router->generateUri('login'));
            }

            $user = $session->get(UserInterface::class);
        }

        $path = $request->getAttribute('path');

        $breadcrumb = [];
        $content = [
            'directories' => [],
            'files' => [],
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

        if ($pathExploded[0] === 'roles' &&
            (!isset($user) || (isset($pathExploded[1]) && !in_array($pathExploded[1], $user['roles'])))
        ) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
        }
        if ($pathExploded[0] === 'users' &&
            (!isset($user) || (isset($pathExploded[1]) && $pathExploded[1] !== $user['username']))
        ) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
        }

        if (is_dir('data/' . $path) && is_readable('data/' . $path)) {
            if ($path === 'roles') {
                foreach ($user['roles'] as $role) {
                    if (is_dir('data/roles/' . $role) && is_readable('data/roles/' . $role)) {
                        $content['directories'][] = new Document('data/roles/' . $role);
                    }
                }
            } elseif ($path === 'users') {
                if (is_dir('data/users/' . $user['username']) && is_readable('data/users/' . $user['username'])) {
                    $content['directories']['user'] = new Document('data/users/' . $user['username']);
                }
            } else {
                $finder = new Finder();
                $finder->ignoreUnreadableDirs();
                $finder->followLinks();
                $finder->in('data/' . $path);
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
            'path' => $path,
            'breadcrumb' => $breadcrumb,
            'content' => $content,
            'images' => $images,
        ];
        return new HtmlResponse($this->template->render('app::scan', $data));
    }
}
