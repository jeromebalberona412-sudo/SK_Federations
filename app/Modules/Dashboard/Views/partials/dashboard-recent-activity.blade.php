<div class="charts-grid-2 dash-feed-row" id="dashboardActivityApp"
     data-activities-url="{{ route('dashboard.recent-activities') }}">
    <div class="content-card dash-section-card">
        <div class="dash-section-header">
            <div>
                <h2 class="dash-section-title">Recent Activity</h2>
                <p class="dash-section-sub">Latest system actions</p>
            </div>
            <button type="button" class="dash-view-all" id="dashActivityViewAllBtn">View all</button>
        </div>
        <div class="activity-list activity-list--dashboard" id="activityList"></div>
    </div>

    <div class="content-card dash-section-card">
        <div class="dash-section-header">
            <div>
                <h2 class="dash-section-title">Upcoming Events</h2>
                <p class="dash-section-sub">Calendar preview</p>
            </div>
            <a href="{{ route('calendar') }}" class="dash-view-all">View all</a>
        </div>
        <div class="events-list" id="eventsList"></div>
    </div>
</div>

<div class="dash-activity-modal" id="dashActivityModal" hidden>
    <div class="dash-activity-modal-backdrop" data-dash-activity-close></div>
    <div class="dash-activity-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dashActivityModalTitle">
        <div class="dash-activity-modal-header">
            <div>
                <h3 id="dashActivityModalTitle">Recent Activity</h3>
                <p id="dashActivityModalSubtitle">Complete federation activity history</p>
            </div>
            <div class="dash-activity-modal-actions">
                <button type="button" class="dash-activity-modal-fullscreen" id="dashActivityFullscreenBtn" aria-label="Enter fullscreen">
                    <span class="dash-fs-icon" aria-hidden="true">□</span>
                </button>
                <button type="button" class="dash-activity-modal-close" data-dash-activity-close aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="dash-activity-modal-body">
            <div class="activity-list activity-list--modal" id="dashActivityModalList"></div>
            <div class="dash-activity-modal-pagination" id="dashActivityModalPagination"></div>
        </div>
    </div>
</div>
