<x-layouts.admin>
    <x-slot name="title">Pre-Launch Waitlist</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 style="font-size: 1.125rem; font-weight: 600; color: #303030;">Pre-Launch Waitlist</h1>
                <p style="font-size: 12px; color: #616161; margin-top: 2px;">People who signed up via the coming-soon page</p>
            </div>
            <a href="{{ route('admin.prelaunch.export') }}" class="btn btn-primary" style="font-size: 13px;">
                Export CSV
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
        <div class="card" style="padding: 1rem 1.25rem;">
            <p style="font-size: 11px; color: #616161; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 500;">Total Signups</p>
            <p style="font-size: 24px; font-weight: 700; color: #303030; margin-top: 4px;">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="card" style="padding: 1rem 1.25rem;">
            <p style="font-size: 11px; color: #616161; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 500;">Today</p>
            <p style="font-size: 24px; font-weight: 700; color: #303030; margin-top: 4px;">{{ number_format($stats['today']) }}</p>
        </div>
        <div class="card" style="padding: 1rem 1.25rem;">
            <p style="font-size: 11px; color: #616161; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 500;">This Week</p>
            <p style="font-size: 24px; font-weight: 700; color: #303030; margin-top: 4px;">{{ number_format($stats['week']) }}</p>
        </div>
    </div>

    <!-- Search -->
    <div class="card" style="margin-bottom: 1rem;">
        <form action="{{ route('admin.prelaunch.index') }}" method="GET" style="padding: 0.75rem 1rem; display: flex; gap: 0.5rem;">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by phone number…"
                   class="form-input" style="flex: 1; font-size: 13px;">
            <button type="submit" class="btn btn-secondary" style="font-size: 13px;">Search</button>
            @if($q)
                <a href="{{ route('admin.prelaunch.index') }}" class="btn btn-secondary" style="font-size: 13px;">Clear</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div class="card">
        <table style="width: 100%;">
            <thead>
                <tr style="border-bottom: 1px solid #e3e3e3;">
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">#</th>
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">Phone</th>
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">IP</th>
                    <th style="padding: 0.5rem 1rem; text-align: left; font-size: 11px; font-weight: 500; color: #616161;">Signed up</th>
                    <th style="padding: 0.5rem 1rem; text-align: right; font-size: 11px; font-weight: 500; color: #616161;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signups as $signup)
                    <tr style="border-bottom: 1px solid #f1f1f1;">
                        <td style="padding: 0.625rem 1rem; font-size: 12px; color: #616161;">{{ $signup->id }}</td>
                        <td style="padding: 0.625rem 1rem; font-size: 13px; font-weight: 500; color: #303030;">
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $signup->phone) }}" target="_blank" rel="noopener" style="color: #005bd3; text-decoration: none;">
                                {{ $signup->phone }}
                            </a>
                        </td>
                        <td style="padding: 0.625rem 1rem; font-size: 12px; color: #616161;">{{ $signup->ip ?? '—' }}</td>
                        <td style="padding: 0.625rem 1rem; font-size: 13px; color: #303030;">
                            {{ $signup->created_at->format('d M Y, h:i A') }}
                            <p style="font-size: 11px; color: #616161;">{{ $signup->created_at->diffForHumans() }}</p>
                        </td>
                        <td style="padding: 0.625rem 1rem; text-align: right;">
                            <form action="{{ route('admin.prelaunch.destroy', $signup) }}" method="POST"
                                  onsubmit="return confirm('Delete this signup?')" style="display: inline;">
                                @csrf @method('DELETE')
                                <button type="submit" style="font-size: 12px; color: #d72c0d; background: none; border: none; cursor: pointer;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 2rem 1rem; text-align: center; font-size: 13px; color: #616161;">
                            @if($q)
                                No signups found matching "{{ $q }}".
                            @else
                                No signups yet. When visitors submit the waitlist form on the coming-soon page, they'll appear here.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($signups->hasPages())
            <div style="padding: 1rem;">
                {{ $signups->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
