<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\Store;
use Carbon\Carbon;

/**
 * Builds TallyPrime-compatible XML for importing POS sales as Sales Vouchers.
 * Tally on the local Windows machine imports the XML via Gateway → Import Data → Vouchers.
 */
class TallyExportService
{
    public function buildXml(int $storeId, Carbon $from, Carbon $to): string
    {
        $store = Store::findOrFail($storeId);
        $companyName = $store->name ?: config('app.name', 'Jwellers');

        $sales = PosSale::with(['items.product', 'customer'])
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('created_at')
            ->get();

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startElement('ENVELOPE');

        $xml->startElement('HEADER');
        $xml->writeElement('TALLYREQUEST', 'Import Data');
        $xml->endElement();

        $xml->startElement('BODY');
        $xml->startElement('IMPORTDATA');

        $xml->startElement('REQUESTDESC');
        $xml->writeElement('REPORTNAME', 'Vouchers');
        $xml->startElement('STATICVARIABLES');
        $xml->writeElement('SVCURRENTCOMPANY', $companyName);
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('REQUESTDATA');

        foreach ($sales as $sale) {
            $this->writeVoucher($xml, $sale, $store);
        }

        $xml->endElement(); // REQUESTDATA
        $xml->endElement(); // IMPORTDATA
        $xml->endElement(); // BODY
        $xml->endElement(); // ENVELOPE

        return $xml->outputMemory();
    }

