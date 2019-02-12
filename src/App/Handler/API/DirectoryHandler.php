<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Log;
use App\Middleware\AclMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Log\Logger;

class DirectoryHandler implements RequestHandlerInterface
{
    /** @var string */
    private $directory;

    /** @var UserInterface */
    private $user;

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $method = $request->getMethod();
        $params = $request->getParsedBody();

        if (!$session->has(UserInterface::class)) {
            return (new EmptyResponse())->withStatus(401);
        }

        $this->user = $session->get(UserInterface::class);

        if (isset($params['directory']) && file_exists('data/'.$params['directory'])) {
            $this->directory = $params['directory'];

            $pathExploded = explode('/', $this->directory);

            $access = true;

            if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                $access = $acl->isAllowed($this->user['username'], 'directory.public', AclMiddleware::PERM_READ);
            } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                $access = $acl->isAllowed($this->user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_READ);
            } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                $access = $acl->isAllowed($this->user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_READ);
            }

            if ($access !== true) {
                return (new EmptyResponse())->withStatus(403);
            }

            $permission = false;

            switch ($method) {
                case 'POST':
                    if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.public', AclMiddleware::PERM_DIRECTORY_CREATE);
                    } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_DIRECTORY_CREATE);
                    } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_DIRECTORY_CREATE);
                    }

                    if ($permission !== true) {
                        return (new EmptyResponse())->withStatus(403);
                    }

                    return $this->create($params['new']);
            }
        }

        return (new EmptyResponse())->withStatus(400);
    }

    private function create(string $name) : JsonResponse
    {
        $pathExploded = explode('/', $this->directory);

        $data = [
            'path'    => 'data/'.$this->directory.'/'.$name,
            'created' => mkdir('data/'.$this->directory.'/'.$name),
        ];

        $log = [
            'directory' => $data['path'],
            'username'  => $this->user['username'],
        ];

        if ($data['created'] === true) {
            (new Log())->write('Directory "{directory}" created.', $log, Logger::NOTICE);
        } else {
            (new Log())->write('Directory "{directory}" failed to be created.', $log, Logger::ERR);
        }

        return new JsonResponse($data);
    }
}
