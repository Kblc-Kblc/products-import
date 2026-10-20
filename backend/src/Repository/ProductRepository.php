<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final class ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    /**
     * @param string|null $name     case-insensitive substring
     * @param string|null $minPrice inclusive, numeric string
     * @param string|null $maxPrice inclusive, numeric string
     *
     * @return array{items: list<Product>, total: int}
     */
    public function search(?string $name, ?string $minPrice, ?string $maxPrice, int $page, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()->select('p')->from(Product::class, 'p');
        if ($name !== null) {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . addcslashes(mb_strtolower($name), '%_\\') . '%');
        }
        if ($minPrice !== null) {
            $qb->andWhere('p.price >= :minPrice')->setParameter('minPrice', $minPrice);
        }
        if ($maxPrice !== null) {
            $qb->andWhere('p.price <= :maxPrice')->setParameter('maxPrice', $maxPrice);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Product> $items */
        $items = $qb->orderBy('p.id')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * First downloaded image of each product, in one query instead of one per product.
     *
     * @param list<int> $productIds
     *
     * @return array<int, string> product id => image path
     */
    public function firstImagePaths(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        /** @var list<array{productId: int, path: string}> $rows */
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(i.product) AS productId, i.path FROM App\Entity\ProductImage i
             WHERE i.product IN (:ids) AND i.path IS NOT NULL ORDER BY i.id',
        )->setParameter('ids', $productIds)->getArrayResult();

        $paths = [];
        foreach ($rows as $row) {
            $paths[(int) $row['productId']] ??= $row['path'];
        }

        return $paths;
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);
    }

    /** Saves the product with its attributes and images atomically. */
    public function save(Product $product): void
    {
        $this->em->wrapInTransaction(fn () => $this->em->persist($product));
    }
}
