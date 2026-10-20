<?php

declare(strict_types=1);

$container = require __DIR__ . '/../config/container.php';
(require __DIR__ . '/../config/app.php')($container)->run();
