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
