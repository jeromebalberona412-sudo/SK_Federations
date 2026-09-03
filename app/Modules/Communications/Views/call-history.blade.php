@extends('layout::app')

@section('title', 'Call history — SK OnePortal')

@push('styles')
    @vite(['app/Modules/Communications/assets/css/communication.css'])
@endpush

@section('content')
<div class="comms-history-page" id="commsCallHistory" data-calls-url="{{ route('api.communications.calls.index') }}">
    <div class="page-header">
        <div class="comms-history-header-row">
            <div>
                <h1>Call history</h1>
                <p>Voice and video calls across your conversations.</p>
            </div>
            <a href="{{ route('communications.index') }}" class="comms-link-btn">Back to Messages</a>
        </div>
    </div>
    <div class="comms-history-list" id="commsHistoryList"></div>
    <div class="comms-empty" id="commsHistoryEmpty" hidden>
        <p>No calls yet.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('commsCallHistory');
    if (!root) return;
    const list = document.getElementById('commsHistoryList');
    const empty = document.getElementById('commsHistoryEmpty');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function formatDuration(sec) {
        if (sec == null) return '—';
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    fetch(root.dataset.callsUrl, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin'
    })
        .then((r) => r.json())
        .then((data) => {
            const calls = data.calls || [];
            if (!calls.length) {
                empty.hidden = false;
                return;
            }
            list.innerHTML = calls.map((call) => {
                const peer = call.peer || {};
                const when = call.created_at ? new Date(call.created_at).toLocaleString() : '';
                return `<article class="comms-history-item">
                    <div class="comms-history-main">
                        <strong>${escapeHtml(peer.name || 'Unknown')}</strong>
                        <span>${escapeHtml(call.call_type)} · ${escapeHtml(call.direction)} · ${escapeHtml(call.status)}</span>
                    </div>
                    <div class="comms-history-meta">
                        <span>${escapeHtml(when)}</span>
                        <span>${escapeHtml(formatDuration(call.duration_seconds))}</span>
                    </div>
                </article>`;
            }).join('');
        })
        .catch(() => {
            empty.hidden = false;
            empty.innerHTML = '<p>Unable to load call history.</p>';
        });
})();
</script>
@endpush
