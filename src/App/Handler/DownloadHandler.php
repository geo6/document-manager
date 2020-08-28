<?php

declare(strict_types=1);

namespace App\Handler;

use App\Middleware\AclMiddleware;
use App\Model\Document;
use App\Model\Image;
use Blast\BaseUrl\BaseUrlMiddleware;
use Intervention\Image\ImageManagerStatic;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Stream;
use Mezzio\Authentication\UserInterface;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DownloadHandler implements RequestHandlerInterface
{
    /**
     * @var string
     */
    private $containerName;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TemplateRendererInterface
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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $basePath = $request->getAttribute(BaseUrlMiddleware::BASE_PATH);
        $route = $request->getAttribute(RouteResult::class);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $mode = $route->getMatchedRouteName();

        $path = $request->getAttribute('path');
        $file = 'data/'.$path;

        $pathExploded = explode('/', $path);

        $access = true;
        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                $access = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ);
            } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_READ);
            } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_READ);
            }
        }
        if ($access !== true) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
        }

        if (file_exists($file)) {
            $document = new Document($file);

            $mime = $document->getMimeType();

            if ($document->isImage() && $mode === 'view') {
                $document = new Image($file);

                $stream = new Stream(self::thumbnail($file));

                return (new Response())
                    ->withBody($stream)
                    ->withStatus(200)
                    ->withHeader('Content-Length', (string) $stream->getSize())
                    ->withHeader('Content-Type', $mime !== false ? $mime : 'application/octet-stream');
            } else {
                $stream = new Stream($file);

                return (new Response())
                    ->withBody($stream)
                    ->withStatus(200)
                    ->withHeader('Content-Length', (string) $stream->getSize())
                    ->withHeader('Content-Type', $mime !== false ? $mime : 'application/octet-stream')
                    ->withHeader(
                        'Content-Disposition',
                        'attachment; filename="'.$document->getBasename().'"'
                    );
            }
        }

        return (new EmptyResponse())->withStatus(404);
    }

    private static function thumbnail(string $file): string
    {
        $directory = dirname($file);
        $fname = basename($file);
        $thumbnail = sprintf('%s/.thumbnails/%s', $directory, $fname);

        if (file_exists($thumbnail)) {
            $image = ImageManagerStatic::make($thumbnail);
        } else {
            $image = ImageManagerStatic::make($file);
            $image->orientate();

            if ($image->height() > $image->width()) {
                $image->heighten(640, function ($constraint): void {
                    $constraint->upsize();
                });
            } else {
                $image->widen(640, function ($constraint): void {
                    $constraint->upsize();
                });
            }

            if (!file_exists(dirname($thumbnail)) || !is_dir(dirname($thumbnail))) {
                mkdir(dirname($thumbnail), 0777, true);
            }

            $image->save($thumbnail);
        }

        return $thumbnail;
    }
}
