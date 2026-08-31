<div class="charts-grid-2 dash-feed-row" id="dashboardActivityApp"
     data-activities-url="{{ route('dashboard.recent-activities') }}">
    <div class="content-card dash-section-card">
        <div class="dash-section-header dash-section-header--compact">
            <div>
                <h2 class="dash-section-title">Recent Activity</h2>
                <p class="dash-section-sub">Latest system actions</p>
            </div>
            <button type="button" class="dash-view-all" id="dashActivityViewAllBtn">View all</button>
        </div>
        <div class="activity-list activity-list--dashboard" id="activityList"></div>
    </div>

    <div class="content-card dash-section-card">
        <div class="dash-section-header dash-section-header--compact">
            <div>
                <h2 class="dash-section-title"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Upcoming Events</h2>
                <p class="dash-section-sub">Calendar preview</p>
            </div>
            <a href="{{ route('calendar') }}" class="dash-view-all">View all</a>
        </div>
        <div class="events-list" id="eventsList"></div>
    </div>
</div>

<div class="dash-activity-modal" id="dashActivityModal" hidden>
    <div class="dash-activity-modal-backdrop" data-dash-activity-close></div>
    <div class="dash-activity-modal-dialog dash-activity-modal-dialog--no-header" role="dialog" aria-modal="true" aria-label="Recent activity">
        <button type="button" class="dash-activity-modal-close dash-activity-modal-close--float" data-dash-activity-close aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <button type="button" class="dash-activity-modal-fullscreen dash-activity-modal-fullscreen--float" id="dashActivityFullscreenBtn" aria-label="Enter fullscreen">
            <span class="dash-fs-icon" aria-hidden="true">□</span>
        </button>
        <div class="dash-activity-modal-body">
            <div class="activity-list activity-list--modal" id="dashActivityModalList"></div>
            <div class="dash-activity-modal-pagination" id="dashActivityModalPagination"></div>
        </div>
    </div>
</div>

<div class="dash-activity-modal" id="dashActivityDetailModal" hidden>
    <div class="dash-activity-modal-backdrop" data-dash-activity-detail-close></div>
    <div class="dash-activity-modal-dialog dash-activity-detail-dialog dash-activity-modal-dialog--no-header" role="dialog" aria-modal="true" aria-label="Activity details">
        <button type="button" class="dash-activity-modal-close dash-activity-modal-close--float" data-dash-activity-detail-close aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="dash-activity-modal-body">
            <p class="dash-activity-detail-text" id="dashActivityDetailText"></p>
            <p class="dash-activity-detail-meta" id="dashActivityDetailMeta"></p>
        </div>
    </div>
</div>
