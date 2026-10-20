<?php

declare(strict_types=1);

use App\Controller\HealthController;
use App\Controller\ImportController;
use Slim\Factory\AppFactory;

$container = require __DIR__ . '/../config/container.php';

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(getenv('APP_DEBUG') === '1', true, true);

$app->get('/api/health', HealthController::class);
$app->post('/api/imports', [ImportController::class, 'create']);
$app->get('/api/imports/{id:[0-9]+}', [ImportController::class, 'show']);

$app->run();
