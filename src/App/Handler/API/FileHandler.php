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

        if (isset($params['path']) && file_exists('data/'.$params['path'])) {
            $pathExploded = explode('/', $params['path']);

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
                case 'DELETE':
                    if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.public', AclMiddleware::PERM_DELETE);
                    } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
                    } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_DELETE);
                    }

                    if ($permission !== true) {
                        return (new EmptyResponse())->withStatus(403);
                    }

                    return $this->delete('data/'.$params['path']);
                    break;
            }
        }

        return (new EmptyResponse())->withStatus(400);
    }

    private function delete(string $path) : JsonResponse
    {
        $document = new Document($path);

        $data = [
            'path'      => $document->getPathname(),
            'readable'  => $document->isReadable(),
            'writable'  => $document->isWritable(),
            'removable' => $document->isRemovable(),
            'deleted'   => @unlink($path),
        ];

        $log = [
            'file'     => $data['path'],
            'username' => $this->user['username'],
        ];

        if ($data['deleted'] === true) {
            (new Log())->write('File "{file}" deleted.', $log, Logger::WARN);
        } else {
            (new Log())->write('File "{file}" failed to be deleted.', $log, Logger::ERR);
        }

        return new JsonResponse($data);
    }
}
