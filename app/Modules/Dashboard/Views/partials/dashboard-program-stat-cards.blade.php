<a href="{{ route('barangay-monitoring') }}" class="stat-card stat-card-blue" data-no-loading data-stat-key="total_programs" title="Youth programs recorded in barangay ABYIP">
    <div class="stat-card-top">
        <span class="stat-card-value">{{ number_format(data_get($programDashboard ?? [], 'stats.total_programs', 0)) }}</span>
        <span class="stat-card-icon stat-icon-blue" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
        </span>
    </div>
    <span class="stat-card-label">Total Programs</span>
</a>
