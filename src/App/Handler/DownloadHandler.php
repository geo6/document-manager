<?php

declare(strict_types=1);

namespace App\Handler;

use App\Middleware\AclMiddleware;
use App\Model\Document;
use App\Model\Image;
use Blast\BaseUrl\BaseUrlMiddleware;
use Geo6\Image\Image as ImageTool;
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
                $exif = $document->getEXIF();

                if (isset($exif['Orientation']) && $exif['Orientation'] > 1) {
                    $rotatedDirectory = 'data/cache/'.ltrim(dirname($file), 'data/');
                    $rotatedFile = $rotatedDirectory.'/'.basename($file).'.rotated';

                    $md5File = $rotatedDirectory.'/'.basename($file).'.md5';

                    $md5_1 = md5_file($file);
                    $md5_2 = file_exists($md5File) ? file_get_contents($md5File) : null;

                    if (!file_exists($rotatedFile) || $md5_1 !== $md5_2) {
                        if (!file_exists($rotatedDirectory) || !is_dir($rotatedDirectory)) {
                            mkdir(dirname($rotatedFile), 0775, true);
                        }

                        $image = ImageTool::createFromFile($file);
                        $rotated = $image->EXIFRotate();
                        $rotated->save($rotatedFile);

                        file_put_contents($md5File, $md5_1);
                    }

                    $file = $rotatedFile;
                }
            }

            $content = file_get_contents($file);

            if ($content !== false && $mime !== false) {
                $gzcontent = gzencode($content);

                if ($gzcontent !== false) {
                    $body = new Stream('php://temp', 'w+');
                    $body->write($gzcontent);
                    $body->rewind();

                    $response = (new Response())->
                        withBody($body)->
                        withStatus(200)->
                        withHeader('Content-Encoding', 'gzip')->
                        withHeader('Content-Length', (string) strlen($gzcontent))->
                        withHeader('Content-Type', $mime);

                    if ($mode === 'download') {
                        $response = $response->withHeader(
                            'Content-Disposition',
                            'attachment; filename="'.$document->getBasename().'"'
                        );
                    }

                    return $response;
                }
            }

            return new EmptyResponse();
        }

        return (new EmptyResponse())->withStatus(404);
    }
}
