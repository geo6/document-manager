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

        $data = [
            'directories' => [
                'public' => null,
                'roles'  => [],
                'users'  => [],
            ],
        ];

        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            if (is_dir('data/public') && is_readable('data/public') && $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ)) {
                $data['directories']['public'] = new Document('data/public');
            }

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
        } else {
            if (is_dir('data/public') && is_readable('data/public')) {
                $data['directories']['public'] = new Document('data/public');
            }
        }

        return new HtmlResponse($this->template->render('app::home-page', $data));
    }
}
