<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrelaunchSignup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrelaunchSignupController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('q');

        $signups = PrelaunchSignup::query()
            ->when($q, fn ($query) => $query->where('phone', 'like', '%' . $q . '%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'total' => PrelaunchSignup::count(),
            'today' => PrelaunchSignup::whereDate('created_at', today())->count(),
            'week'  => PrelaunchSignup::where('created_at', '>=', now()->subWeek())->count(),
        ];

        return view('admin.prelaunch-signups.index', compact('signups', 'stats', 'q'));
    }

    public function export(): StreamedResponse
    {
        $filename = 'prelaunch-signups-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Phone', 'IP', 'Signed up at']);
            PrelaunchSignup::orderByDesc('created_at')->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->phone,
                        $row->ip,
                        $row->created_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(PrelaunchSignup $signup)
    {
        $signup->delete();
        return back()->with('success', 'Signup deleted.');
    }
}
