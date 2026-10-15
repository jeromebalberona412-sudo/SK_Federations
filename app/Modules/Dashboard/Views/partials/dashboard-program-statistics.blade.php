<section class="program-dashboard-section">
    <div class="program-charts-grid">
        <article class="content-card program-chart-card">
            <div class="card-header">
                <div>
                    <h3>Program Schedule Status</h3>
                    <p class="program-chart-subtitle">Open and closed application schedules</p>
                </div>
                <div class="program-chart-header-tools">
                    <label class="program-status-filter">
                        <span class="visually-hidden">Filter by status</span>
                        <select id="programStatusFilter" aria-label="Filter program schedule status">
                            <option value="all">All statuses</option>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </label>
                    <span class="kk-demo-total" id="programStatusTotal" aria-live="polite">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="card-body program-chart-body">
                <div class="program-chart-canvas">
                    <canvas id="programStatusChart" role="img" aria-label="Distribution of program schedules by application status"></canvas>
                </div>
                <p class="program-chart-empty" id="programStatusEmpty" role="status" hidden>No program schedule data is available yet.</p>
            </div>
        </article>
        <article class="content-card program-chart-card">
            <div class="card-header">
                <div>
                    <h3>Programs by Barangay</h3>
                    <p class="program-chart-subtitle">Barangays with the most active schedules</p>
                </div>
                <span class="kk-demo-total" id="programBarangayTotal" aria-live="polite">
                    <span class="kk-demo-total-label">Total</span>
                    <span class="kk-demo-total-value">0</span>
                </span>
            </div>
            <div class="card-body program-chart-body">
                <div class="program-chart-canvas program-chart-canvas--bar">
                    <canvas id="programBarangayChart" role="img" aria-label="Number of active program schedules by barangay"></canvas>
                </div>
                <p class="program-chart-empty" id="programBarangayEmpty" role="status" hidden>No barangay program data is available yet.</p>
            </div>
        </article>
    </div>
</section>
