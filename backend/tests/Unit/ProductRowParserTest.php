<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Import\InvalidRowException;
use App\Import\ProductRowParser;
use PHPUnit\Framework\TestCase;

final class ProductRowParserTest extends TestCase
{
    private const VALID = [
        'Внешний код' => ' ABC-1 ',
        'Наименование' => 'Бермуды',
        'Цена: Цена продажи' => '1 320,00',
        'Закупочная цена' => '880,00',
        'Описание' => '',
        'Доп. поле: Цвет' => 'Grigio',
        'Доп. поле: Размер' => null,
        'Доп. поле: Ссылка на упаковку' => 'http://x/p.jpg',
        'Доп. поле: Ссылки на фото' => 'http://x/1.jpg, http://x/p.jpg,,',
    ];

    public function testParsesAndNormalisesRow(): void
    {
        $row = (new ProductRowParser())->parse(self::VALID);

        self::assertSame('ABC-1', $row->externalCode);
        self::assertSame('1320.00', $row->price);
        self::assertSame('33.33', $row->discount);
        self::assertNull($row->description);
        self::assertSame('Grigio', $row->attributes['Цвет']);
        self::assertArrayNotHasKey('Размер', $row->attributes);
        self::assertSame(['http://x/p.jpg', 'http://x/1.jpg'], $row->imageUrls);
    }

    public function testDiscountIsNullWithoutPurchasePrice(): void
    {
        $row = (new ProductRowParser())->parse(['Закупочная цена' => null] + self::VALID);

        self::assertNull($row->discount);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRows(): iterable
    {
        yield 'no code' => [['Внешний код' => ''] + self::VALID, 'Внешний код'];
        yield 'no name' => [['Наименование' => null] + self::VALID, 'Наименование'];
        yield 'bad price' => [['Цена: Цена продажи' => 'дорого'] + self::VALID, 'Цена продажи'];
        yield 'negative price' => [['Цена: Цена продажи' => '-5'] + self::VALID, 'Цена продажи'];
        yield 'long name' => [['Наименование' => str_repeat('я', 256)] + self::VALID, '255'];
    }

    /** @param array<string, mixed> $cells */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRows')]
    public function testRejectsInvalidRow(array $cells, string $expectedMessagePart): void
    {
        $this->expectException(InvalidRowException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        (new ProductRowParser())->parse($cells);
    }
}
