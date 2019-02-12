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

        $year = $request->getAttribute('year') ?? date('Y');
        $month = $request->getAttribute('month') ?? date('m');

        $glob = glob('data/log/*/*.log');
        rsort($glob);

        $list = [];
        foreach ($glob as $g) {
            $name = pathinfo($g, PATHINFO_FILENAME);
            $y = substr($name, 0, 4);
            $m = substr($name, 4, 2);

            if (!isset($list[$y])) {
                $list[$y] = [];
            }

            $list[$y][] = [
                'text' => date('F Y', mktime(12, 0, 0, intval($m), 1, intval($y))),
                'year' => $y,
                'month' => $m,
            ];
        }

        $data = [
            'year'  => $year,
            'month' => $month,
            'logs'  => (new Log(intval($year), intval($month)))->read(),
            'list'  => $list,
        ];

        return new HtmlResponse($this->template->render('app::logs', $data));
    }
}
