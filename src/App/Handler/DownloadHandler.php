<?php

declare(strict_types=1);

namespace App\Handler;

use App\Middleware\AclMiddleware;
use App\Model\Document;
use Blast\BaseUrl\BaseUrlMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Stream;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template\TemplateRendererInterface;

class DownloadHandler implements RequestHandlerInterface
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
