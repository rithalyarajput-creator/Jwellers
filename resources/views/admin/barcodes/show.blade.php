<x-layouts.admin>
    <x-slot name="title">Barcodes — {{ $product->name }}</x-slot>

    <x-slot name="header">
        <div class="page-header">
            <h1>{{ $product->name }} <span style="font-weight:400; color:#6b7280; font-size:.85em;">— Barcodes</span></h1>
            <div style="display:flex; gap:.5rem; align-items:center;">
                <div style="display:flex; align-items:center; gap:.35rem; padding:.25rem .4rem; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">
                    <label for="sheetFormat" style="font-size:12px; color:#6b7280; font-weight:600;">Sheet:</label>
                    <select id="sheetFormat" style="font-size:12px; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; background:#fff;">
                        <option value="thermal" selected>Thermal 50×20mm (current roll)</option>
                        <option value="thermal_50x15">Thermal 50×15mm (compact roll)</option>
                        <option value="thermal_50x25">Thermal 50×25mm (legacy roll)</option>
                        <option value="thermal_38x25">Thermal 38×25mm (narrow roll)</option>
                        <option value="thermal_38x15">Thermal 38×15mm (narrow compact)</option>
                        <option value="st32">MARG ST-32 (32-up · A4)</option>
                        <option value="st24">MARG ST-24 (24-up · A4)</option>
                        <option value="st12">MARG ST-12 (12-up · A4)</option>
                        <option value="a4">Avery 5160 (30-up · A4)</option>
                    </select>
                </div>
                <a href="{{ route('admin.barcodes.index') }}" class="btn btn-secondary" style="font-size:13px;">← Back</a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div style="padding:.6rem 1rem; background:#dcfce7; color:#166534; border-radius:6px; margin-bottom:1rem;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="padding:.6rem 1rem; background:#fee2e2; color:#991b1b; border-radius:6px; margin-bottom:1rem;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Product summary --}}
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:1rem; margin-bottom:1rem; display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem;">
        <div><div style="font-size:11px; color:#6b7280; text-transform:uppercase;">SKU</div><div style="font-family:monospace; font-weight:600;">{{ $product->sku ?? '—' }}</div></div>
        <div><div style="font-size:11px; color:#6b7280; text-transform:uppercase;">Article #</div><div style="font-family:monospace; font-weight:600;">{{ $product->article_no ?? '—' }}</div></div>
        <div><div style="font-size:11px; color:#6b7280; text-transform:uppercase;">MRP</div><div style="font-family:monospace; font-weight:600;">₹{{ number_format($product->mrp ?? $product->price, 2) }}</div></div>
        <div><div style="font-size:11px; color:#6b7280; text-transform:uppercase;">Stock</div><div style="font-family:monospace; font-weight:600;">{{ $product->stock_quantity ?? 0 }}</div></div>
    </div>

    {{-- Add barcode form --}}
    <div class="assign-barcode-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px 18px; margin-bottom:16px;">
        <style>
            .assign-barcode-card .ab-label {
                display:block;
                font-size:11px;
                font-weight:600;
                letter-spacing:0.04em;
                text-transform:uppercase;
                color:#5c5f62;
                margin-bottom:6px;
                white-space:nowrap;
                overflow:hidden;
                text-overflow:ellipsis;
            }
            .assign-barcode-card .ab-input,
            .assign-barcode-card .ab-select {
                width:100%;
                height:36px;
                padding:0 10px;
                border:1px solid #c9cccf;
                border-radius:6px;
                font-size:13px;
                color:#303030;
                background:#fff;
                box-sizing:border-box;
                transition:border-color 0.12s ease, box-shadow 0.12s ease;
            }
            .assign-barcode-card .ab-input { font-family:'Consolas','Monaco',monospace; }
            .assign-barcode-card .ab-input:focus,
            .assign-barcode-card .ab-select:focus {
                border-color:#005bd3;
                outline:0;
                box-shadow:0 0 0 1px #005bd3;
            }
            .assign-barcode-card .ab-helper {
                font-size:11px;
                color:#8c9196;
                margin-top:4px;
                line-height:1.3;
            }
            .assign-barcode-card .ab-grid {
                display:grid;
                grid-template-columns: minmax(180px, 1.4fr) minmax(160px, 1.1fr) minmax(120px, 0.9fr) minmax(220px, 1.6fr) auto auto;
                gap:12px;
                align-items:end;
            }
            .assign-barcode-card .ab-primary-toggle {
                display:flex;
                align-items:center;
                gap:8px;
                height:36px;
                padding:0 12px;
                background:#fafbfb;
                border:1px solid #e1e3e5;
                border-radius:6px;
                cursor:pointer;
                font-size:13px;
                color:#303030;
                user-select:none;
                white-space:nowrap;
                transition:all 0.12s ease;
            }
            .assign-barcode-card .ab-primary-toggle:hover {
                border-color:#8c9196;
                background:#f6f6f7;
            }
            .assign-barcode-card .ab-primary-toggle input[type="checkbox"] {
                width:16px;
                height:16px;
                accent-color:#005bd3;
                cursor:pointer;
                margin:0;
            }
            .assign-barcode-card .ab-primary-toggle:has(input:checked) {
                background:#eaf2ff;
                border-color:#005bd3;
                color:#003d80;
                font-weight:600;
            }
            .assign-barcode-card .ab-submit {
                height:36px;
                padding:0 18px;
                background:#1a1a1a;
                color:#fff;
                border:1px solid #1a1a1a;
                border-radius:6px;
                font-size:13px;
                font-weight:600;
                cursor:pointer;
                white-space:nowrap;
                transition:all 0.12s ease;
                box-shadow:0 1px 0 rgba(0,0,0,0.05);
            }
            .assign-barcode-card .ab-submit:hover {
                background:#303030;
            }
            @media (max-width: 980px) {
                .assign-barcode-card .ab-grid {
                    grid-template-columns: 1fr 1fr;
                }
                .assign-barcode-card .ab-grid > *:nth-last-child(-n+2) { grid-column: span 1; }
            }
        </style>

        <div style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:12px;">
            <h3 style="margin:0; font-size:14px; font-weight:600; color:#303030;">Assign barcode</h3>
            <span style="font-size:12px; color:#8c9196;">Leave manual code blank to auto-generate.</span>
        </div>

        <form method="POST" action="{{ route('admin.barcodes.store', $product) }}" class="ab-grid">
            @csrf
            <div>
                <label class="ab-label" for="ab_variant">Variant</label>
                <select id="ab_variant" name="variant_id" class="ab-select">
                    <option value="">Product-level (no variant)</option>
                    @foreach($product->variants as $v)
                        <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->sku }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ab-label" for="ab_type">Type</label>
                <select id="ab_type" name="type" class="ab-select">
                    <option value="ean13">EAN-13 (numeric, retail)</option>
                    <option value="code128">Code 128 (alphanumeric)</option>
                    <option value="internal">Internal (in-house)</option>
                </select>
            </div>
            <div>
                <label class="ab-label" for="ab_pack">Pack unit</label>
                <select id="ab_pack" name="pack_unit" class="ab-select">
                    <option value="piece">Piece</option>
                    <option value="inner">Inner</option>
                    <option value="outer">Outer</option>
                    <option value="carton">Carton</option>
                </select>
            </div>
            <div>
                <label class="ab-label" for="ab_manual">Manual code <span style="font-weight:400; color:#8c9196; text-transform:none; letter-spacing:0;">(optional)</span></label>
                <input type="text" id="ab_manual" name="manual_code" class="ab-input"
                       placeholder="e.g. 8901234567894 or SKU-XYZ">
            </div>
            <div style="align-self:end;">
                <label class="ab-primary-toggle" title="Mark this as the primary barcode for the product/variant">
                    <input type="checkbox" name="is_primary" value="1">
                    <span>Primary</span>
                </label>
            </div>
            <div style="align-self:end;">
                <button type="submit" class="ab-submit">+ Assign</button>
            </div>
        </form>
    </div>

    {{-- Existing barcodes --}}
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead style="background:#f9fafb;">
                <tr>
                    <th style="text-align:left; padding:.6rem .8rem;">Barcode</th>
                    <th style="text-align:left; padding:.6rem .8rem;">Type</th>
                    <th style="text-align:left; padding:.6rem .8rem;">Pack</th>
                    <th style="text-align:left; padding:.6rem .8rem;">Variant</th>
                    <th style="text-align:left; padding:.6rem .8rem;">Preview</th>
                    <th style="text-align:right; padding:.6rem .8rem;"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($product->barcodes->sortByDesc('is_primary') as $bc)
                <tr style="border-top:1px solid #f3f4f6;">
                    <td style="padding:.6rem .8rem; font-family:monospace; font-weight:700;">
                        {{ $bc->barcode }}
                        @if($bc->is_primary)<span style="background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:999px; font-size:10px; margin-left:.4rem;">PRIMARY</span>@endif
                    </td>
                    <td style="padding:.6rem .8rem;">{{ strtoupper($bc->barcode_type) }}</td>
                    <td style="padding:.6rem .8rem;">{{ $bc->pack_unit }}</td>
                    <td style="padding:.6rem .8rem;">
                        @php $variantName = $bc->variant_id ? ($product->variants->firstWhere('id', $bc->variant_id)?->name ?? '—') : '—'; @endphp
                        {{ $variantName }}
                    </td>
                    <td style="padding:.6rem .8rem;">
                        <img src="{{ route('admin.barcodes.render', ['code' => $bc->barcode, 'type' => $bc->barcode_type]) }}"
                             alt="{{ $bc->barcode }}" style="height:36px; image-rendering: pixelated;">
                    </td>
                    <td style="padding:.6rem .8rem; text-align:right; white-space:nowrap;">
                        <button type="button" onclick="printOne({{ $product->id }})"
                                class="btn btn-secondary" style="font-size:12px; padding:4px 10px;"
                                title="Print using the sheet format selected above">Print</button>
                        <form method="POST" action="{{ route('admin.barcodes.destroy', $bc) }}" style="display:inline;"
                              onsubmit="return confirm('Remove this barcode?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger" style="font-size:12px; padding:4px 10px;">×</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:2rem; text-align:center; color:#6b7280;">No barcodes assigned. Use the form above to add one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <script>
        function printOne(productId) {
            const fmt = document.getElementById('sheetFormat').value;
            const url = "{{ route('admin.barcodes.labels') }}"
                + "?format=" + encodeURIComponent(fmt)
                + "&products=" + productId
                + "&qty=1";
            window.open(url, '_blank');
        }
    </script>
</x-layouts.admin>
