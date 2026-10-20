<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Fixtures\ProductFixtures;
use App\Repository\ProductRepository;
use App\Tests\IntegrationTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;

/** CRUD for products with their attributes and images. */
final class ProductRepositoryTest extends IntegrationTestCase
{
    private ProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->products = $this->service(ProductRepository::class);
    }

    public function testCreateAndRead(): void
    {
        $product = new Product('EXT-1', 'Футболка', '1500.00', 'Хлопок', '20.00');
        $product->addAttribute('Цвет', 'Синий');
        $product->addAttribute('Размер', 'L');
        $product->addImage('https://example.com/1.jpg', 'images/1.jpg');
        $this->products->save($product);
        $this->em->clear();

        $found = $this->products->findByExternalCode('EXT-1');
        self::assertNotNull($found);
        self::assertSame('Футболка', $found->getName());
        self::assertSame('1500.00', $found->getPrice());
        self::assertSame('20.00', $found->getDiscount());
        self::assertSame(['Цвет' => 'Синий', 'Размер' => 'L'], $this->attributesOf($found));
        $image = $found->getImages()->first();
        self::assertNotFalse($image);
        self::assertSame('images/1.jpg', $image->getPath());
        self::assertSame($found->getId(), $this->products->find((int) $found->getId())?->getId());
    }

    public function testUpdateReplacesAttributesAndImages(): void
    {
        $product = new Product('EXT-1', 'Старое имя', '100.00');
        $product->addAttribute('Цвет', 'Красный');
        $product->addImage('https://example.com/old.jpg', null);
        $this->products->save($product);
        $this->em->clear();

        $product = $this->products->findByExternalCode('EXT-1');
        self::assertNotNull($product);
        $product->setName('Новое имя');
        $product->setPrice('200.00');
        $product->getAttributes()->clear();
        $product->addAttribute('Цвет', 'Зелёный');
        $product->getImages()->clear();
        $product->addImage('https://example.com/new.jpg', 'images/new.jpg');
        $this->products->save($product);
        $this->em->clear();

        $product = $this->products->findByExternalCode('EXT-1');
        self::assertNotNull($product);
        self::assertSame('Новое имя', $product->getName());
        self::assertSame('200.00', $product->getPrice());
        self::assertSame(['Цвет' => 'Зелёный'], $this->attributesOf($product));
        self::assertSame(1, $this->rowCount('product_attributes'), 'old attributes are removed, not orphaned');
        self::assertSame(1, $this->rowCount('product_images'));
        self::assertSame(1, $this->rowCount('products'));
    }

    public function testDeleteCascadesToAttributesAndImages(): void
    {
        $product = new Product('EXT-1', 'Удаляемый', '100.00');
        $product->addAttribute('Цвет', 'Красный');
        $product->addImage('https://example.com/1.jpg', null);
        $this->products->save($product);

        $this->products->delete($product);

        self::assertNull($this->products->findByExternalCode('EXT-1'));
        self::assertSame(0, $this->rowCount('product_attributes'));
        self::assertSame(0, $this->rowCount('product_images'));
    }

    public function testExternalCodeIsUnique(): void
    {
        $this->products->save(new Product('EXT-1', 'Первый', '100.00'));

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->products->save(new Product('EXT-1', 'Дубликат', '100.00'));
    }

    public function testFixturesSeedAllThreeTables(): void
    {
        $this->loadFixtures();

        self::assertSame(ProductFixtures::COUNT, $this->rowCount('products'));
        self::assertSame(ProductFixtures::COUNT * 3, $this->rowCount('product_attributes'));
        self::assertSame(ProductFixtures::COUNT * 2, $this->rowCount('product_images'));
    }

    public function testSearchPaginatesAndFilters(): void
    {
        $this->loadFixtures(); // prices 100, 200, ... 3000

        $page = $this->products->search(null, null, null, page: 2, limit: 10);
        self::assertSame(ProductFixtures::COUNT, $page['total']);
        self::assertSame('FIXTURE-011', $page['items'][0]->getExternalCode());
        self::assertCount(10, $page['items']);

        $byPrice = $this->products->search(null, '500', '800', 1, 50);
        self::assertSame(['500.00', '600.00', '700.00', '800.00'], array_map(fn(Product $p) => $p->getPrice(), $byPrice['items']));

        // case-insensitive, Cyrillic, and LIKE wildcards are literal
        self::assertSame(11, $this->products->search('ТЕСТОВЫЙ ТОВАР 1', null, null, 1, 50)['total']);
        self::assertSame(0, $this->products->search('%', null, null, 1, 50)['total']);
    }

    private function loadFixtures(): void
    {
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute([new ProductFixtures()]);
        $this->em->clear();
    }

    /** @return array<string, string|null> */
    private function attributesOf(Product $product): array
    {
        $result = [];
        foreach ($product->getAttributes() as $attribute) {
            $result[$attribute->getKey()] = $attribute->getValue();
        }

        return $result;
    }
}
