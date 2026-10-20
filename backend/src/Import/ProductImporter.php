<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Product;
use App\Repository\ProductRepository;

final class ProductImporter
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ImageDownloader $images,
    ) {}

    /**
     * Upserts the product by external code, replacing its attributes and images.
     *
     * @param callable(string): void $warn receives non-fatal problems (e.g. an image that failed to download)
     *
     * @return bool true if the product was created, false if updated
     */
    public function import(ProductRow $row, callable $warn): bool
    {
        // Network first, so the DB transaction stays short
        $paths = [];
        foreach ($row->imageUrls as $url) {
            try {
                $paths[$url] = $this->images->download($url);
            } catch (\RuntimeException $e) {
                $warn($e->getMessage());
                $paths[$url] = null;
            }
        }

        $product = $this->products->findByExternalCode($row->externalCode);
        $created = $product === null;
        $product ??= new Product($row->externalCode, $row->name, $row->price);

        $product->setName($row->name);
        $product->setDescription($row->description);
        $product->setPrice($row->price);
        $product->setDiscount($row->discount);

        $product->getAttributes()->clear();
        foreach ($row->attributes as $key => $value) {
            $product->addAttribute($key, $value);
        }
        $product->getImages()->clear();
        foreach ($paths as $url => $path) {
            $product->addImage($url, $path);
        }

        $this->products->save($product);

        return $created;
    }
}
