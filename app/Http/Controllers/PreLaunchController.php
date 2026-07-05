<?php

namespace App\Http\Controllers;

use App\Models\PrelaunchSignup;
use Illuminate\Http\Request;

class PreLaunchController extends Controller
{
    public function show()
    {
        return view('pages.coming-soon');
    }

    public function verify(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $expected = (string) config('app.prelaunch_password');
        if (!empty($expected) && hash_equals($expected, (string) $request->input('password'))) {
            $request->session()->put('prelaunch_verified', true);
            return redirect('/');
        }

        return back()->withErrors(['password' => 'Invalid access code.']);
    }

    public function signup(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ], [
            'phone.regex' => 'Please enter a valid 10-digit Indian mobile number.',
        ]);

        $phone = '+91' . $request->input('phone');

        PrelaunchSignup::firstOrCreate(
            ['phone' => $phone],
            ['ip' => $request->ip()]
        );

        return back()->with('waitlist_success', true);
    }
}
