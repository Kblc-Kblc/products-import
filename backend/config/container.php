<?php

declare(strict_types=1);

use App\Auth\JwtService;
use App\Controller\AuthController;
use App\Import\ImageDownloader;
use App\Import\ImportService;
use App\Message\ImportProductsHandler;
use App\Message\ImportProductsMessage;
use DI\ContainerBuilder;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function DI\autowire;
use function DI\get;

require __DIR__ . '/../vendor/autoload.php';

return (new ContainerBuilder())
    ->addDefinitions([
        'storage.dir' => __DIR__ . '/../storage',

        ResponseFactoryInterface::class => autowire(ResponseFactory::class),
        JwtService::class => fn() => new JwtService((string) getenv('JWT_SECRET')),
        AuthController::class => autowire()
            ->constructorParameter('adminEmail', (string) getenv('ADMIN_EMAIL'))
            ->constructorParameter('adminPassword', (string) getenv('ADMIN_PASSWORD')),

        EntityManagerInterface::class => function (): EntityManagerInterface {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths: [__DIR__ . '/../src/Entity'],
                isDevMode: getenv('APP_DEBUG') === '1',
            );
            $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
            $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));

            $connection = DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => getenv('DB_HOST') ?: 'db',
                'port' => (int) (getenv('DB_PORT') ?: 5432),
                'dbname' => (string) getenv('DB_NAME'),
                'user' => (string) getenv('DB_USER'),
                'password' => (string) getenv('DB_PASSWORD'),
            ], $config);

            return new EntityManager($connection, $config);
        },

        // Image URLs come from user files: block requests to internal hosts (db, rabbitmq, metadata, ...)
        ImageDownloader::class => fn(ContainerInterface $c) => new ImageDownloader(
            new NoPrivateNetworkHttpClient(HttpClient::create()),
            $c->get('storage.dir'),
        ),
        ImportService::class => autowire()->constructorParameter('storageDir', get('storage.dir')),

        // Messenger: the "async" transport is RabbitMQ; the same bus sends (web) and handles (worker)
        'async' => fn() => (new AmqpTransportFactory())->createTransport(
            (string) getenv('MESSENGER_TRANSPORT_DSN'),
            [],
            new PhpSerializer(),
        ),
        MessageBusInterface::class => fn(ContainerInterface $c) => new MessageBus([
            new SendMessageMiddleware(new SendersLocator([ImportProductsMessage::class => ['async']], $c)),
            new HandleMessageMiddleware(new HandlersLocator([
                ImportProductsMessage::class => [
                    fn(ImportProductsMessage $m) => $c->get(ImportProductsHandler::class)($m),
                ],
            ])),
        ]),
    ])
    ->build();
