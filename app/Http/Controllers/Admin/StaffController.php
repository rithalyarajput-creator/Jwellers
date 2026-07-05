<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $perPage = request()->input('per_page', 10);
        $staff = Staff::with('user', 'store')->latest()->paginate($perPage)->withQueryString();

        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('admin.staff.create', compact('stores'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:manager,cashier,support,warehouse,accountant',
            'store_id' => 'nullable|exists:stores,id',
            'pin' => 'nullable|string|regex:/^\d{4,6}$/',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:dashboard,orders,catalog,customers,sellers,staff,marketing,storefront,content,reports,tally,settings',
        ], [
            'pin.regex' => 'PIN must be 4 to 6 digits only.',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
        ]);

        $employeeId = 'EMP-' . str_pad(Staff::max('id') + 1, 4, '0', STR_PAD_LEFT);

        // Auto-generate 4-digit PIN if not provided
        $plainPin = $validated['pin'] ?? null;
        if (empty($plainPin)) {
            $plainPin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        $staff = Staff::create([
            'user_id' => $user->id,
            'employee_id' => $employeeId,
            'role' => $validated['role'],
            'store_id' => $validated['store_id'] ?? null,
            'pin' => Hash::make($plainPin),
            'is_active' => $validated['is_active'] ?? true,
            'permissions' => $validated['permissions'] ?? null,
            'joined_at' => now(),
        ]);

        return redirect()->route('admin.staff.index')->with([
            'success' => 'Staff member created',
            'new_pin' => $plainPin,
            'new_pin_name' => $user->first_name . ' ' . $user->last_name,
        ]);
    }

    public function edit(Staff $staff): View
    {
        $staff->load('user');
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('admin.staff.edit', compact('staff', 'stores'));
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,' . $staff->user_id,
            'password' => 'nullable|min:8|confirmed',
            'role' => 'required|in:manager,cashier,support,warehouse,accountant',
            'store_id' => 'nullable|exists:stores,id',
            'pin' => 'nullable|string|regex:/^\d{4,6}$/',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:dashboard,orders,catalog,customers,sellers,staff,marketing,storefront,content,reports,tally,settings',
        ], [
            'pin.regex' => 'PIN must be 4 to 6 digits only.',
        ]);

        $staff->user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
        ]);

        if ($request->filled('password')) {
            $staff->user->update(['password' => Hash::make($validated['password'])]);
        }

        $staffUpdate = [
            'role' => $validated['role'],
            'store_id' => $validated['store_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'permissions' => $validated['permissions'] ?? null,
        ];

        // Only update PIN if a new value was entered
        $flashPin = null;
        if ($request->filled('pin')) {
            $staffUpdate['pin'] = Hash::make($validated['pin']);
            $flashPin = $validated['pin'];
        }

        $staff->update($staffUpdate);

        $redirect = redirect()->route('admin.staff.index')->with('success', 'Staff member updated');
        if ($flashPin) {
            $redirect = $redirect->with([
                'new_pin' => $flashPin,
                'new_pin_name' => $staff->user->first_name . ' ' . $staff->user->last_name,
            ]);
        }
        return $redirect;
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $staff->user->delete();
        $staff->delete();

        return redirect()->route('admin.staff.index')->with('success', 'Staff member deleted');
    }
}
