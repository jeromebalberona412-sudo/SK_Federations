<div class="content-card dash-audit-card" id="dashboardAuditApp" data-audit-data-url="{{ route('auditlogs.data') }}">
    <div class="card-header dash-audit-header">
        <div>
            <h3><i class="fas fa-clipboard-list" style="color:#213F99;margin-right:8px;"></i>Recent Audit Activity</h3>
            <p class="dash-audit-subtitle" id="dashAuditTableSubtitle">Latest 10 activity records across the federation portal</p>
        </div>
        <a href="{{ route('auditlogs.index') }}" class="dash-audit-view-all">View All Audit Logs</a>
    </div>
    <div class="card-body dash-audit-body">
        <div class="dash-audit-table-wrap">
            <table class="dash-audit-table" id="dashAuditLogsTable">
                <thead>
                    <tr>
                        <th class="dash-audit-col-date">Date</th>
                        <th class="dash-audit-col-time">Time</th>
                        <th class="dash-audit-col-email">Email</th>
                        <th class="dash-audit-col-role">Role</th>
                        <th class="dash-audit-col-event">Event Type</th>
                        <th class="dash-audit-col-ip">IP Address</th>
                        <th class="dash-audit-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="dashAuditLogsTableBody">
                    <tr><td colspan="7" class="dash-audit-empty-row">Loading audit logs...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="dash-audit-modal" id="dashAuditDetailsModal" hidden>
    <div class="dash-audit-modal-backdrop" data-dash-audit-close></div>
    <div class="dash-audit-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dashAuditModalTitle">
        <div class="dash-audit-modal-header">
            <div>
                <h3 id="dashAuditModalTitle">Audit Log Details</h3>
                <p id="dashAuditModalSubtitle"></p>
            </div>
            <button type="button" class="dash-audit-modal-close" data-dash-audit-close aria-label="Close details">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="dash-audit-modal-body">
            <div class="dash-audit-detail-grid" id="dashAuditDetailGrid"></div>
        </div>
    </div>
</div>
