<?php

declare(strict_types=1);

namespace App\Tests;

use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/** Real PostgreSQL (the *_test database), RabbitMQ replaced by an in-memory transport. */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $schemaCreated = false;

    protected Container $container;
    protected EntityManagerInterface $em;
    protected InMemoryTransport $transport;
    protected string $storageDir;

    protected function setUp(): void
    {
        // A fresh container per test => a fresh EntityManager with an empty identity map
        $this->container = require __DIR__ . '/../config/container.php';
        $this->storageDir = sys_get_temp_dir() . '/products-test-' . bin2hex(random_bytes(4));
        mkdir($this->storageDir);
        $this->container->set('storage.dir', $this->storageDir);
        $this->container->set('async', $this->transport = new InMemoryTransport());
        $this->em = $this->container->get(EntityManagerInterface::class);

        if (!self::$schemaCreated) {
            $tool = new SchemaTool($this->em);
            $tool->dropDatabase();
            $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
            self::$schemaCreated = true;
        }
        $this->em->getConnection()->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs RESTART IDENTITY CASCADE',
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->storageDir));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function service(string $id): object
    {
        $service = $this->container->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    /** @return int number of rows */
    protected function rowCount(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM $table");
    }
}
