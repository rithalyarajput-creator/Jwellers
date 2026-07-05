<x-layouts.admin>
    <x-slot name="title">Register New Terminal</x-slot>

    <x-slot name="header">
        <div>
            <h1 style="font-size: 1.125rem; font-weight: 600; color: #303030;">Register New Terminal</h1>
            <p style="font-size: 12px; color: #616161; margin-top: 2px;">Create a new POS terminal for a billing counter</p>
        </div>
    </x-slot>

    <div class="card" style="max-width: 600px;">
        <form action="{{ route('admin.pos-registers.store') }}" method="POST" style="padding: 1.25rem;">
            @csrf

            @if($errors->any())
                <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem;">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Store <span style="color: #d72c0d;">*</span></label>
                <select name="store_id" class="form-input" required>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>
                            {{ $store->name }}{{ $store->code ? ' (' . $store->code . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Terminal Name <span style="color: #d72c0d;">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-input" placeholder="e.g. Counter 1, Main Terminal, Back Desk" required maxlength="100">
                <p style="font-size: 11px; color: #737373; margin-top: 4px;">A friendly name so staff can tell terminals apart.</p>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 12px; font-weight: 500; color: #303030; display: block; margin-bottom: 4px;">Device ID <span style="color: #d72c0d;">*</span></label>
                <input type="text" name="device_id" value="{{ old('device_id', $suggestedId) }}" class="form-input" required maxlength="50" style="font-family: monospace; text-transform: uppercase;" pattern="[A-Za-z0-9\-_]+">
                <p style="font-size: 11px; color: #737373; margin-top: 4px;">This is what staff type on the POS login screen. Only letters, numbers, hyphens, and underscores. Must be unique.</p>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <a href="{{ route('admin.pos-registers.index') }}" class="btn btn-secondary" style="font-size: 13px;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="font-size: 13px;">Register Terminal</button>
            </div>
        </form>
    </div>

    <div style="max-width: 600px; margin-top: 1rem; background: #f0f9ff; border: 1px solid #bae6fd; padding: 12px 14px; border-radius: 8px;">
        <p style="font-size: 13px; color: #075985; margin: 0; font-weight: 500;">After creating the terminal</p>
        <ol style="font-size: 12px; color: #0c4a6e; margin: 6px 0 0 18px; line-height: 1.6;">
            <li>Go to the billing counter machine</li>
            <li>Open <strong>https://foreverkidss.in/pos</strong> in a browser</li>
            <li>Type the Device ID and click <strong>Register Device</strong></li>
            <li>Staff can then log in with their PIN</li>
        </ol>
    </div>
</x-layouts.admin>
