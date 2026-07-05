<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\TallyExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TallyExportController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $storeId = $request->input('store_id', $stores->first()->id ?? null);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $summary = null;
        if ($storeId) {
            $summary = app(TallyExportService::class)->summary(
                (int) $storeId,
                Carbon::parse($from),
                Carbon::parse($to)
            );
        }

        return view('admin.tally.index', compact('stores', 'storeId', 'from', 'to', 'summary'));
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $store = Store::findOrFail($request->store_id);
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $filename = 'tally-export-' . $store->code . '-' . $from->format('Ymd') . '-to-' . $to->format('Ymd') . '.xml';
        $xml = app(TallyExportService::class)->buildXml((int) $store->id, $from, $to);

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename, ['Content-Type' => 'application/xml']);
    }
}
