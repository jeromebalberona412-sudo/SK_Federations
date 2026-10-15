<div class="charts-grid-2 dash-feed-row" id="dashboardActivityApp"
     data-activities-url="{{ route('dashboard.recent-activities') }}">
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
    <div class="dash-activity-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dashActivityModalTitle">
        <div class="dash-activity-modal-header">
            <h3 id="dashActivityModalTitle">All Recent Activity</h3>
            <button type="button" class="dash-activity-modal-close" data-dash-activity-close data-no-loading aria-label="Close" onclick="window.closeDashActivityModal(event)">&times;</button>
        </div>
        <div class="dash-activity-modal-body">
            <div class="activity-list activity-list--modal" id="dashActivityModalList"></div>
            <div class="dash-activity-modal-pagination" id="dashActivityModalPagination"></div>
        </div>
        <div class="dash-activity-modal-footer">
            <button type="button" class="dash-activity-btn-cancel" data-dash-activity-close data-no-loading onclick="window.closeDashActivityModal(event)">Close</button>
        </div>
    </div>
</div>

<div class="dash-activity-modal dash-activity-modal--front" id="dashActivityDetailModal" hidden>
    <div class="dash-activity-modal-backdrop" data-dash-activity-detail-close></div>
    <div class="dash-activity-modal-dialog dash-activity-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="dashActivityDetailTitle">
        <div class="dash-activity-modal-header">
            <h3 id="dashActivityDetailTitle">Activity Details</h3>
            <button type="button" class="dash-activity-modal-close" data-dash-activity-detail-close data-no-loading aria-label="Close" onclick="window.closeDashActivityDetailModal(event)">&times;</button>
        </div>
        <div class="dash-activity-modal-body">
            <div class="activity-detail">
                <p class="activity-detail-text" id="dashActivityDetailText"></p>
                <div class="activity-detail-meta">
                    <div class="activity-detail-meta-left">
                        <span class="activity-detail-who" id="dashActivityDetailWho"></span>
                        <span class="activity-detail-position" id="dashActivityDetailPosition"></span>
                    </div>
                    <span class="activity-detail-time" id="dashActivityDetailTime"></span>
                </div>
            </div>
        </div>
        <div class="dash-activity-modal-footer">
            <button type="button" class="dash-activity-btn-cancel" data-dash-activity-detail-close data-no-loading onclick="window.closeDashActivityDetailModal(event)">Close</button>
        </div>
    </div>
</div>
