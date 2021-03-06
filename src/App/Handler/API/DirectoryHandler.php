<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Log;
use App\Middleware\AclMiddleware;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Log\Logger;
use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DirectoryHandler implements RequestHandlerInterface
{
    /** @var string */
    private $directory;

    /** @var array */
    private $user;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $method = $request->getMethod();
        $params = (array) $request->getParsedBody();

        if (!$session->has(UserInterface::class)) {
            return (new EmptyResponse())->withStatus(401);
        }

        $this->user = $session->get(UserInterface::class);

        if (isset($params['directory']) && file_exists('data/'.html_entity_decode($params['directory']))) {
            $this->directory = html_entity_decode($params['directory']);

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

                    if (isset($params['new'])) {
                        return $this->create($params['new'], $request);
                    }
            }
        }

        return (new EmptyResponse())->withStatus(400);
    }

    private function create(string $name, ServerRequestInterface $request): JsonResponse
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
            new Log('Directory "{directory}" created.', $log, Logger::NOTICE, $request);
        } else {
            new Log('Directory "{directory}" failed to be created.', $log, Logger::ERR, $request);
        }

        return new JsonResponse($data);
    }
}
