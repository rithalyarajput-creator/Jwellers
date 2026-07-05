<x-layouts.admin>
    <x-slot name="title">Tally Export</x-slot>

    <x-slot name="header">
        <div>
            <h1 style="font-size: 1.125rem; font-weight: 600; color: #303030;">Tally Export</h1>
            <p style="font-size: 12px; color: #616161; margin-top: 2px;">Export POS sales as TallyPrime XML for import into your accounting software</p>
        </div>
    </x-slot>

    @if(session('error'))
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem;">
            {{ session('error') }}
        </div>
    @endif

    <div class="card" style="max-width: 720px;">
        <div style="padding: 0.875rem 1.25rem; border-bottom: 1px solid #e3e3e3;">
            <h2 style="font-size: 13px; font-weight: 600; color: #303030;">Generate XML for TallyPrime</h2>
        </div>

        <form id="tallyForm" action="{{ route('admin.tally.export') }}" method="GET" style="padding: 1.25rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Store</label>
                    <select name="store_id" class="form-input" required onchange="refreshSummary()">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" @selected($store->id == $storeId)>
                                {{ $store->name }}{{ $store->gst_number ? ' (GSTIN ' . $store->gst_number . ')' : ' (GSTIN not set)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-input" required onchange="refreshSummary()">
                </div>
                <div>
                    <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-input" required onchange="refreshSummary()">
                </div>
            </div>

            @if($summary)
                @if($summary['count'] === 0)
                    <div style="background: #fef3c7; border: 1px solid #fde68a; padding: 12px 14px; border-radius: 8px; margin-bottom: 1rem;">
                        <p style="font-size: 13px; color: #854d0e; margin: 0; font-weight: 500;">
                            No completed sales found in this range. Adjust the dates above.
                        </p>
                    </div>
                @else
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px 14px; border-radius: 8px; margin-bottom: 1rem;">
                        <p style="font-size: 13px; color: #166534; margin: 0; font-weight: 500;">
                            Found <strong>{{ $summary['count'] }}</strong> completed sales totalling <strong>&#8377;{{ number_format($summary['total'], 2) }}</strong> in this range.
                        </p>
                    </div>
                @endif
            @endif

            <button type="submit" style="width: 100%; padding: 12px; background: #F8931D; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">
                Download Tally XML
            </button>
        </form>

        <script>
            function refreshSummary() {
                var f = document.getElementById('tallyForm');
                f.action = '{{ route('admin.tally.index') }}';
                f.submit();
            }
        </script>
    </div>

    <div class="card" style="max-width: 720px; margin-top: 1rem; padding: 1.25rem;">
        <h3 style="font-size: 13px; font-weight: 600; color: #303030; margin-bottom: 8px;">How to import into TallyPrime</h3>
        <ol style="font-size: 12px; color: #525252; line-height: 1.6; padding-left: 18px;">
            <li>Download the XML file from the button above.</li>
            <li>Open TallyPrime on your Windows machine and select your company.</li>
            <li>Go to <strong>Gateway of Tally</strong> then <strong>Import Data</strong> then <strong>Vouchers</strong>.</li>
            <li>Choose the downloaded XML file and confirm the import.</li>
            <li>All POS sales in the selected date range will appear as Sales Vouchers in Tally.</li>
        </ol>
        <p style="font-size: 11px; color: #737373; margin-top: 10px;">
            Note. The selected store must have a GSTIN configured in Admin then Stores, otherwise the COMPANYGSTIN field will be blank in the XML.
        </p>
    </div>
</x-layouts.admin>
