<?php

declare(strict_types=1);

namespace App\Handler;

use App\Log;
use App\Middleware\AclMiddleware;
use Blast\BaseUrl\BaseUrlMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template;

class LogsHandler implements RequestHandlerInterface
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

            if ($acl->isAllowed($user['username'], 'logs') !== true) {
                return (new HtmlResponse($this->template->render('error::error', [
                    'status' => 403,
                    'reason' => 'Forbidden',
                ])))->withStatus(403);
            }
        }

        $logs = [];
        $_logs = (new Log())->read();
        foreach ($_logs as $log) {
            $year = date('Y', $log['timestamp']);
            $month = date('Y-m', $log['timestamp']);

            if (!isset($logs[$year])) {
                $logs[$year] = [];
            }
            if (!isset($logs[$year][$month])) {
                $logs[$year][$month] = [];
            }

            $logs[$year][$month][] = $log;
        }

        $data = [
            'logs' => $logs,
        ];

        return new HtmlResponse($this->template->render('app::logs', $data));
    }
}
