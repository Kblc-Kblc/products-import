<?php

declare(strict_types=1);

namespace App\Import;

/** Turns a raw spreadsheet row (header => cell) into a ProductRow or explains why it can't. */
final class ProductRowParser
{
    public const COL_EXTERNAL_CODE = 'Внешний код';
    public const COL_NAME = 'Наименование';
    public const COL_PRICE = 'Цена: Цена продажи';
    public const COL_DESCRIPTION = 'Описание';
    public const COL_PURCHASE_PRICE = 'Закупочная цена';
    public const ATTRIBUTE_PREFIX = 'Доп. поле:';
    public const IMAGE_COLUMNS = ['Доп. поле: Ссылка на упаковку', 'Доп. поле: Ссылки на фото'];

    public const REQUIRED_COLUMNS = [self::COL_EXTERNAL_CODE, self::COL_NAME, self::COL_PRICE];

    /**
     * @param array<string, mixed> $cells
     *
     * @throws InvalidRowException
     */
    public function parse(array $cells): ProductRow
    {
        $externalCode = $this->text($cells[self::COL_EXTERNAL_CODE] ?? null);
        $name = $this->text($cells[self::COL_NAME] ?? null);
        $errors = [];

        if ($externalCode === null) {
            $errors[] = 'не заполнен «' . self::COL_EXTERNAL_CODE . '»';
        } elseif (mb_strlen($externalCode) > 64) {
            $errors[] = '«' . self::COL_EXTERNAL_CODE . '» длиннее 64 символов';
        }
        if ($name === null) {
            $errors[] = 'не заполнено «' . self::COL_NAME . '»';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = '«' . self::COL_NAME . '» длиннее 255 символов';
        }

        $price = $this->money($cells[self::COL_PRICE] ?? null);
        if ($price === null) {
            $errors[] = 'некорректная «' . self::COL_PRICE . '»';
        }

        $purchase = $this->money($cells[self::COL_PURCHASE_PRICE] ?? null);
        $discount = null;
        if ($price !== null && $purchase !== null && $price > 0) {
            $discount = round(($price - $purchase) / $price * 100, 2);
            if (abs($discount) >= 1000) {
                $errors[] = 'закупочная цена несоразмерна цене продажи';
            }
        }

        if ($errors !== [] || $externalCode === null || $name === null || $price === null) {
            throw new InvalidRowException(implode('; ', $errors));
        }

        $attributes = [];
        foreach ($cells as $header => $value) {
            if (str_starts_with($header, self::ATTRIBUTE_PREFIX) && ($value = $this->text($value)) !== null) {
                $attributes[trim(mb_substr($header, mb_strlen(self::ATTRIBUTE_PREFIX)))] = $value;
            }
        }

        $imageUrls = [];
        foreach (self::IMAGE_COLUMNS as $column) {
            foreach (explode(',', (string) $this->text($cells[$column] ?? null)) as $url) {
                if (($url = trim($url)) !== '') {
                    $imageUrls[] = $url;
                }
            }
        }

        return new ProductRow(
            externalCode: $externalCode,
            name: $name,
            description: $this->text($cells[self::COL_DESCRIPTION] ?? null),
            price: number_format($price, 2, '.', ''),
            discount: $discount === null ? null : number_format($discount, 2, '.', ''),
            attributes: $attributes,
            imageUrls: array_values(array_unique($imageUrls)),
        );
    }

    private function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** "1 320,00" / "1320.5" / 1320 => 1320.0; null if empty, not a number or negative. */
    private function money(mixed $value): ?float
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value);

        return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }
}
