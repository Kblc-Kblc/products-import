<?php

declare(strict_types=1);

namespace App\Import;

/** One validated, normalised product row from the import file. */
final readonly class ProductRow
{
    /**
     * @param array<string, string> $attributes "Доп. поле" columns, key => value
     * @param list<string>          $imageUrls
     */
    public function __construct(
        public string $externalCode,
        public string $name,
        public ?string $description,
        public string $price,
        public ?string $discount,
        public array $attributes,
        public array $imageUrls,
    ) {
    }
}
