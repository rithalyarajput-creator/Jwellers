<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shiprocket Checkout catalog endpoints.
 * Mimics the Shopify-shaped JSON shape Shiprocket expects.
 *
 *  GET  /sr/catalog/products?page=1&limit=250
 *  GET  /sr/catalog/categories
 *  GET  /sr/catalog/categories/products?collection_id=1&page=1&limit=250
 */
class ShiprocketCatalogController extends Controller
{
    private const MAX_LIMIT = 250;

    public function products(Request $request): JsonResponse
    {
        [$page, $limit] = $this->paging($request);

        $query = Product::query()
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with(['brand:id,name', 'category:id,name', 'images', 'tags:id,name']);

        $total = (clone $query)->count();

        $products = $query
            ->orderBy('id')
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'data' => [
                'total'    => $total,
                'products' => $products->map(fn ($p) => $this->mapProduct($p))->values(),
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        // Count active+approved products per category id
        $counts = \DB::table('products')
            ->select('category_id', \DB::raw('COUNT(*) as c'))
            ->where('is_active', true)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        return response()->json([
            'data' => [
                'total'       => $categories->count(),
                'collections' => $categories->map(fn ($c) => [
                    'id'             => $c->id,
                    'title'          => $c->name,
                    'handle'         => $c->slug,
                    'description'    => (string) ($c->description ?? ''),
                    'image'          => ['src' => $this->resolveImage($c->image_url)],
                    'products_count' => (int) ($counts[$c->id] ?? 0),
                ])->values(),
            ],
        ]);
    }

    public function categoryProducts(Request $request): JsonResponse
    {
        $collectionId = (int) $request->query('collection_id', 0);
        if ($collectionId <= 0) {
            return response()->json(['data' => ['total' => 0, 'products' => []]]);
        }

        $category = Category::find($collectionId);
        if (! $category) {
            return response()->json(['data' => ['total' => 0, 'products' => []]]);
        }

        // Include descendants so a parent category returns products from its
        // sub-categories too (matches expected Shopify "collection" semantics).
        $categoryIds = $category->getAllDescendantIds();

        [$page, $limit] = $this->paging($request);

        $query = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with(['brand:id,name', 'category:id,name', 'images', 'tags:id,name']);

        $total = (clone $query)->count();

        $products = $query
            ->orderBy('id')
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'data' => [
                'total'    => $total,
                'products' => $products->map(fn ($p) => $this->mapProduct($p))->values(),
            ],
        ]);
    }

    private function paging(Request $request): array
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        return [$page, $limit];
    }

    private function mapProduct(Product $p): array
    {
        $primary = $p->images->firstWhere('is_primary', true) ?? $p->images->first();
        $imageSrc = $primary ? $this->resolveImage($primary->url) : null;

        $weightKg = (float) ($p->weight ?? 0);
        $unit = strtolower((string) ($p->weight_unit ?? 'kg'));
        $grams = $this->toGrams($weightKg, $unit);

        $tagsCsv = $p->tags->pluck('name')->filter()->implode(', ');

        $price = number_format((float) $p->price, 2, '.', '');
        $compareAt = ($p->mrp && (float) $p->mrp > (float) $p->price)
            ? number_format((float) $p->mrp, 2, '.', '')
            : null;

        $createdAt = optional($p->created_at)->toIso8601String();
        $updatedAt = optional($p->updated_at)->toIso8601String();

        // No variant rows in the DB — synthesise a single default variant
        // using product-level price/sku/stock so Shiprocket can render a buy box.
        $variant = [
            'id'               => $p->id,
            'title'            => 'Default Title',
            'price'            => $price,
            'compare_at_price' => $compareAt,
            'sku'              => (string) ($p->sku ?? ''),
            'quantity'         => (int) ($p->stock_quantity ?? 0),
            'created_at'       => $createdAt,
            'updated_at'       => $updatedAt,
            'taxable'          => (bool) $p->is_taxable,
            'option_values'    => (object) [],
            'grams'            => $grams,
            'image'            => ['src' => $imageSrc],
            'weight'           => (float) $weightKg,
            'weight_unit'      => $this->normaliseUnit($unit),
        ];

        return [
            'id'         => $p->id,
            'title'      => (string) $p->name,
            'body_html'  => (string) ($p->description ?? ''),
            'vendor'     => $p->brand?->name ?? 'ForeverKids',
            'product_type' => $p->category?->name ?? '',
            'created_at' => $createdAt,
            'handle'     => (string) $p->slug,
            'updated_at' => $updatedAt,
            'tags'       => $tagsCsv,
            'status'     => $p->is_active ? 'active' : 'draft',
            'variants'   => [$variant],
            'image'      => ['src' => $imageSrc],
            'options'    => [],
        ];
    }

    private function resolveImage(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return rtrim(config('app.url'), '/') . $path;
        }
        return asset('storage/' . $path);
    }

    private function toGrams(float $weight, string $unit): int
    {
        return match ($unit) {
            'g', 'gram', 'grams' => (int) round($weight),
            'lb', 'lbs', 'pound', 'pounds' => (int) round($weight * 453.592),
            'oz', 'ounce', 'ounces' => (int) round($weight * 28.3495),
            default => (int) round($weight * 1000), // kg → g (default)
        };
    }

    private function normaliseUnit(string $unit): string
    {
        return match ($unit) {
            'g', 'gram', 'grams' => 'g',
            'lb', 'lbs', 'pound', 'pounds' => 'lb',
            'oz', 'ounce', 'ounces' => 'oz',
            default => 'kg',
        };
    }
}
