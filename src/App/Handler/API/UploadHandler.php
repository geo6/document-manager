<?php

declare(strict_types=1);

namespace App\Handler\API;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;

/**
 * @see https://github.com/23/resumable.js/blob/master/samples/Backend%20on%20PHP.md
 */
class UploadHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = array_merge(
            $request->getParsedBody(),
            $request->getQueryParams()
        );

        $method = $request->getMethod();

        $directory = $params['directory'];

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
                                for ($i = 1; $i <= $resumableTotalChunks; $i++) {
                                    $uploadedChunk = $tempDirectory.'/'.$resumableFilename.'.part.'.$i;

                                    if (file_exists($uploadedChunk) && is_readable($uploadedChunk)) {
                                        $content = file_get_contents($uploadedChunk);

                                        fwrite($handle, $content);

                                        @unlink($uploadedChunk);
                                    } else {
                                        throw new Exception(
                                            sprintf('Unable to open chunk #%d of file "%s".', $i, $resumableFilename)
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
