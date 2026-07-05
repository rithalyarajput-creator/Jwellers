<x-layouts.admin>
    <x-slot name="title">Edit Terminal</x-slot>

    <x-slot name="header">
        <div>
            <h1 style="font-size: 1.125rem; font-weight: 600; color: #303030;">Edit Terminal</h1>
            <p style="font-size: 12px; color: #616161; margin-top: 2px;">Device ID: <span style="font-family: monospace; font-weight: 600;">{{ $register->device_id }}</span></p>
        </div>
    </x-slot>

    <div class="card" style="max-width: 600px;">
        <form action="{{ route('admin.pos-registers.update', $register) }}" method="POST" style="padding: 1.25rem;">
            @csrf @method('PUT')

            @if($errors->any())
                <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem;">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Store</label>
                <select name="store_id" class="form-input" required>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($register->store_id == $store->id)>
                            {{ $store->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Terminal Name</label>
                <input type="text" name="name" value="{{ old('name', $register->name) }}" class="form-input" required maxlength="100">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Status</label>
                <select name="status" class="form-input" required>
                    <option value="active" @selected($register->status === 'active')>Active</option>
                    <option value="inactive" @selected($register->status === 'inactive')>Inactive (cannot log in)</option>
                </select>
                <p style="font-size: 11px; color: #737373; margin-top: 4px;">Inactive terminals stay in history but staff cannot log in until reactivated.</p>
            </div>

            <div style="margin-bottom: 1.5rem; padding: 12px 14px; background: #f9fafb; border-radius: 8px;">
                <p style="font-size: 12px; color: #737373; margin: 0;">
                    <strong>Device ID:</strong> <span style="font-family: monospace;">{{ $register->device_id }}</span> (cannot be changed)<br>
                    <strong>Sales recorded:</strong> {{ number_format($register->sales()->count()) }}<br>
                    <strong>Last sync:</strong> {{ $register->last_sync_at?->diffForHumans() ?? 'never' }}
                </p>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <a href="{{ route('admin.pos-registers.index') }}" class="btn btn-secondary" style="font-size: 13px;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="font-size: 13px;">Save Changes</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
