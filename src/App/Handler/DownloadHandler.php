<?php

declare (strict_types = 1);

namespace App\Handler;

use App\Middleware\AclMiddleware;
use App\Model\Document;
use App\Model\Image;
use Blast\BaseUrl\BaseUrlMiddleware;
use Intervention\Image\ImageManagerStatic;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Stream;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template\TemplateRendererInterface;

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
        $file = 'data/' . $path;

        $pathExploded = explode('/', $path);

        $access = true;
        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                $access = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ);
            } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.' . $pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.roles.' . $pathExploded[1], AclMiddleware::PERM_READ);
            } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.' . $pathExploded[1])) {
                $access = $acl->isAllowed($user['username'], 'directory.users.' . $pathExploded[1], AclMiddleware::PERM_READ);
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

                $content = self::thumbnail($file);
            } else {
                $content = file_get_contents($file);
            }

            if ($content !== false && $mime !== false) {
                $gzcontent = gzencode($content);

                if ($gzcontent !== false) {
                    $body = new Stream('php://temp', 'w+');
                    $body->write($gzcontent);
                    $body->rewind();

                    $response = (new Response())
                        ->withBody($body)
                        ->withStatus(200)
                        ->withHeader('Content-Encoding', 'gzip')
                        ->withHeader('Content-Length', (string)strlen($gzcontent))
                        ->withHeader('Content-Type', $mime);

                    if ($mode === 'download') {
                        $response = $response->withHeader(
                            'Content-Disposition',
                            'attachment; filename="' . $document->getBasename() . '"'
                        );
                    }

                    return $response;
                }
            }

            return new EmptyResponse();
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
                $image->heighten(640, function ($constraint) {
                    $constraint->upsize();
                });
            } else {
                $image->widen(640, function ($constraint) {
                    $constraint->upsize();
                });
            }

            if (!file_exists(dirname($thumbnail)) || !is_dir(dirname($thumbnail))) {
                mkdir(dirname($thumbnail), 0777, true);
            }

            $image->save($thumbnail);
        }

        return (string)$image->encode();
    }
}
