<?php

namespace App\Services\Nia;

use App\DTOs\Messaging\ProductMatchDTO;
use App\Models\Product;

/**
 * Search the live products catalog for matches against an extracted intent
 * (or a flat filters array passed by Claude tool-use).
 *
 * Reuses the Product::active() and Product::inStock() Eloquent scopes so
 * Nia never recommends archived or out-of-stock items.
 */
class ProductLookupService
{
    private const DEFAULT_LIMIT = 5;
    private const MAX_LIMIT = 10;

    /**
     * @param array $filters See IntentDTO::filters shape. Plus optional 'limit'
     *                       and free-text 'query'.
     * @return ProductMatchDTO[]
     */
    public function search(array $filters): array
    {
        $limit = min((int) ($filters['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);
        $inStockOnly = (bool) ($filters['in_stock_only'] ?? true);

        $query = Product::active()->with(['images' => function ($q) {
            $q->orderByDesc('is_primary')->orderBy('position');
        }]);

        if ($inStockOnly) {
            $query->inStock();
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        $this->applyTextFilters($query, $filters);

        // Prefer featured + better rated + better selling so Nia leads with strength.
        $query->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderByDesc('sales_count')
            ->limit($limit);

        return $query->get()->map(fn (Product $p) => $this->toDto($p))->all();
    }

    private function applyTextFilters($query, array $filters): void
    {
        $terms = [];

        foreach (['category_keywords'] as $key) {
            if (!empty($filters[$key]) && is_array($filters[$key])) {
                foreach ($filters[$key] as $kw) {
                    if (is_string($kw) && trim($kw) !== '') {
                        $terms[] = trim($kw);
                    }
                }
            }
        }

        foreach (['color', 'occasion', 'gender', 'age_band', 'query'] as $key) {
            if (!empty($filters[$key]) && is_string($filters[$key])) {
                $terms[] = trim($filters[$key]);
            }
        }

        if (empty($terms)) {
            return;
        }

        $query->where(function ($outer) use ($terms) {
            foreach ($terms as $term) {
                $like = '%' . $term . '%';
                $outer->orWhere('name', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('description', 'like', $like);
            }
        });
    }

    private function toDto(Product $p): ProductMatchDTO
    {
        $primary = $p->images->firstWhere('is_primary', true) ?? $p->images->first();
        $imgUrl = $primary?->url
            ? (str_starts_with($primary->url, 'http') ? $primary->url : asset('storage/' . ltrim($primary->url, '/')))
            : null;

        return new ProductMatchDTO(
            id:               (int) $p->id,
            name:             (string) $p->name,
            slug:             (string) $p->slug,
            url:              url('/products/' . $p->slug),
            price:            (float) $p->price,
            mrp:              $p->mrp !== null ? (float) $p->mrp : null,
            primaryImageUrl:  $imgUrl,
            inStock:          $p->stock_status === 'in_stock' && $p->stock_quantity > 0,
            inStockSizes:     [],
            shortDescription: $p->short_description ? mb_substr((string) $p->short_description, 0, 140) : null,
        );
    }
}
