@php
    $turnoverContext = $turnoverContext ?? [];
    $showStartNotice = $turnoverContext['show_start_notice'] ?? false;
    $showCompleteCard = $turnoverContext['show_complete_card'] ?? false;
@endphp

@if($showStartNotice)
<div class="dash-turnover-banner" id="turnoverStartNotice">
    <div class="dash-turnover-icon"><i class="fas fa-exchange-alt"></i></div>
    <div class="dash-turnover-body">
        <h3>Start Federation Turnover</h3>
        <p>The current federation term is about to end. Please register the incoming Federation President and Federation Vice President to begin the turnover process.</p>
    </div>
    <div class="dash-turnover-actions">
        <a href="{{ route('turnover.index') }}" class="btn-primary-modern">Start Turnover</a>
        <button type="button" class="btn-secondary-modern" id="turnoverRemindLaterBtn" data-remind-url="{{ route('turnover.remind-later') }}">Remind Me Later</button>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('turnoverRemindLaterBtn')?.addEventListener('click', async function () {
    const btn = this;
    try {
        const response = await fetch(btn.dataset.remindUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) throw new Error('Failed');
        document.getElementById('turnoverStartNotice')?.remove();
    } catch (e) {
        alert('Unable to dismiss notice.');
    }
});
</script>
@endpush
@endif

@if($showCompleteCard)
<div class="dash-turnover-banner dash-turnover-banner--complete" id="turnoverCompleteNotice">
    <div class="dash-turnover-icon"><i class="fas fa-check-double"></i></div>
    <div class="dash-turnover-body">
        <h3>Complete Federation Turnover</h3>
        <p>Both incoming Federation Officers have completed account setup. You may now transfer administrative access.</p>
    </div>
    <div class="dash-turnover-actions">
        <a href="{{ route('turnover.index') }}" class="btn-danger-modern">Complete Turnover</a>
    </div>
</div>
@endif
