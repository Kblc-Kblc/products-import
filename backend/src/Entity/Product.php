<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $images;

    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        private string $externalCode,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private string $price,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description = null,
        // (price - purchase price) / price * 100; null when purchase price is unknown
        #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
        private ?string $discount = null,
    ) {
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): void
    {
        $this->discount = $discount;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(string $key, ?string $value): void
    {
        $this->attributes->add(new ProductAttribute($this, $key, $value));
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(string $url, ?string $path): void
    {
        $this->images->add(new ProductImage($this, $url, $path));
    }
}
