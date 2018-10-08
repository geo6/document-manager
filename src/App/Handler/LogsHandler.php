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
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Expressive\Template\TemplateRendererInterface;

class LogsHandler implements RequestHandlerInterface
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
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $user = $session->get(UserInterface::class);

        if ($acl->isAllowed($user['username'], 'logs') !== true) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
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