    private function writeVoucher(\XMLWriter $xml, PosSale $sale, Store $store): void
    {
        $date = $sale->created_at->format('Ymd');
        $party = $sale->customer->name ?? 'POS Walk-in Customer';
        $gstRate = $this->detectGstRate($sale);
        $salesLedger = 'Sales @ ' . rtrim(rtrim(number_format($gstRate, 2), '0'), '.') . '%';

        $totalCgst = (float) $sale->items->sum('cgst');
        $totalSgst = (float) $sale->items->sum('sgst');
        $totalIgst = (float) $sale->items->sum('igst');

        // Fallback: if per-item tax breakdown is missing, derive from sale.tax
        // Intra-state (buyer + seller in same state) splits 50/50 CGST/SGST
        // Store GSTIN state code determines the seller state
        if ($totalCgst == 0 && $totalSgst == 0 && $totalIgst == 0 && (float) $sale->tax > 0) {
            $totalCgst = round((float) $sale->tax / 2, 2);
            $totalSgst = round((float) $sale->tax - $totalCgst, 2);
        }

        // Prices on this system are GST-INCLUSIVE, so the taxable value (to credit
        // the Sales ledger in Tally) is the gross minus the derived tax portion.
        $gross = (float) $sale->subtotal - (float) $sale->discount;
        $totalTaxable = round($gross - $totalCgst - $totalSgst - $totalIgst, 2);

        $xml->startElement('TALLYMESSAGE');
        $xml->writeAttribute('xmlns:UDF', 'TallyUDF');

        $xml->startElement('VOUCHER');
        $xml->writeAttribute('VCHTYPE', 'Sales');
        $xml->writeAttribute('ACTION', 'Create');
        $xml->writeAttribute('OBJVIEW', 'Invoice Voucher View');

        $xml->writeElement('DATE', $date);
        $xml->writeElement('VOUCHERTYPENAME', 'Sales');
        $xml->writeElement('VOUCHERNUMBER', $sale->sale_number);
        $xml->writeElement('PARTYLEDGERNAME', $party);
        $xml->writeElement('PARTYNAME', $party);
        $xml->writeElement('REFERENCE', $sale->sale_number);
        $xml->writeElement('NARRATION', 'POS sale at ' . $store->name . ' on ' . $sale->created_at->format('d-M-Y H:i'));
        $xml->writeElement('ISINVOICE', 'Yes');
        $xml->writeElement('ENTEREDBY', 'Jwellers POS');
        if ($store->gst_number) {
            $xml->writeElement('PARTYGSTIN', '');
            $xml->writeElement('COMPANYGSTIN', $store->gst_number);
        }

        // Ledger entries
        // 1) Party (debit total)
        $xml->startElement('ALLLEDGERENTRIES.LIST');
        $xml->writeElement('LEDGERNAME', $party);
        $xml->writeElement('ISDEEMEDPOSITIVE', 'Yes');
        $xml->writeElement('AMOUNT', '-' . number_format((float) $sale->total, 2, '.', ''));
        $xml->endElement();

        // 2) Sales (credit taxable)
        $xml->startElement('ALLLEDGERENTRIES.LIST');
        $xml->writeElement('LEDGERNAME', $salesLedger);
        $xml->writeElement('ISDEEMEDPOSITIVE', 'No');
        $xml->writeElement('AMOUNT', number_format($totalTaxable, 2, '.', ''));
        $xml->endElement();

        // 3) CGST
        if ($totalCgst > 0) {
            $xml->startElement('ALLLEDGERENTRIES.LIST');
            $xml->writeElement('LEDGERNAME', 'CGST');
            $xml->writeElement('ISDEEMEDPOSITIVE', 'No');
            $xml->writeElement('AMOUNT', number_format($totalCgst, 2, '.', ''));
            $xml->endElement();
        }

        // 4) SGST
        if ($totalSgst > 0) {
            $xml->startElement('ALLLEDGERENTRIES.LIST');
            $xml->writeElement('LEDGERNAME', 'SGST');
            $xml->writeElement('ISDEEMEDPOSITIVE', 'No');
            $xml->writeElement('AMOUNT', number_format($totalSgst, 2, '.', ''));
            $xml->endElement();
        }

        // 5) IGST
        if ($totalIgst > 0) {
            $xml->startElement('ALLLEDGERENTRIES.LIST');
            $xml->writeElement('LEDGERNAME', 'IGST');
            $xml->writeElement('ISDEEMEDPOSITIVE', 'No');
            $xml->writeElement('AMOUNT', number_format($totalIgst, 2, '.', ''));
            $xml->endElement();
        }

        // Stock item inventory entries.
        // Prices are GST-inclusive, so the net taxable amount per line = gross - tax.
        // Both inventory AMOUNT and the Sales ledger allocation must use the net
        // value so the voucher balances (Party debit = Sales + CGST + SGST + IGST).
        foreach ($sale->items as $item) {
            $itemTax = (float) $item->cgst + (float) $item->sgst + (float) $item->igst;
            $itemNet = round((float) $item->total - $itemTax, 2);
            $qty = (float) $item->quantity;
            $netRate = $qty > 0 ? round($itemNet / $qty, 2) : (float) $item->price;

            $xml->startElement('ALLINVENTORYENTRIES.LIST');
            $xml->writeElement('STOCKITEMNAME', $item->product_name);
            $xml->writeElement('ISDEEMEDPOSITIVE', 'No');
            $xml->writeElement('RATE', number_format($netRate, 2, '.', '') . '/PCS');
            $xml->writeElement('AMOUNT', number_format($itemNet, 2, '.', ''));
            $xml->writeElement('ACTUALQTY', $qty . ' PCS');
            $xml->writeElement('BILLEDQTY', $qty . ' PCS');
            if ($item->hsn_code) {
                $xml->writeElement('HSNCODE', $item->hsn_code);
            }

            $xml->startElement('ACCOUNTINGALLOCATIONS.LIST');
            $xml->writeElement('LEDGERNAME', $salesLedger);
            $xml->writeElement('ISDEEMEDPOSITIVE', 'No');
            $xml->writeElement('AMOUNT', number_format($itemNet, 2, '.', ''));
            $xml->endElement();

            $xml->endElement();
        }

        $xml->endElement(); // VOUCHER
        $xml->endElement(); // TALLYMESSAGE
    }

    private function detectGstRate(PosSale $sale): float
    {
        // Prefer item-level tax_rate if populated
        $rates = $sale->items->pluck('tax_rate')->filter(fn($r) => (float) $r > 0)->unique();
        if ($rates->count() === 1) {
            return (float) $rates->first();
        }

        // Else derive from sale-level tax / taxable
        $totalTax = (float) $sale->tax ?: (float) $sale->items->sum(fn($i) => (float) $i->cgst + (float) $i->sgst + (float) $i->igst);
        $taxable = (float) $sale->subtotal - (float) $sale->discount;
        if ($taxable > 0 && $totalTax > 0) {
            return round(($totalTax / $taxable) * 100, 2);
        }
        return 0;
    }

    public function summary(int $storeId, Carbon $from, Carbon $to): array
    {
        $query = PosSale::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);

        return [
            'count' => $query->count(),
            'total' => (float) $query->sum('total'),
        ];
    }
}
