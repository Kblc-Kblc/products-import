<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Fixtures\ProductFixtures;
use App\Message\ImportProductsMessage;
use App\Tests\IntegrationTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

/** HTTP API through the real Slim app (routing, auth middleware, controllers), without a web server. */
final class ApiTest extends IntegrationTestCase
{
    /** @var App<\Psr\Container\ContainerInterface|null> */
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = (require __DIR__ . '/../../config/app.php')($this->container);
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute([new ProductFixtures()]);
        $this->em->clear();
    }

    public function testLogin(): void
    {
        self::assertSame(401, $this->request('POST', '/api/auth/login', body: ['email' => 'admin@example.com', 'password' => 'nope'])->getStatusCode());

        $response = $this->request('POST', '/api/auth/login', body: ['email' => 'ADMIN@example.com', 'password' => 'secret']);
        self::assertSame(200, $response->getStatusCode());
        self::assertIsString($this->json($response)['token']);
    }

    public function testApiRequiresToken(): void
    {
        self::assertSame(401, $this->request('GET', '/api/products', token: null)->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/products', token: 'garbage')->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/imports/1', token: null)->getStatusCode());
    }

    public function testListPaginates(): void
    {
        $body = $this->json($this->request('GET', '/api/products?page=2&limit=10'));

        self::assertSame(2, $body['page']);
        self::assertSame(10, $body['limit']);
        self::assertSame(ProductFixtures::COUNT, $body['total']);
        self::assertSame(3, $body['totalPages']);
        self::assertCount(10, $body['items']);
        self::assertSame('FIXTURE-011', $body['items'][0]['externalCode']);
        self::assertSame(['id', 'externalCode', 'name', 'price', 'discount', 'image'], array_keys($body['items'][0]));
    }

    public function testListFiltersByNameAndPriceRange(): void
    {
        $body = $this->json($this->request('GET', '/api/products?name=bianco&price_min=500&price_max=1500'));

        // Bianco = every 5th product (i % 5 === 1): 6 and 11 fall into 500..1500
        self::assertSame(['600.00', '1100.00'], array_column($body['items'], 'price'));
    }

    public function testListRejectsInvalidParams(): void
    {
        $response = $this->request('GET', '/api/products?page=0&limit=1000&price_min=abc');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['page', 'limit', 'price_min'], array_keys($this->json($response)['fields']));
    }

    public function testShowReturnsProductWithAttributesAndImages(): void
    {
        $body = $this->json($this->request('GET', '/api/products/1'));

        self::assertSame('FIXTURE-001', $body['externalCode']);
        self::assertSame('25.00', $body['discount']);
        self::assertCount(3, $body['attributes']);
        self::assertSame(['key' => 'Бренд', 'value' => 'Fixture'], $body['attributes'][2]);
        self::assertSame(['url' => 'https://example.com/images/1.jpg', 'path' => null, 'src' => null], $body['images'][0]);

        self::assertSame(404, $this->request('GET', '/api/products/99999')->getStatusCode());
    }

    public function testImportQueuesValidFileAndExposesStatus(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['Внешний код', 'Наименование', 'Цена: Цена продажи'], ['N-1', 'Новый', '10']]);
        $path = $this->storageDir . '/upload.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->request('POST', '/api/imports', file: [$path, 'товары.xlsx']);

        self::assertSame(202, $response->getStatusCode());
        $job = $this->json($response);
        self::assertSame('queued', $job['status']);
        self::assertSame('/api/imports/' . $job['id'], $response->getHeaderLine('Location'));
        $sent = $this->transport->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(ImportProductsMessage::class, $sent[0]->getMessage());

        self::assertSame('queued', $this->json($this->request('GET', '/api/imports/' . $job['id']))['status']);
        self::assertSame(404, $this->request('GET', '/api/imports/99999')->getStatusCode());
    }

    public function testImportRejectsInvalidFiles(): void
    {
        $text = $this->storageDir . '/notes.txt';
        file_put_contents($text, 'hello');
        $fake = $this->storageDir . '/fake.xlsx';
        file_put_contents($fake, 'not a spreadsheet');

        foreach ([[null, 'не передан'], [[$text, 'notes.txt'], '.xlsx'], [[$fake, 'fake.xlsx'], 'не является']] as [$file, $message]) {
            $response = $this->request('POST', '/api/imports', file: $file);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString($message, $this->json($response)['error']);
        }
        self::assertCount(0, $this->transport->getSent());
    }

    /**
     * @param array<string, mixed>|null      $body
     * @param array{string, string}|null     $file local path + client filename, sent as the "file" field
     */
    private function request(string $method, string $uri, ?array $body = null, ?array $file = null, ?string $token = 'login'): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        }
        if ($file !== null) {
            $request = $request->withUploadedFiles(['file' => new UploadedFile($file[0], $file[1], null, (int) filesize($file[0]))]);
        }
        if ($token === 'login') {
            $token = $this->json($this->request('POST', '/api/auth/login', body: ['email' => 'admin@example.com', 'password' => 'secret'], token: null))['token'];
        }
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $this->app->handle($request);
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
