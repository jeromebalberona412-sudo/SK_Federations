<article class="stat-card stat-card-blue" data-stat-key="total_programs" title="Non-archived programs across barangays">
    <div class="stat-card-top">
        <span class="stat-card-value">{{ number_format(data_get($programDashboard ?? [], 'stats.total_programs', 0)) }}</span>
        <span class="stat-card-icon stat-icon-blue" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
        </span>
    </div>
    <span class="stat-card-label">Total Programs</span>
</article>
<article class="stat-card stat-card-green" data-stat-key="open_programs" title="Programs open for applications">
    <div class="stat-card-top">
        <span class="stat-card-value">{{ number_format(data_get($programDashboard ?? [], 'stats.open_programs', 0)) }}</span>
        <span class="stat-card-icon stat-icon-green" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
        </span>
    </div>
    <span class="stat-card-label">Open for Applications</span>
</article>
<article class="stat-card stat-card-yellow" data-stat-key="closed_programs" title="Programs closed to applications">
    <div class="stat-card-top">
        <span class="stat-card-value">{{ number_format(data_get($programDashboard ?? [], 'stats.closed_programs', 0)) }}</span>
        <span class="stat-card-icon stat-icon-yellow" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 1 1 8 0v3"></path></svg>
        </span>
    </div>
    <span class="stat-card-label">Closed to Applications</span>
</article>
<article class="stat-card stat-card-teal" data-stat-key="upcoming_programs" title="Upcoming programs by start date">
    <div class="stat-card-top">
        <span class="stat-card-value">{{ number_format(data_get($programDashboard ?? [], 'stats.upcoming_programs', 0)) }}</span>
        <span class="stat-card-icon stat-icon-teal" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 2"></path><circle cx="12" cy="12" r="9"></circle><path d="M19 5l2-2"></path></svg>
        </span>
    </div>
    <span class="stat-card-label">Upcoming by Start Date</span>
</article>
