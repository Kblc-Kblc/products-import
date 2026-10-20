<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    private const MAX_LIMIT = 100;

    public function __construct(private readonly ProductRepository $products)
    {
    }

    /** GET /api/products?page=1&limit=20&name=&price_min=&price_max= */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $limit = filter_var($query['limit'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_LIMIT]]);
        $name = trim((string) ($query['name'] ?? ''));
        $minPrice = $this->price($query['price_min'] ?? null);
        $maxPrice = $this->price($query['price_max'] ?? null);

        if ($page === false || $limit === false || $minPrice === false || $maxPrice === false) {
            return Json::write($response, ['error' => 'Некорректные параметры', 'fields' => array_filter([
                'page' => $page === false ? 'целое число ≥ 1' : null,
                'limit' => $limit === false ? 'целое число от 1 до ' . self::MAX_LIMIT : null,
                'price_min' => $minPrice === false ? 'число ≥ 0' : null,
                'price_max' => $maxPrice === false ? 'число ≥ 0' : null,
            ])], 400);
        }

        $result = $this->products->search($name === '' ? null : $name, $minPrice, $maxPrice, $page, $limit);
        $images = $this->products->firstImagePaths(array_map(fn (Product $p) => (int) $p->getId(), $result['items']));

        return Json::write($response, [
            'items' => array_map(fn (Product $p) => [
                'id' => $p->getId(),
                'externalCode' => $p->getExternalCode(),
                'name' => $p->getName(),
                'price' => $p->getPrice(),
                'discount' => $p->getDiscount(),
                'image' => self::src($images[(int) $p->getId()] ?? null),
            ], $result['items']),
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
            'totalPages' => (int) ceil($result['total'] / $limit),
        ]);
    }

    /**
     * GET /api/products/{id}
     *
     * @param array{id: string} $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->products->find((int) $args['id']);
        if ($product === null) {
            return Json::write($response, ['error' => 'Товар не найден'], 404);
        }

        return Json::write($response, [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'discount' => $product->getDiscount(),
            'attributes' => $product->getAttributes()->map(fn (ProductAttribute $a) => [
                'key' => $a->getKey(),
                'value' => $a->getValue(),
            ])->getValues(),
            'images' => $product->getImages()->map(fn (ProductImage $i) => [
                'url' => $i->getUrl(),
                'path' => $i->getPath(),
                'src' => self::src($i->getPath()),
            ])->getValues(),
        ]);
    }

    /** @return string|false|null null when absent, false when invalid */
    private function price(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) && (float) $value >= 0 ? (string) $value : false;
    }

    /** Public URL of a downloaded image, served by nginx from the shared storage volume. */
    private static function src(?string $path): ?string
    {
        return $path === null ? null : '/storage/' . $path;
    }
}
