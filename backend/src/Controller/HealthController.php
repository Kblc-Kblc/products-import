<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $checks = [
            'db' => $this->check(fn() => new \PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME')),
                (string) getenv('DB_USER'),
                (string) getenv('DB_PASSWORD'),
                [\PDO::ATTR_TIMEOUT => 2],
            )),
            'rabbitmq' => $this->check(function (): void {
                $dsn = parse_url((string) getenv('MESSENGER_TRANSPORT_DSN'));
                $connection = new \AMQPConnection([
                    'host' => $dsn['host'] ?? 'localhost',
                    'port' => $dsn['port'] ?? 5672,
                    'login' => urldecode($dsn['user'] ?? 'guest'),
                    'password' => urldecode($dsn['pass'] ?? 'guest'),
                    'connect_timeout' => 2,
                ]);
                $connection->connect();
                $connection->disconnect();
            }),
        ];
        $healthy = !in_array(false, $checks, true);

        return Json::write($response, [
            'status' => $healthy ? 'ok' : 'fail',
            'checks' => array_map(fn(bool $ok) => $ok ? 'ok' : 'fail', $checks),
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
