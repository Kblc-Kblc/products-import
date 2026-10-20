<?php

declare(strict_types=1);

use App\Auth\JwtAuthMiddleware;
use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

/** Builds the Slim app (routes + middleware); shared by public/index.php and the API tests. */
return function (ContainerInterface $container): App {
    AppFactory::setContainer($container);
    $app = AppFactory::create();
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(getenv('APP_DEBUG') === '1', true, true);

    $app->get('/api/health', HealthController::class);
    $app->post('/api/auth/login', [AuthController::class, 'login']);

    $app->group('/api', function (RouteCollectorProxy $api) {
        $api->post('/imports', [ImportController::class, 'create']);
        $api->get('/imports/{id:[0-9]+}', [ImportController::class, 'show']);
        $api->get('/products', [ProductController::class, 'list']);
        $api->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);
    })->add(JwtAuthMiddleware::class);

    return $app;
};
