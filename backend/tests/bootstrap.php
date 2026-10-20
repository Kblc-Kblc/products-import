<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dbName = (string) getenv('DB_NAME');
if (!str_ends_with($dbName, '_test')) {
    // Integration tests wipe the schema; refuse to touch anything but a *_test database
    fwrite(STDERR, "DB_NAME must end with _test, got \"$dbName\"\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=postgres', getenv('DB_HOST'), getenv('DB_PORT')),
    (string) getenv('DB_USER'),
    (string) getenv('DB_PASSWORD'),
);
if ($pdo->query('SELECT 1 FROM pg_database WHERE datname = ' . $pdo->quote($dbName))->fetchColumn() === false) {
    $pdo->exec('CREATE DATABASE "' . $dbName . '"');
}
