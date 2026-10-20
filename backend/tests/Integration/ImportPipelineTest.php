<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ImportJob;
use App\Import\ImageDownloader;
use App\Message\ImportProductsHandler;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Tests\IntegrationTestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Worker side of the import: xlsx => products, attributes, images, error report, upsert. */
final class ImportPipelineTest extends IntegrationTestCase
{
    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

    protected function setUp(): void
    {
        parent::setUp();
        $http = new MockHttpClient(fn(string $method, string $url) => str_contains($url, 'missing')
            ? new MockResponse('', ['http_code' => 404])
            : new MockResponse(self::JPEG, ['response_headers' => ['content-type' => 'binary/octet-stream']]));
        $this->container->set(ImageDownloader::class, new ImageDownloader($http, $this->storageDir));
    }

    public function testImportsValidRowsAndReportsInvalidOnes(): void
    {
        $job = $this->runImport([
            ['A-1', 'Товар один', '1 320,00', '880,00', 'Синий', 'http://img/1.jpg, http://img/missing.jpg'],
            ['', 'Без кода', '100', '', '', ''],
            ['A-2', 'Товар два', 'дорого', '', '', ''],
            ['A-3', 'Товар три', '500', '', 'Красный', ''],
        ]);

        $data = $job->toArray();
        self::assertSame(ImportJob::STATUS_DONE, $data['status']);
        self::assertSame(4, $data['total']);
        self::assertSame(4, $data['processed']);
        self::assertSame(2, $data['created']);
        self::assertSame(
            [2, 3, 4],
            array_column($data['errors'], 'row'),
            'the 404 image of row 2, empty code (row 3) and bad price (row 4) are reported in file order',
        );

        $product = $this->service(ProductRepository::class)->findByExternalCode('A-1');
        self::assertNotNull($product);
        self::assertSame('1320.00', $product->getPrice());
        self::assertSame('33.33', $product->getDiscount());
        $attribute = $product->getAttributes()->first();
        self::assertNotFalse($attribute);
        self::assertSame(['Цвет', 'Синий'], [$attribute->getKey(), $attribute->getValue()]);
        $paths = $product->getImages()->map(fn($i) => $i->getPath())->getValues();
        self::assertCount(2, $paths);
        self::assertMatchesRegularExpression('#^images/[0-9a-f]{40}\.jpg$#', (string) $paths[0]);
        self::assertFileExists($this->storageDir . '/' . $paths[0]);
        self::assertNull($paths[1], 'failed download keeps the url with an empty path');
    }

    public function testReimportUpdatesInsteadOfDuplicating(): void
    {
        $this->runImport([['A-1', 'Старое имя', '100', '', 'Синий', '']]);
        $this->em->clear();
        $job = $this->runImport([['A-1', 'Новое имя', '200', '150', 'Зелёный', '']]);

        self::assertSame(0, $job->toArray()['created']);
        self::assertSame(1, $job->toArray()['updated']);
        self::assertSame(1, $this->rowCount('products'));
        self::assertSame(1, $this->rowCount('product_attributes'));
        $product = $this->service(ProductRepository::class)->findByExternalCode('A-1');
        self::assertNotNull($product);
        self::assertSame('Новое имя', $product->getName());
        self::assertSame('25.00', $product->getDiscount());
    }

    public function testFileWithoutRequiredColumnsFailsTheJob(): void
    {
        $path = $this->writeXlsx([['Что-то', 'Другое'], ['1', '2']]);
        $job = $this->handle($path);

        self::assertSame(ImportJob::STATUS_FAILED, $job->toArray()['status']);
        self::assertStringContainsString('Внешний код', $job->toArray()['errors'][0]['message']);
    }

    /** @param list<list<string>> $rows code, name, price, purchase price, color, photos */
    private function runImport(array $rows): ImportJob
    {
        $header = ['Внешний код', 'Наименование', 'Цена: Цена продажи', 'Закупочная цена', 'Доп. поле: Цвет', 'Доп. поле: Ссылки на фото'];

        return $this->handle($this->writeXlsx([$header, ...$rows]));
    }

    private function handle(string $path): ImportJob
    {
        $jobs = $this->service(ImportJobRepository::class);
        $job = new ImportJob('test.xlsx');
        $jobs->save($job);
        $this->service(ImportProductsHandler::class)(new ImportProductsMessage((int) $job->getId(), $path));

        $this->em->clear();
        $reloaded = $jobs->find((int) $job->getId());
        self::assertNotNull($reloaded);
        self::assertFileDoesNotExist($path, 'the uploaded file is removed after processing');

        return $reloaded;
    }

    /** @param list<list<string>> $rows */
    private function writeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = $this->storageDir . '/upload.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
