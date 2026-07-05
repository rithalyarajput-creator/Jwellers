<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosRegister;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PosRegisterController extends Controller
{
    public function index()
    {
        $registers = PosRegister::with(['store', 'sales'])
            ->withCount('sales')
            ->orderBy('store_id')
            ->orderBy('name')
            ->get();

        return view('admin.pos-registers.index', compact('registers'));
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        $suggestedId = 'FK-TERM-' . str_pad(PosRegister::count() + 1, 2, '0', STR_PAD_LEFT);

        return view('admin.pos-registers.create', compact('stores', 'suggestedId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'name' => 'required|string|max:100',
            'device_id' => 'required|string|max:50|unique:pos_registers,device_id|regex:/^[A-Za-z0-9\-_]+$/',
        ], [
            'device_id.regex' => 'Device ID can only contain letters, numbers, hyphens, and underscores.',
            'device_id.unique' => 'A terminal with this Device ID already exists.',
        ]);

        $data['status'] = 'active';

        $register = PosRegister::create($data);

        return redirect()->route('admin.pos-registers.index')
            ->with('success', 'Terminal "' . $register->name . '" created with Device ID ' . $register->device_id);
    }

    public function edit(PosRegister $posRegister)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('admin.pos-registers.edit', ['register' => $posRegister, 'stores' => $stores]);
    }

    public function update(Request $request, PosRegister $posRegister)
    {
        $data = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'name' => 'required|string|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $posRegister->update($data);

        return redirect()->route('admin.pos-registers.index')
            ->with('success', 'Terminal "' . $posRegister->name . '" updated.');
    }

    public function destroy(PosRegister $posRegister)
    {
        if ($posRegister->sales()->exists()) {
            return back()->with('error', 'Cannot delete "' . $posRegister->name . '" because it has sales history. Mark it inactive instead.');
        }
        $name = $posRegister->name;
        $posRegister->delete();
        return redirect()->route('admin.pos-registers.index')
            ->with('success', 'Terminal "' . $name . '" deleted.');
    }
}
