<?php

declare(strict_types=1);

namespace App\Handler;

use App\Middleware\AclMiddleware;
use Blast\BaseUrl\BaseUrlMiddleware;
use Geo6\Laminas\Log\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Template\TemplateRendererInterface;
use Laminas\Permissions\Acl\AclInterface;
use Mezzio\Session\SessionMiddleware;

class LogsHandler implements RequestHandlerInterface
{
    /** @var TemplateRendererInterface */
    private $renderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $user = $session->get(UserInterface::class);

        if ($acl->isAllowed($user['username'], 'logs') !== true) {
            return (new HtmlResponse($this->template->render('error::error', [
                'status' => 403,
                'reason' => 'Forbidden',
            ])))->withStatus(403);
        }

        $year = $request->getAttribute('year');
        $month = $request->getAttribute('month');

        if (is_null($year) && is_null($month)) {
            $logs = glob('data/log/*.log');
            $last = end($logs);

            preg_match('/^([0-9]{4})([0-9]{2})\.log$/', basename($last), $matches);
            [, $year, $month] = $matches;
        }

        $path = sprintf('data/log/%s%s.log', $year, $month);
        $log = file_exists($path) && is_readable($path) ? Log::read($path) : null;

        $previous = self::getPrevious(intval($year), intval($month));
        while (!file_exists(self::getPath($previous['year'], $previous['month']))) {
            $previous = self::getPrevious($previous['year'], intval($previous['month']));

            if ($previous['year'] < 2019) {
                $previous = null;
                break;
            }
        }

        $next = self::getNext(intval($year), intval($month));
        while (!file_exists(self::getPath($next['year'], $next['month']))) {
            $next = self::getNext($next['year'], intval($next['month']));

            if ($next['year'] > intval(date('Y'))) {
                $next = null;
                break;
            }
        }

        return new HtmlResponse($this->renderer->render(
            'app::logs',
            [
                'title' => date('F Y', mktime(12, 0, 0, intval($month), 1, intval($year))),
                'log' => $log,
                'previous' => $previous,
                'next' => $next,
            ]
        ));
    }

    private static function getPrevious(int $year, int $month): array
    {
        if ($month === 1) {
            $month = 12;
            $year = $year - 1;
        } else {
            $month = $month - 1;
            $year = $year;
        }

        return ['year' => $year, 'month' => str_pad((string) $month, 2, '0', STR_PAD_LEFT)];
    }

    private static function getNext(int $year, int $month): array
    {
        if ($month === 12) {
            $month = 1;
            $year = $year + 1;
        } else {
            $month = $month + 1;
            $year = $year;
        }

        return ['year' => $year, 'month' => str_pad((string) $month, 2, '0', STR_PAD_LEFT)];
    }

    private static function getPath(int $year, string $month): string
    {
        return sprintf('data/log/%s%s.log', $year, $month);
    }
}
