<?php

namespace App\DTOs\Messaging;

/**
 * One product match returned by ProductLookupService.
 *
 * Flat shape because Claude tool-use needs JSON-serialisable input
 * and Meta Send API generic-template elements need a similar flat shape.
 */
final class ProductMatchDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $url,
        public readonly float $price,
        public readonly ?float $mrp,
        public readonly ?string $primaryImageUrl,
        public readonly bool $inStock,
        public readonly array $inStockSizes = [],
        public readonly ?string $shortDescription = null,
    ) {
    }

    public function discountPercentage(): int
    {
        if (!$this->mrp || $this->mrp <= 0 || $this->price >= $this->mrp) {
            return 0;
        }
        return (int) round((($this->mrp - $this->price) / $this->mrp) * 100);
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'url'                => $this->url,
            'price'              => $this->price,
            'mrp'                => $this->mrp,
            'primary_image_url'  => $this->primaryImageUrl,
            'in_stock'           => $this->inStock,
            'in_stock_sizes'     => $this->inStockSizes,
            'short_description'  => $this->shortDescription,
            'discount_percent'   => $this->discountPercentage(),
        ];
    }
}
