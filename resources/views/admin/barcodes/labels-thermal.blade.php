{{-- Thermal label template — parameterised. One label per page (printer feeds between).
     $cfg keys: w (mm), h (mm), pad (mm), name_pt, meta_pt, code_pt, bar_h (mm), name (label-set title).
     Sizes supported today: 50×20mm (default · current Jwellers roll), 50×25mm (legacy roll). --}}
@php
    /** @var array{w:float,h:float,pad:float,name_pt:float,meta_pt:float,code_pt:float,bar_h:float,name:string} $cfg */
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $cfg['name'] }} — {{ count($barcodes) }} labels</title>
    <style>
        @page { size: {{ $cfg['w'] }}mm {{ $cfg['h'] }}mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label {
            width: {{ $cfg['w'] }}mm; height: {{ $cfg['h'] }}mm;
            padding: {{ $cfg['pad'] }}mm {{ $cfg['pad'] + 0.5 }}mm;
            display: flex; flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            page-break-after: always;
        }
        .name {
            font-size: {{ $cfg['name_pt'] }}pt; font-weight: 700;
            line-height: 1.05;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .meta {
            display: flex; justify-content: space-between;
            font-size: {{ $cfg['meta_pt'] }}pt; line-height: 1.1;
        }
        .price { font-weight: 800; }
        .barcode-img { width: 100%; height: {{ $cfg['bar_h'] }}mm; display: block; }
        .code {
            font-family: 'Consolas', monospace;
            font-size: {{ $cfg['code_pt'] }}pt; text-align: center;
            letter-spacing: 0.3pt;
        }
        @media print {
            .no-print { display: none !important; }
        }
        .no-print {
            position: fixed; top: 12pt; right: 12pt;
            background: #0f172a; color: white;
            padding: 6pt 12pt; border-radius: 4pt;
            box-shadow: 0 2pt 8pt rgba(0,0,0,0.2);
            font-size: 9pt;
        }
        .no-print button {
            background: transparent; color: white; border: 1pt solid rgba(255,255,255,0.4);
            padding: 3pt 10pt; border-radius: 3pt; cursor: pointer;
            font-size: 9pt; margin-left: 4pt;
        }
    </style>
</head>
<body>
    <div class="no-print">
        {{ count($barcodes) }} × {{ $cfg['name'] }}
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    @foreach($barcodes as $bc)
        @php
            $product = $bc->product;
            $name = trim(($product?->name ?? 'Product') . ($bc->variant ? ' · ' . $bc->variant->name : ''));
            $sku  = $bc->variant?->sku ?? $product?->sku ?? '';
            $mrp  = (float) ($product?->mrp ?? $product?->price ?? 0);
        @endphp
        <div class="label">
            <div>
                <div class="name">{{ $name }}</div>
                <div class="meta">
                    <span>{{ $sku }}</span>
                    @if($mrp > 0)<span class="price">₹{{ number_format($mrp, 2) }}</span>@endif
                </div>
            </div>
            <div>
                @php $svg = $svc->renderSvg($bc->barcode, $bc->barcode_type); @endphp
                <div class="barcode-img">{!! $svg !!}</div>
                <div class="code">{{ $bc->barcode }}</div>
            </div>
        </div>
    @endforeach
<script>/*AUTO-PRINT-MARKER*/window.addEventListener("load",function(){setTimeout(function(){window.print();},250);});</script></body>
</html>
