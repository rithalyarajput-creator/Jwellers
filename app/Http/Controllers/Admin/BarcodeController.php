<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarcodeController extends Controller
{
    public function __construct(private readonly BarcodeService $svc) {}

    /** Index: paginated list of products with barcode counts. */
    public function index(Request $request): View
    {
        $q = Product::query()
            ->where('is_active', 1)
            ->withCount('barcodes')
            ->orderByDesc('id');

        if ($search = $request->input('q')) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('article_no', 'like', "%{$search}%");
            });
        }

        $products = $q->paginate(40)->withQueryString();

        return view('admin.barcodes.index', compact('products'));
    }

    /** Detail: list barcodes for one product (incl. variants), with assignment form. */
    public function show(Product $product): View
    {
        $product->load(['variants:id,product_id,name,sku,attributes', 'barcodes']);
        $variantBarcodes = ProductBarcode::where('product_id', $product->id)
            ->whereNotNull('variant_id')
            ->get()
            ->groupBy('variant_id');

        return view('admin.barcodes.show', compact('product', 'variantBarcodes'));
    }

    /** Assign one barcode (manual or auto-generated). */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id'  => ['nullable', 'integer', 'exists:product_variants,id'],
            'type'        => ['required', 'in:ean13,code128,internal'],
            'manual_code' => ['nullable', 'string', 'max:128'],
            'pack_unit'   => ['required', 'in:piece,inner,outer,carton'],
            'is_primary'  => ['nullable', 'boolean'],
        ]);

        try {
            $this->svc->assign(
                productId:  $product->id,
                variantId:  $validated['variant_id'] ?? null,
                type:       $validated['type'],
                manualCode: $validated['manual_code'] ?: null,
                packUnit:   $validated['pack_unit'],
                isPrimary:  (bool) ($validated['is_primary'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['barcode' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Barcode assigned.');
    }

    /** Delete one barcode row. */
    public function destroy(ProductBarcode $barcode): RedirectResponse
    {
        $barcode->delete();
        return back()->with('success', 'Barcode removed.');
    }

    /**
     * Bulk auto-generate EAN-13 for every active product (and each variant) that lacks one.
     * Idempotent: skips products that already have a primary barcode.
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $type     = $request->input('type', 'ean13');
        $scope    = $request->input('scope', 'missing'); // missing | all
        $limit    = (int) $request->input('limit', 5000);

        if (! in_array($type, ['ean13', 'code128', 'internal'], true)) {
            return response()->json(['message' => 'Invalid type'], 422);
        }

        $generated = 0;
        $skipped = 0;
        $errors = [];

        $productsQuery = Product::query()->where('is_active', 1);
        if ($scope === 'missing') {
            $productsQuery->whereDoesntHave('barcodes', fn ($q) => $q->whereNull('variant_id'));
        }

        $productsQuery->limit($limit)->chunk(200, function ($products) use ($type, &$generated, &$skipped, &$errors) {
            foreach ($products as $p) {
                try {
                    $this->svc->assign($p->id, null, $type, null, 'piece', true);
                    $generated++;
                } catch (\Throwable $e) {
                    $errors[] = "P#{$p->id}: " . $e->getMessage();
                    $skipped++;
                }
            }
        });

        // Variants too
        $variantsQuery = ProductVariant::query()->where('is_active', 1);
        if ($scope === 'missing') {
            $variantsQuery->whereDoesntHave('barcodes');
        }
        $variantsQuery->limit($limit)->chunk(200, function ($variants) use ($type, &$generated, &$skipped, &$errors) {
            foreach ($variants as $v) {
                try {
                    $this->svc->assign($v->product_id, $v->id, $type, null, 'piece', true);
                    $generated++;
                } catch (\Throwable $e) {
                    $errors[] = "V#{$v->id}: " . $e->getMessage();
                    $skipped++;
                }
            }
        });

        return response()->json([
            'success'   => true,
            'generated' => $generated,
            'skipped'   => $skipped,
            'errors'    => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Render a barcode image (PNG) for inline use. Cached forever.
     * URL: /admin/barcodes/render/{code}.png?type=ean13
     */
    public function render(Request $request, string $code)
    {
        $type = $request->input('type', 'ean13');
        try {
            $png = $this->svc->renderPng($code, $type);
        } catch (\Throwable $e) {
            abort(404, 'Could not render barcode: ' . $e->getMessage());
        }
        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Print labels. Supports thermal | a4 (Avery 30-up) | st12 | st24 | st32 (MARG sheets).
     * ?products=1,2,3 picks specific products; ?qty=N repeats each label N times.
     */
    public function labels(Request $request): View
    {
        $format = $request->input('format', 'thermal');
        $qty    = max(1, min(500, (int) $request->input('qty', 1)));
        $ids    = array_filter(array_map('intval', explode(',', (string) $request->input('products', ''))));

        $barcodes = ProductBarcode::with(['product:id,name,sku,price,mrp', 'variant:id,product_id,name,sku'])
            ->where('is_primary', true)
            ->when(! empty($ids), fn ($q) => $q->whereIn('product_id', $ids))
            ->limit(2000)
            ->get();

        $rows = [];
        foreach ($barcodes as $bc) {
            for ($i = 0; $i < $qty; $i++) {
                $rows[] = $bc;
            }
        }

        // MARG sheet configurations (A4 portrait, dimensions in mm)
        $margConfigs = [
            'st12' => [
                'cols' => 3, 'rows' => 4, 'w' => 65, 'h' => 67.7,
                'col_gap' => 2.5, 'row_gap' => 0, 'margin_x' => 6, 'margin_y' => 13,
                'name' => 'MARG ST-12 (12-up · 65×67.7mm)',
                'name_pt' => 10, 'code_pt' => 7.5, 'price_pt' => 11, 'bar_h' => 16,
            ],
            'st24' => [
                'cols' => 3, 'rows' => 8, 'w' => 65, 'h' => 33.9,
                'col_gap' => 2.5, 'row_gap' => 0, 'margin_x' => 6, 'margin_y' => 13,
                'name' => 'MARG ST-24 (24-up · 65×33.9mm)',
                'name_pt' => 8, 'code_pt' => 6.5, 'price_pt' => 9, 'bar_h' => 11,
            ],
            'st32' => [
                'cols' => 4, 'rows' => 8, 'w' => 48, 'h' => 33.9,
                'col_gap' => 2, 'row_gap' => 0, 'margin_x' => 6, 'margin_y' => 13,
                'name' => 'MARG ST-32 (32-up · 48×33.9mm)',
                'name_pt' => 7, 'code_pt' => 6, 'price_pt' => 8, 'bar_h' => 10,
            ],
        ];

        // Thermal roll configurations. Default 50×20mm matches the actual Foreverkids paper roll
        // ("Paper Labels Size 50X20 (Packing 2000)"). 50×25 kept as a legacy fallback.
        $thermalConfigs = [
            'thermal' => [
                'w' => 50, 'h' => 20, 'pad' => 0.5,
                'name_pt' => 6.5, 'meta_pt' => 5.5, 'code_pt' => 5.5, 'bar_h' => 8,
                'name' => 'Thermal 50×20mm',
            ],
            'thermal_50x25' => [
                'w' => 50, 'h' => 25, 'pad' => 1.0,
                'name_pt' => 7.0, 'meta_pt' => 6.0, 'code_pt' => 6.0, 'bar_h' => 10,
                'name' => 'Thermal 50×25mm (legacy roll)',
            ],
            'thermal_50x15' => [
                'w' => 50, 'h' => 15, 'pad' => 0.5,
                'name_pt' => 5.5, 'meta_pt' => 4.5, 'code_pt' => 4.5, 'bar_h' => 6,
                'name' => 'Thermal 50×15mm (compact roll)',
            ],
            'thermal_38x25' => [
                'w' => 38, 'h' => 25, 'pad' => 0.8,
                'name_pt' => 6.5, 'meta_pt' => 5.5, 'code_pt' => 5.5, 'bar_h' => 9,
                'name' => 'Thermal 38×25mm (narrow roll)',
            ],
            'thermal_38x15' => [
                'w' => 38, 'h' => 15, 'pad' => 0.5,
                'name_pt' => 5.0, 'meta_pt' => 4.0, 'code_pt' => 4.0, 'bar_h' => 6,
                'name' => 'Thermal 38×15mm (narrow compact)',
            ],
        ];

        if (isset($margConfigs[$format])) {
            return view('admin.barcodes.labels-marg', [
                'barcodes' => $rows,
                'svc'      => $this->svc,
                'cfg'      => $margConfigs[$format],
            ]);
        }

        if (isset($thermalConfigs[$format])) {
            return view('admin.barcodes.labels-thermal', [
                'barcodes' => $rows,
                'svc'      => $this->svc,
                'cfg'      => $thermalConfigs[$format],
            ]);
        }

        // A4 Avery 5160 (30-up) — default for non-thermal, non-MARG
        return view('admin.barcodes.labels-a4', ['barcodes' => $rows, 'svc' => $this->svc]);
    }
}
