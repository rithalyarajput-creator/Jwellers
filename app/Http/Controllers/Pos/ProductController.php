<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Paginated product list for the POS grid.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->with(['primaryImage', 'category:id,name', 'variants:id,product_id,name,sku,barcode,price,stock_quantity,attributes']);

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Stock filter (default: show all, but highlight OOS)
        if ($request->input('in_stock_only')) {
            $query->where('stock_quantity', '>', 0);
        }

        $products = $query->orderByAvailability()
            ->orderBy('name')
            ->paginate($request->input('per_page', 24));

        return response()->json([
            'products' => $products->getCollection()->map(fn ($p) => $this->formatProduct($p))->values(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    /**
     * Search products by name, SKU, or barcode.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%")
                  ->orWhereHas('variants', fn ($vq) => $vq->where('barcode', 'like', "%{$query}%")->orWhere('sku', 'like', "%{$query}%"));
            })
            ->with(['primaryImage', 'category:id,name', 'variants:id,product_id,name,sku,barcode,price,stock_quantity,attributes'])
            ->orderByAvailability()
            ->orderByDesc('sales_count')
            ->limit(20)
            ->get();

        $upper = strtoupper($query);

        return response()->json([
            'products' => $products->map(function ($p) use ($upper) {
                $arr = $this->formatProduct($p);
                // If the query is an exact match for a variant's barcode or SKU,
                // attach that variant to the response so the dropdown can show
                // "product · 8 / red" and Enter/click adds the right variant.
                $matched = $p->variants->first(function ($v) use ($upper) {
                    return strtoupper($v->barcode ?? '') === $upper
                        || strtoupper($v->sku ?? '') === $upper;
                });
                if ($matched) {
                    $attrs = is_array($matched->attributes) ? $matched->attributes
                        : (is_string($matched->attributes) ? (json_decode($matched->attributes, true) ?: []) : []);
                    $arr['matched_variant'] = [
                        'id'    => (int) $matched->id,
                        'name'  => $matched->name,
                        'sku'   => $matched->sku,
                        'price' => (float) ($matched->price ?? $p->price),
                        'stock' => (int) $matched->stock_quantity,
                        'size'  => $attrs['size'] ?? null,
                        'color' => isset($attrs['color']) ? ucfirst(strtolower($attrs['color'])) : null,
                    ];
                }
                return $arr;
            }),
        ]);
    }

    /**
     * Lookup product by barcode (USB scanner or camera).
     */
    public function barcodeLookup(Request $request, string $code): JsonResponse
    {
        // Validate barcode format: 4-128 alphanumeric characters (covers EAN-8/13, UPC-A/E, Code128, QR, etc.)
        $code = trim($code);
        if (! preg_match('/^[A-Za-z0-9\-\.\/\+\s]{4,128}$/', $code)) {
            return response()->json(['found' => false, 'message' => 'Invalid barcode format.'], 422);
        }

        // Normalise to uppercase for case-insensitive matching
        $codeUpper = strtoupper($code);

        // POS scan resolution: cashier is holding physical inventory in hand.
        // We intentionally do NOT filter by `is_active` here — even legacy or
        // soon-to-be-deactivated SKUs can be sold off the shelf. We DO keep
        // the `status='approved'` filter so anything explicitly rejected
        // (status='rejected') can't be sold by accident. Browse and search
        // (index/search methods above) keep the `is_active` filter — that's
        // a storefront concern, not a barcode-resolution concern.

        // 1) products.barcode (legacy single-barcode column on products)
        $product = Product::whereRaw('UPPER(barcode) = ?', [$codeUpper])
            ->where('status', 'approved')
            ->with(['primaryImage', 'category:id,name', 'variants:id,product_id,name,sku,barcode,price,stock_quantity,attributes'])
            ->first();

        if ($product) {
            return response()->json([
                'found'   => true,
                'product' => $this->formatProduct($product),
            ]);
        }

        // 2) product_variants.barcode
        $variant = ProductVariant::whereRaw('UPPER(barcode) = ?', [$codeUpper])
            ->with(['product' => fn ($q) => $q->where('status', 'approved')->with(['primaryImage', 'category:id,name'])])
            ->first();

        if ($variant && $variant->product) {
            return response()->json([
                'found'      => true,
                'product'    => $this->formatProduct($variant->product),
                'variant_id' => $variant->id,
            ]);
        }

        // 3) products.sku — so any SKU-encoded label works as a scan code
        $product = Product::whereRaw('UPPER(sku) = ?', [$codeUpper])
            ->where('status', 'approved')
            ->with(['primaryImage', 'category:id,name', 'variants:id,product_id,name,sku,barcode,price,stock_quantity,attributes'])
            ->first();

        if ($product) {
            return response()->json([
                'found'   => true,
                'product' => $this->formatProduct($product),
            ]);
        }

        // 4) product_variants.sku
        $variant = ProductVariant::whereRaw('UPPER(sku) = ?', [$codeUpper])
            ->with(['product' => fn ($q) => $q->where('status', 'approved')->with(['primaryImage', 'category:id,name'])])
            ->first();

        if ($variant && $variant->product) {
            return response()->json([
                'found'      => true,
                'product'    => $this->formatProduct($variant->product),
                'variant_id' => $variant->id,
            ]);
        }

        // 5) product_barcodes (MARG-style multi-barcode per product/variant — piece/inner/outer/carton)
        $pb = \App\Models\ProductBarcode::whereRaw('UPPER(barcode) = ?', [$codeUpper])->first();
        if ($pb) {
            $product = Product::where('id', $pb->product_id)
                ->where('status', 'approved')
                ->with(['primaryImage', 'category:id,name', 'variants:id,product_id,name,sku,barcode,price,stock_quantity,attributes'])
                ->first();

            if ($product) {
                return response()->json([
                    'found'      => true,
                    'product'    => $this->formatProduct($product),
                    'variant_id' => $pb->variant_id,
                    'pack_unit'  => $pb->pack_unit, // so cashier knows if scanning a carton vs. piece
                ]);
            }
        }

        // (Legacy `\App\Models\Barcode` fallback removed — class no longer
        // exists and was throwing fatal "Class not found" on lookups that
        // reached the fallback. The product_barcodes table above is the
        // canonical multi-barcode store.)

        return response()->json([
            'found'   => false,
            'message' => "No product found for barcode: {$code}",
        ], 404);
    }

    /**
     * Get active categories for the filter tabs.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id') // top-level only
            ->withCount(['products' => fn ($q) => $q->where('status', 'approved')->where('is_active', true)])
            ->orderBy('position')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'products_count' => $c->products_count,
            ]);

        return response()->json(['categories' => $categories]);
    }

    /**
     * Format a product for POS display.
     */
    private function formatProduct(Product $product): array
    {
        $hasVariants = $product->variants->count() > 0;
        $totalStock  = $hasVariants
            ? $product->variants->sum('stock_quantity')
            : $product->stock_quantity;

        return [
            'id'             => $product->id,
            'name'           => $product->name,
            'sku'            => $product->sku,
            'barcode'        => $product->barcode,
            'price'          => (float) $product->price,
            'mrp'            => (float) ($product->mrp ?? $product->price),
            'cost_price'     => (float) ($product->cost_price ?? 0),
            'tax_rate'       => (float) ($product->tax_rate ?? 0),
            'hsn_code'       => $product->hsn_code,
            'stock'          => (int) $totalStock,
            'low_stock'      => $totalStock > 0 && $totalStock <= ($product->low_stock_threshold ?? 10),
            'in_stock'       => $totalStock > 0,
            'image'          => $product->primary_image_url,
            'category'       => $product->category?->name,
            'has_variants'   => $hasVariants,
            'variants'       => $hasVariants ? $product->variants->map(function ($v) use ($product) {
                $attrs = is_array($v->attributes) ? $v->attributes
                    : (is_string($v->attributes) ? (json_decode($v->attributes, true) ?: []) : []);
                $size = $attrs['size'] ?? null;
                $color = $attrs['color'] ?? null;
                return [
                    'id'           => $v->id,
                    'product_name' => $product->name,
                    'name'         => $v->name,
                    'sku'          => $v->sku,
                    'barcode'      => $v->barcode,
                    'price'        => (float) ($v->price ?? $product->price),
                    'stock'        => (int) $v->stock_quantity,
                    'in_stock'     => $v->stock_quantity > 0,
                    'attributes'   => $attrs,
                    // Flat fields the POS variant picker view reads:
                    'size'         => $size,
                    'size_name'    => $size,
                    'color'        => $color ? ucfirst($color) : null,
                    'color_name'   => $color ? ucfirst($color) : null,
                    'color_hex'    => $color ? $this->colorHex($color) : null,
                ];
            }) : [],
        ];
    }

    /**
     * Map common color names to a hex code so the POS variant picker can show
     * a coloured swatch dot. Returns NULL for unrecognised — the picker hides it.
     */
    private function colorHex(string $name): ?string
    {
        static $map = [
            'red'        => '#dc2626',
            'pink'       => '#ec4899',
            'orange'     => '#f97316',
            'yellow'     => '#eab308',
            'green'      => '#16a34a',
            'olive'      => '#65a30d',
            'teal'       => '#14b8a6',
            'cyan'       => '#06b6d4',
            'blue'       => '#2563eb',
            'navy'       => '#1e3a8a',
            'purple'     => '#9333ea',
            'magenta'    => '#d946ef',
            'maroon'     => '#7f1d1d',
            'brown'      => '#92400e',
            'beige'      => '#d6c7a3',
            'cream'      => '#f5f0e1',
            'white'      => '#ffffff',
            'off-white'  => '#fafaf6',
            'ivory'      => '#fffff0',
            'grey'       => '#6b7280',
            'gray'       => '#6b7280',
            'silver'     => '#c0c0c0',
            'black'      => '#000000',
            'gold'       => '#d4af37',
            'multi'      => '#a855f7',
            'multicolor' => '#a855f7',
            'multicolour'=> '#a855f7',
        ];
        $key = strtolower(trim($name));
        return $map[$key] ?? null;
    }
}
