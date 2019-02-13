<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

/*
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/:id', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Zend\Expressive\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $loadAuthenticationMiddleware = function ($middleware) use ($container) {
        if (isset($container->get('config')['authentication']['pdo'])) {
            return [
                Zend\Expressive\Authentication\AuthenticationMiddleware::class,
                $middleware,
            ];
        }

        return $middleware;
    };

    $app->get('/', $loadAuthenticationMiddleware(App\Handler\HomePageHandler::class), 'home');
    $app->get('/download/{path:.+}', $loadAuthenticationMiddleware(App\Handler\DownloadHandler::class), 'download');
    $app->get('/logs[/{year:[0-9]{4}}/{month:[0-9]{2}}]', $loadAuthenticationMiddleware(App\Handler\LogsHandler::class), 'logs');
    $app->get('/scan/{path:.+}', $loadAuthenticationMiddleware(App\Handler\ScanHandler::class), 'scan');
    $app->get('/view/{path:.+}', $loadAuthenticationMiddleware(App\Handler\DownloadHandler::class), 'view');

    $app->get('/api/ping', App\Handler\API\PingHandler::class, 'api.ping');
    $app->route('/api/file', $loadAuthenticationMiddleware(App\Handler\API\FileHandler::class), ['DELETE', 'PUT'], 'api.file');
    $app->route('/api/directory', $loadAuthenticationMiddleware(App\Handler\API\DirectoryHandler::class), ['POST'], 'api.directory');
    $app->route('/api/upload', $loadAuthenticationMiddleware(App\Handler\API\UploadHandler::class), ['GET', 'POST'], 'api.upload');

    $app->route('/login', [
        App\Handler\LoginHandler::class,
        Zend\Expressive\Authentication\AuthenticationMiddleware::class,
    ], ['GET', 'POST'], 'login');
    $app->get('/logout', App\Handler\LoginHandler::class, 'logout');
};
