<?php

declare (strict_types = 1);

namespace App\Handler;

use App\Model\Document;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Stream;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template;

class DownloadHandler implements RequestHandlerInterface
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

        $route = $request->getAttribute(RouteResult::class);

        $mode = $route->getMatchedRouteName();

        $path = $request->getAttribute('path');
        $file = 'data/' . $path;

        $pathExploded = explode('/', $path);
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

        if (file_exists($file)) {
            $document = new Document($file);

            $mime = $document->getMimeType();
            $content = gzencode(file_get_contents($file));

            $body = new Stream('php://temp', 'w+');
            $body->write($content);
            $body->rewind();

            $response = (new Response())->
                withBody($body)->
                withStatus(200)->
                withHeader('Content-Encoding', 'gzip')->
                withHeader('Content-Length', strlen($content))->
                withHeader('Content-Type', $mime);

            if ($mode === 'download') {
                $response = $response->withHeader(
                    'Content-Disposition',
                    'attachment; filename="' . $document->getBasename() . '"'
                );
            }

            return $response;
        }

        return (new EmptyResponse())->withStatus(404);
    }
}
