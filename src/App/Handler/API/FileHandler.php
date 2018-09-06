<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Log;
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
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $method = $request->getMethod();
        $params = $request->getParsedBody();

        if (isset($params['path']) && file_exists('data/'.$params['path'])) {
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
