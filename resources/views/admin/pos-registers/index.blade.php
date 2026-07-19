<x-layouts.admin>
    <x-slot name="title">POS Terminals</x-slot>

    <x-slot name="header">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.125rem; font-weight: 600; color: #303030;">POS Terminals</h1>
                <p style="font-size: 12px; color: #616161; margin-top: 2px;">Register a new POS terminal for each billing counter</p>
            </div>
            <a href="{{ route('admin.pos-registers.create') }}" class="btn btn-primary" style="font-size: 13px;">
                + Register Terminal
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem;">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <table style="width: 100%;">
            <thead>
                <tr style="border-bottom: 1px solid #e3e3e3;">
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">Device ID</th>
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">Name</th>
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">Store</th>
                    <th style="padding: 0.5rem 1rem; text-align: center; font-size: 11px; font-weight: 500; color: #616161;">Status</th>
                    <th style="padding: 0.5rem 1rem; text-align: right; font-size: 11px; font-weight: 500; color: #616161;">Sales</th>
                    <th style="padding: 0.5rem 1rem; text-align: right; font-size: 11px; font-weight: 500; color: #616161;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registers as $register)
                    <tr style="border-bottom: 1px solid #f1f1f1;">
                        <td style="padding: 0.75rem 1rem; font-size: 13px; font-weight: 600; color: #303030; font-family: monospace;">
                            {{ $register->device_id }}
                        </td>
                        <td style="padding: 0.75rem 1rem; font-size: 13px; color: #303030;">{{ $register->name }}</td>
                        <td style="padding: 0.75rem 1rem; font-size: 13px; color: #303030;">{{ $register->store->name ?? '—' }}</td>
                        <td style="padding: 0.75rem 1rem; text-align: center;">
                            @if($register->status === 'active')
                                <span style="font-size: 11px; padding: 3px 8px; background: #dcfce7; color: #166534; border-radius: 4px; font-weight: 500;">Active</span>
                            @else
                                <span style="font-size: 11px; padding: 3px 8px; background: #f1f5f9; color: #475569; border-radius: 4px; font-weight: 500;">Inactive</span>
                            @endif
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right; font-size: 13px; color: #303030;">{{ number_format($register->sales_count) }}</td>
                        <td style="padding: 0.75rem 1rem; text-align: right;">
                            <a href="{{ route('admin.pos-registers.edit', $register) }}" style="font-size: 12px; color: #005bd3; text-decoration: none; margin-right: 12px;">Edit</a>
                            @if($register->sales_count == 0)
                                <form action="{{ route('admin.pos-registers.destroy', $register) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete terminal {{ $register->device_id }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="font-size: 12px; color: #d72c0d; background: none; border: none; cursor: pointer; padding: 0;">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2rem 1rem; text-align: center; font-size: 13px; color: #616161;">
                            No terminals registered yet.
                            <a href="{{ route('admin.pos-registers.create') }}" style="color: #005bd3;">Register the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem; background: #fffbeb; border: 1px solid #fde68a; padding: 12px 14px; border-radius: 8px;">
        <p style="font-size: 13px; color: #854d0e; margin: 0; font-weight: 500;">How to use a new terminal</p>
        <ol style="font-size: 12px; color: #854d0e; margin: 6px 0 0 18px; line-height: 1.6;">
            <li>Create the terminal here and copy its Device ID</li>
            <li>On the billing counter machine, visit <strong>https://jwellers.in/pos</strong></li>
            <li>Enter the Device ID in the registration screen and click <strong>Register Device</strong></li>
            <li>Staff can now log in with their PIN</li>
        </ol>
    </div>
</x-layouts.admin>
