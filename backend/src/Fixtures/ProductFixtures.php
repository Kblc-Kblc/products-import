<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Entity\Product;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Seeds products together with their attributes and images (all three tables). */
final class ProductFixtures implements FixtureInterface
{
    public const COUNT = 30;
    private const COLORS = ['Nero', 'Bianco', 'Grigio', 'Blu', 'Rosso'];
    private const SIZES = ['S', 'M', 'L', 'XL'];

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= self::COUNT; ++$i) {
            $price = 100 * $i;
            $product = new Product(
                externalCode: sprintf('FIXTURE-%03d', $i),
                name: sprintf('Тестовый товар %d, %s', $i, self::COLORS[$i % 5]),
                price: number_format($price, 2, '.', ''),
                description: "Описание тестового товара $i",
                // every 3rd product has no purchase price => no discount
                discount: $i % 3 === 0 ? null : '25.00',
            );
            $product->addAttribute('Цвет', self::COLORS[$i % 5]);
            $product->addAttribute('Размер', self::SIZES[$i % 4]);
            $product->addAttribute('Бренд', 'Fixture');
            $product->addImage("https://example.com/images/$i.jpg", null);
            $product->addImage("https://example.com/images/$i-back.jpg", null);
            $manager->persist($product);
        }
        $manager->flush();
    }
}
