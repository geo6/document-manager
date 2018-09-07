<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Log;
use App\Middleware\AclMiddleware;
use App\Model\Document;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Log\Logger;

class FileHandler implements RequestHandlerInterface
{
    private $authentication;

    public function __construct(bool $authentication)
    {
        $this->authentication = $authentication;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        if ($this->authentication !== false) {
            if (!$session->has(UserInterface::class)) {
                return (new EmptyResponse())->withStatus(401);
            }

            $user = $session->get(UserInterface::class);
            $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        }

        $method = $request->getMethod();
        $params = $request->getParsedBody();

        if (isset($params['path']) && file_exists('data/'.$params['path'])) {
            $pathExploded = explode('/', $params['path']);

            $access = true;
            if (isset($user, $acl)) {
                if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                    $access = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_READ);
                    if ($method === 'DELETE') {
                        $access = $access && $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_DELETE);
                    }
                } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                    $access = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_READ);
                    if ($method === 'DELETE') {
                        $access = $access && $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
                    }
                } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                    $access = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_READ);
                    if ($method === 'DELETE') {
                        $access = $access && $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
                    }
                }
            }

            if ($access !== true) {
                return (new EmptyResponse())->withStatus(403);
            }

            $document = new Document('data/'.$params['path']);

            $data = [
                'path'      => $document->getPathname(),
                'readable'  => $document->isReadable(),
                'writable'  => $document->isWritable(),
                'removable' => $document->isRemovable(),
            ];

            switch ($method) {
                case 'DELETE':
                    $data['deleted'] = @unlink('data/'.$params['path']);

                    $log = ['file' => $data['path']];

                    if ($session->has(UserInterface::class)) {
                        $user = $session->get(UserInterface::class);

                        $log['username'] = $user['username'];
                    }

                    if ($data['deleted'] === true) {
                        (new Log())->write('File "{file}" deleted.', $log, Logger::NOTICE);
                    } else {
                        (new Log())->write('File "{file}" failed to be deleted.', $log, Logger::ERR);
                    }

                    return new JsonResponse($data);
                    break;
            }
        }

        return (new EmptyResponse())->withStatus(400);
    }
}
