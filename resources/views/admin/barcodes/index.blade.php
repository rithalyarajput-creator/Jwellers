<x-layouts.admin>
    <x-slot name="title">Barcodes</x-slot>

    <x-slot name="header">
        <div class="page-header">
            <h1>Barcode Master</h1>
            <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                <a href="{{ route('admin.barcodes.labels', ['format' => 'thermal']) }}" target="_blank" class="btn btn-primary" style="font-size:13px;" title="50×20mm per label, 1-up — matches Foreverkids paper roll (Packing 2000)">Print Thermal (50×20mm)</a>

                <div style="display:flex; align-items:center; gap:.35rem; padding:.25rem .4rem; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">
                    <label for="sheetFormat" style="font-size:12px; color:#6b7280; font-weight:600;">Other format:</label>
                    <select id="sheetFormat" style="font-size:12px; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; background:#fff;">
                        <option value="thermal_50x15">Thermal 50×15mm (compact roll)</option>
                        <option value="thermal_50x25">Thermal 50×25mm (legacy roll)</option>
                        <option value="thermal_38x25">Thermal 38×25mm (narrow roll)</option>
                        <option value="thermal_38x15">Thermal 38×15mm (narrow compact)</option>
                        <option value="st32" selected>MARG ST-32 (32-up · A4)</option>
                        <option value="st24">MARG ST-24 (24-up · A4)</option>
                        <option value="st12">MARG ST-12 (12-up · A4)</option>
                        <option value="a4">Avery 5160 (30-up · A4)</option>
                    </select>
                    <button type="button" onclick="printSheet()" class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">Print</button>
                </div>

                <button onclick="bulkGen('ean13','missing')" class="btn btn-primary" style="font-size:13px;">Generate EAN-13 (missing)</button>
                <button onclick="bulkGen('code128','missing')" class="btn btn-secondary" style="font-size:13px;">Generate Code 128 (missing)</button>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div style="padding:.6rem 1rem; background:#dcfce7; color:#166534; border-radius:6px; margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <form method="GET" action="{{ route('admin.barcodes.index') }}" style="display:flex; gap:.5rem;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name, SKU, article #"
                   style="flex:1; padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:6px;">
            <button class="btn btn-primary" style="font-size:13px;">Search</button>
        </form>
    </div>

    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead style="background:#f9fafb;">
                <tr>
                    <th style="text-align:left; padding:.6rem .8rem; border-bottom:1px solid #e5e7eb;">Product</th>
                    <th style="text-align:left; padding:.6rem .8rem; border-bottom:1px solid #e5e7eb;">SKU</th>
                    <th style="text-align:left; padding:.6rem .8rem; border-bottom:1px solid #e5e7eb;">Article #</th>
                    <th style="text-align:right; padding:.6rem .8rem; border-bottom:1px solid #e5e7eb;">MRP</th>
                    <th style="text-align:right; padding:.6rem .8rem; border-bottom:1px solid #e5e7eb;">Barcodes</th>
                    <th style="text-align:right; padding:.6rem .8rem; border-bottom:1px solid #e5e7eb;">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($products as $p)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:.6rem .8rem;">
                        <div style="font-weight:600; color:#111827;">{{ $p->name }}</div>
                    </td>
                    <td style="padding:.6rem .8rem; font-family:monospace; color:#374151;">{{ $p->sku ?? '—' }}</td>
                    <td style="padding:.6rem .8rem; font-family:monospace; color:#374151;">{{ $p->article_no ?? '—' }}</td>
                    <td style="padding:.6rem .8rem; text-align:right; font-family:monospace;">₹{{ number_format($p->mrp ?? $p->price, 2) }}</td>
                    <td style="padding:.6rem .8rem; text-align:right;">
                        @if($p->barcodes_count > 0)
                            <span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:999px; font-weight:600;">{{ $p->barcodes_count }}</span>
                        @else
                            <span style="background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:999px; font-weight:600;">none</span>
                        @endif
                    </td>
                    <td style="padding:.6rem .8rem; text-align:right;">
                        <a href="{{ route('admin.barcodes.show', $p) }}" class="btn btn-secondary" style="font-size:12px; padding:4px 10px;">Manage</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:2rem; text-align:center; color:#6b7280;">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem;">{{ $products->links() }}</div>

    <script>
        function printSheet() {
            const fmt = document.getElementById('sheetFormat').value;
            const url = "{{ route('admin.barcodes.labels') }}" + "?format=" + encodeURIComponent(fmt);
            window.open(url, '_blank');
        }
        async function bulkGen(type, scope) {
            if (!confirm('Generate ' + type.toUpperCase() + ' barcodes for ' + scope + ' products?')) return;
            const tok = document.querySelector('meta[name=csrf-token]')?.content;
            try {
                const r = await fetch("{{ route('admin.barcodes.bulk-generate') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': tok, 'Accept': 'application/json' },
                    body: JSON.stringify({ type, scope })
                });
                const d = await r.json();
                if (d.success) {
                    alert('Generated: ' + d.generated + '   Skipped: ' + d.skipped + (d.errors.length ? '\n\nFirst errors:\n' + d.errors.join('\n') : ''));
                    location.reload();
                } else {
                    alert('Failed: ' + (d.message || 'unknown'));
                }
            } catch (e) {
                alert('Network error: ' + e.message);
            }
        }
    </script>
</x-layouts.admin>
