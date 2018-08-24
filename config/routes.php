<?php

declare (strict_types = 1);

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

/**
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
    $app->get('/app/document-manager[/]', App\Handler\HomePageHandler::class, 'home');
    $app->get('/app/document-manager/scan/{path:.+}', App\Handler\ScanHandler::class, 'scan');
    $app->get('/app/document-manager/download/{path:.+}', App\Handler\DownloadHandler::class, 'download');
    $app->get('/app/document-manager/view/{path:.+}', App\Handler\DownloadHandler::class, 'view');

    $app->route('/app/document-manager/login', [
        App\Handler\LoginHandler::class,
        Zend\Expressive\Authentication\AuthenticationMiddleware::class,
    ], ['GET', 'POST'], 'login');
    $app->get('/app/document-manager/logout', App\Handler\LoginHandler::class, 'logout');

    //$app->get('/api/ping', App\Handler\PingHandler::class, 'api.ping');
};
