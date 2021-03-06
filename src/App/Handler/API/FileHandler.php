<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Log;
use App\Middleware\AclMiddleware;
use App\Model\Document;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Log\Logger;
use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FileHandler implements RequestHandlerInterface
{
    /** @var array */
    private $user;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $method = $request->getMethod();

        if (in_array($method, ['GET', 'HEAD'], true)) {
            $params = $request->getQueryParams();
        } else {
            $params = (array) $request->getParsedBody();
        }

        if (!$session->has(UserInterface::class)) {
            return (new EmptyResponse())->withStatus(401);
        }

        $this->user = $session->get(UserInterface::class);

        if (isset($params['path']) && file_exists('data/'.html_entity_decode($params['path']))) {
            $path = html_entity_decode($params['path']);
            $pathExploded = explode('/', $path);

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
                case 'GET':
                    $document = new Document('data/'.$path);

                    return new JsonResponse([
                        'name'        => $document->getBasename(),
                        'description' => $document->getInfo(),
                    ]);

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

                    return $this->delete('data/'.$path, $request);

                case 'PUT':
                    if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.public', AclMiddleware::PERM_RENAME);
                    } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_RENAME);
                    } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
                        $permission = $acl->isAllowed($this->user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_RENAME);
                    }

                    if ($permission !== true) {
                        return (new EmptyResponse())->withStatus(403);
                    }

                    if (isset($params['name'])) {
                        return $this->rename('data/'.$path, $params['name'], $request);
                    } elseif (isset($params['description'])) {
                        return $this->description('data/'.$path, $params['description'], $request);
                    }
                    break;
            }
        }

        return (new EmptyResponse())->withStatus(400);
    }

    private function delete(string $path, ServerRequestInterface $request): JsonResponse
    {
        $document = new Document($path);

        $data = [
            'path'      => $document->getPathname(),
            'readable'  => $document->isReadable(),
            'writable'  => $document->isWritable(),
            'deleted'   => @unlink($path),
        ];

        $log = [
            'file'     => $data['path'],
            'username' => $this->user['username'],
        ];

        if ($data['deleted'] === true) {
            new Log('File "{file}" deleted.', $log, Logger::WARN, $request);
        } else {
            new Log('File "{file}" failed to be deleted.', $log, Logger::ERR, $request);
        }

        return new JsonResponse($data);
    }

    private function rename(string $path, string $name, ServerRequestInterface $request): JsonResponse
    {
        $document = new Document($path);

        $data = [
            'path'      => $document->getPathname(),
            'readable'  => $document->isReadable(),
            'writable'  => $document->isWritable(),
            'renamed'   => rename($path, $document->getPath().'/'.$name),
        ];

        $log = [
            'file'     => $data['path'],
            'username' => $this->user['username'],
        ];

        if ($data['renamed'] === true) {
            new Log(sprintf('File "{file}" renamed into "%s".', $name), $log, Logger::WARN, $request);

            if (file_exists($data['path'].'.info')) {
                $description = rename($data['path'].'.info', $document->getPath().'/'.$name.'.info');

                if ($description === true) {
                    new Log(sprintf('File "{file}" description renamed into "%s".', $name.'.info'), $log, Logger::WARN, $request);
                } else {
                    new Log(sprintf('File "{file}" description failed to be renamed into "%s".', $name.'.info'), $log, Logger::ERR, $request);
                }
            }
        } else {
            new Log(sprintf('File "{file}" failed to be renamed into "%s".', $name), $log, Logger::ERR, $request);
        }

        return new JsonResponse($data);
    }

    private function description(string $path, string $description, ServerRequestInterface $request): JsonResponse
    {
        $document = new Document($path);

        $data = [
            'path'        => $document->getPathname(),
            'readable'    => $document->isReadable(),
            'writable'    => $document->isWritable(),
        ];

        $log = [
            'file'     => $data['path'],
            'username' => $this->user['username'],
        ];

        if (strlen($description) === 0) {
            $data['description'] = @unlink($path.'.info');

            if ($data['description'] === true) {
                new Log('File "{file}" description removed.', $log, Logger::WARN, $request);
            } else {
                new Log('File "{file}" description failed to be removed.', $log, Logger::ERR, $request);
            }
        } else {
            $data['description'] = (file_put_contents($path.'.info', rtrim($description, PHP_EOL).PHP_EOL) !== false);

            if ($data['description'] === true) {
                new Log('File "{file}" description edited.', $log, Logger::NOTICE, $request);
            } else {
                new Log('File "{file}" description failed to be edited.', $log, Logger::ERR, $request);
            }
        }

        return new JsonResponse($data);
    }
}
