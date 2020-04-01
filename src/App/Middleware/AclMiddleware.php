<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionMiddleware;
use Laminas\Permissions\Acl\Acl;

class AclMiddleware implements MiddlewareInterface
{
    public const ACL_ATTRIBUTE = 'acl';

    public const PERM_READ = 'read';
    public const PERM_WRITE = 'write';
    public const PERM_DELETE = 'delete';
    public const PERM_RENAME = 'rename';
    public const PERM_DESCRIPTION = 'description';
    public const PERM_DIRECTORY_CREATE = 'createDirectory';

    /**
     * @var Acl
     */
    private $acl;

    public function __construct(Acl $acl)
    {
        $this->acl = $acl;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        if ($session->has(UserInterface::class)) {
            $user = $session->get(UserInterface::class);

            $request = $request->withAttribute(
                UserInterface::class,
                $user
            );
        }

        return $handler->handle($request->withAttribute(self::ACL_ATTRIBUTE, $this->acl));
    }
}
