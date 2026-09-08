@extends('layout::app')

@section('title', 'Invalid Email Management')

@push('styles')
<style>
.invalid-email-page { max-width: 1200px; margin: 0 auto; padding: 1rem 1.25rem 2rem; }
.invalid-email-page h1 { margin: 0 0 .35rem; font-size: 1.45rem; color: #0f172a; }
.invalid-email-page .sub { margin: 0 0 1.25rem; color: #64748b; font-size: .92rem; }
.invalid-email-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
.invalid-email-filters { display: flex; flex-wrap: wrap; gap: .6rem; align-items: end; }
.invalid-email-filters label { display: grid; gap: .25rem; font-size: .8rem; color: #475569; font-weight: 600; }
.invalid-email-filters input, .invalid-email-filters select { min-width: 180px; border: 1px solid #cbd5e1; border-radius: 8px; padding: .45rem .65rem; }
.invalid-email-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.invalid-email-table th, .invalid-email-table td { padding: .65rem .5rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.invalid-email-table th { color: #64748b; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
.badge-status { display: inline-block; padding: .15rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
.badge-temporarily_invalid { background: #fef3c7; color: #92400e; }
.badge-permanently_blocked { background: #fee2e2; color: #991b1b; }
.badge-active, .badge-verified { background: #dcfce7; color: #166534; }
.invalid-email-actions { display: flex; flex-wrap: wrap; gap: .35rem; }
.invalid-email-actions form { margin: 0; }
.invalid-email-actions button { border: 0; border-radius: 7px; padding: .3rem .55rem; font-size: .75rem; font-weight: 600; cursor: pointer; }
.btn-reset { background: #e0e7ff; color: #3730a3; }
.btn-block { background: #fee2e2; color: #991b1b; }
.btn-remove { background: #f1f5f9; color: #334155; }
.btn-add { background: #213F99; color: #fff; border: 0; border-radius: 8px; padding: .5rem .9rem; font-weight: 600; cursor: pointer; }
.invalid-email-alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
.invalid-email-alert.success { background: #ecfdf5; color: #065f46; }
.invalid-email-note { color: #64748b; font-size: .82rem; margin-top: .75rem; }
@media (max-width: 768px) {
    .invalid-email-table { display: block; overflow-x: auto; }
}
</style>
@endpush

@section('content')
<div class="invalid-email-page">
    <h1>Invalid Email Management</h1>
    <p class="sub">Record Gmail “Mail Delivery Failed” addresses and manage temporary / permanent blocks. Temporary blocks use progressive cooldown (24h → 3d → 7d → 30d).</p>

    @if(session('success'))
        <div class="invalid-email-alert success">{{ session('success') }}</div>
    @endif

    <div class="invalid-email-card">
        <h2 style="margin:0 0 .75rem;font-size:1rem;">Add Invalid Email (manual bounce)</h2>
        <form method="post" action="{{ route('accounts.invalid-emails.store') }}" class="invalid-email-filters">
            @csrf
            <label>Email Address
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="bounced@example.com">
            </label>
            <label>Reason
                <select name="reason" required>
                    @foreach($reasons as $key => $label)
                        <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Notes
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Optional">
            </label>
            <button type="submit" class="btn-add">Record as Temporarily Invalid</button>
        </form>
        @error('email')<p style="color:#b91c1c;margin:.5rem 0 0;">{{ $message }}</p>@enderror
        <p class="invalid-email-note">SMTP cannot always detect later Gmail bounces automatically. Use this form when the official sender inbox receives Mail Delivery Failed.</p>
    </div>

    <div class="invalid-email-card">
        <form method="get" class="invalid-email-filters" style="margin-bottom:1rem;">
            <label>Search
                <input type="search" name="q" value="{{ $search }}" placeholder="Email or reason">
            </label>
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    <option value="temporarily_invalid" @selected($status === 'temporarily_invalid')>Temporarily Invalid</option>
                    <option value="permanently_blocked" @selected($status === 'permanently_blocked')>Permanently Blocked</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="verified" @selected($status === 'verified')>Verified</option>
                </select>
            </label>
            <button type="submit" class="btn-add">Filter</button>
        </form>

        <table class="invalid-email-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Failures</th>
                    <th>Reason</th>
                    <th>First / Last</th>
                    <th>Retry After</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->normalized_email }}</strong>
                            @if($row->notes)
                                <div style="color:#64748b;font-size:.78rem;">{{ $row->notes }}</div>
                            @endif
                        </td>
                        <td><span class="badge-status badge-{{ $row->status }}">{{ str_replace('_', ' ', $row->status) }}</span></td>
                        <td>{{ $row->failure_count }}</td>
                        <td>{{ $row->last_failure_reason ?: '—' }}</td>
                        <td>
                            <div>{{ optional($row->first_detected_at)->format('M j, Y H:i') ?: '—' }}</div>
                            <div style="color:#64748b;">{{ optional($row->last_detected_at)->format('M j, Y H:i') ?: '—' }}</div>
                        </td>
                        <td>{{ optional($row->retry_after)->format('M j, Y H:i') ?: '—' }}</td>
                        <td>
                            <div class="invalid-email-actions">
                                <form method="post" action="{{ route('accounts.invalid-emails.reset', $row) }}">
                                    @csrf
                                    <button type="submit" class="btn-reset">Reset / Unblock</button>
                                </form>
                                @if($row->status !== 'permanently_blocked')
                                    <form method="post" action="{{ route('accounts.invalid-emails.block', $row) }}">
                                        @csrf
                                        <button type="submit" class="btn-block" onclick="return confirm('Permanently block this email?')">Permanent Block</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('accounts.invalid-emails.destroy', $row) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-remove" onclick="return confirm('Remove this record?')">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:#64748b;padding:1.5rem;">No invalid email records yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem;">{{ $records->links() }}</div>
    </div>
</div>
@endsection
