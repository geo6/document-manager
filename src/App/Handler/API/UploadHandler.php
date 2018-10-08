<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Log;
use App\Middleware\AclMiddleware;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Authentication\UserInterface;
use Zend\Expressive\Session\SessionMiddleware;
use Zend\Log\Logger;

/**
 * @see https://github.com/23/resumable.js/blob/master/samples/Backend%20on%20PHP.md
 */
class UploadHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $acl = $request->getAttribute(AclMiddleware::ACL_ATTRIBUTE);
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

        $params = array_merge(
            $request->getParsedBody(),
            $request->getQueryParams()
        );

        $method = $request->getMethod();

        if (!$session->has(UserInterface::class)) {
            return (new EmptyResponse())->withStatus(401);
        }

        $user = $session->get(UserInterface::class);

        $directory = $params['directory'];
        $pathExploded = explode('/', $directory);

        $access = true;
        if ($pathExploded[0] === 'public' && $acl->hasResource('directory.public')) {
            $access = $acl->isAllowed($user['username'], 'directory.public', AclMiddleware::PERM_WRITE);
        } elseif ($pathExploded[0] === 'roles' && isset($pathExploded[1]) && $acl->hasResource('directory.roles.'.$pathExploded[1])) {
            $access = $acl->isAllowed($user['username'], 'directory.roles.'.$pathExploded[1], AclMiddleware::PERM_WRITE);
        } elseif ($pathExploded[0] === 'users' && isset($pathExploded[1]) && $acl->hasResource('directory.users.'.$pathExploded[1])) {
            $access = $acl->isAllowed($user['username'], 'directory.users.'.$pathExploded[1], AclMiddleware::PERM_WRITE);
        }

        if ($access !== true) {
            return (new EmptyResponse())->withStatus(403);
        }

        $resumableIdentifier = $params['resumableIdentifier'] ?? '';
        $resumableFilename = $params['resumableFilename'] ?? '';
        $resumableChunkNumber = $params['resumableChunkNumber'] ?? 0;

        $tempDirectory = sys_get_temp_dir().'/'.$resumableIdentifier;
        if (!file_exists($tempDirectory) || !is_dir($tempDirectory)) {
            $mkdir = mkdir($tempDirectory);
        }

        $chunk = $tempDirectory.'/'.$resumableFilename.'.part.'.$resumableChunkNumber;

        switch ($method) {
            case 'GET':
                if (file_exists($chunk)) {
                    return (new EmptyResponse())->withStatus(200);
                } else {
                    return (new EmptyResponse())->withStatus(404);
                }
                break;

            case 'POST':
                $files = $request->getUploadedFiles();

                $data = [
                    'filename' => $resumableFilename,
                    'chunk'    => $resumableChunkNumber,
                ];

                try {
                    foreach ($files as $file) {
                        $file->moveTo($chunk);

                        $resumableTotalSize = $params['resumableTotalSize'] ?? 0;
                        $resumableTotalChunks = $params['resumableTotalChunks'] ?? 0;

                        $uploadedSize = 0;
                        $listChunks = glob($tempDirectory.'/*.part.*');
                        foreach ($listChunks as $uploadedChunk) {
                            $uploadedSize += filesize($uploadedChunk);
                        }

                        if ($uploadedSize >= $resumableTotalSize) {
                            $handle = fopen($tempDirectory.'/'.$resumableFilename, 'w');

                            if ($handle !== false) {
                                for ($c = 1; $c <= $resumableTotalChunks; $c++) {
                                    $uploadedChunk = $tempDirectory.'/'.$resumableFilename.'.part.'.$c;

                                    if (file_exists($uploadedChunk) && is_readable($uploadedChunk)) {
                                        $content = file_get_contents($uploadedChunk);

                                        fwrite($handle, $content);

                                        @unlink($uploadedChunk);
                                    } else {
                                        throw new Exception(
                                            sprintf('Unable to open chunk #%d of file "%s".', $c, $resumableFilename)
                                        );
                                    }
                                }

                                fclose($handle);

                                $i = 1;
                                $new = 'data/'.$directory.'/'.$resumableFilename;
                                $path = pathinfo($new);
                                while (file_exists($new)) {
                                    $new = $path['dirname'].'/'.$path['filename'].'.'.($i++).'.'.$path['extension'];
                                }

                                $rename = rename(
                                    $tempDirectory.'/'.$resumableFilename,
                                    $new
                                );

                                if ($rename === false) {
                                    throw new Exception(
                                        sprintf('Unable to move file to directory "%s".', $directory)
                                    );
                                }

                                $data['success'] = true;

                                rmdir($tempDirectory);

                                $log = ['file' => $new];

                                if ($session->has(UserInterface::class)) {
                                    $user = $session->get(UserInterface::class);

                                    $log['username'] = $user['username'];
                                }

                                (new Log())->write('File "{file}" uploaded.', $log, Logger::NOTICE);
                            } else {
                                throw new Exception(
                                    sprintf('Unable to write file "%s" in temporary folder.', $resumableFilename)
                                );
                            }
                        }
                    }

                    return new JsonResponse($data);
                } catch (Exception $e) {
                    $data['error'] = $e->getMessage();

                    return (new JsonResponse($data))->withStatus(500);
                }
                break;
        }

        return (new EmptyResponse())->withStatus(400);
    }
}
