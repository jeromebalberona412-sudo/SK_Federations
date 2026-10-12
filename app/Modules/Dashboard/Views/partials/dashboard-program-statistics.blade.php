<section class="program-dashboard-section" aria-labelledby="programStatisticsTitle">
    <div class="program-dashboard-heading">
        <div>
            <h2 id="programStatisticsTitle">Program Statistics</h2>
            <p>Counts cover non-archived schedules across your barangays. Open/closed reflects application availability, not implementation progress.</p>
        </div>
    </div>
    <div class="program-charts-grid">
        <article class="content-card program-chart-card">
            <div class="card-header">
                <div>
                    <h3>Program Schedule Status</h3>
                    <p class="program-chart-subtitle">Open and closed application schedules</p>
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
