<section class="kk-demo-block" id="kkDemographicsSection" aria-label="Youth demographics">
    <p class="dash-empty-msg" id="kkDemoEmptyMsg" @if(!empty($youthDemographics['has_data'])) hidden @endif>No KK Profiling data available yet.</p>

    <div class="kk-demo-charts-grid" id="kkDemoChartsGrid">
        <article class="kk-demo-chart-card">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Civil Status</h3>
                    <p class="dash-section-sub">Youth by civil status</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoCivil" class="dash-filter-select" aria-label="Filter civil status">
                        <option value="all" selected>All</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Separated">Separated</option>
                        <option value="Annulled">Annulled</option>
                        <option value="Unknown">Unknown</option>
                        <option value="Live-in">Live-in</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoCivilTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-demo-chart-wrap">
                <canvas id="chartDemoCivilStatus" aria-label="Civil status donut chart"></canvas>
            </div>
            <div class="donut-legend" id="kkDemoCivilLegend"></div>
        </article>

        <article class="kk-demo-chart-card">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Youth Age Group</h3>
                    <p class="dash-section-sub">Child, Core, and Young Adult</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoAge" class="dash-filter-select" aria-label="Filter youth age group">
                        <option value="all" selected>All</option>
                        <option value="Child Youth (15-17 yrs old)">Child Youth (15-17 yrs old)</option>
                        <option value="Core Youth (18-24 yrs old)">Core Youth (18-24 yrs old)</option>
                        <option value="Young Adult (25-30 yrs old)">Young Adult (25-30 yrs old)</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoAgeTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-demo-chart-wrap kk-demo-chart-wrap--bar">
                <canvas id="chartDemoAgeGroup" aria-label="Youth age group bar chart"></canvas>
            </div>
        </article>

        <article class="kk-demo-chart-card kk-demo-chart-card--wide">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Educational Background</h3>
                    <p class="dash-section-sub">Highest educational attainment</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoEducation" class="dash-filter-select" aria-label="Filter educational background">
                        <option value="all" selected>All</option>
                        <option value="Elementary Level">Elementary Level</option>
                        <option value="Elementary Grad">Elementary Grad</option>
                        <option value="High school level">High school level</option>
                        <option value="High school Grad">High school Grad</option>
                        <option value="Vocational Grad">Vocational Grad</option>
                        <option value="College Level">College Level</option>
                        <option value="College Grad">College Grad</option>
                        <option value="Masters Level">Masters Level</option>
                        <option value="Masters Grad">Masters Grad</option>
                        <option value="Doctorate Level">Doctorate Level</option>
                        <option value="Doctorate Graduate">Doctorate Graduate</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoEducationTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-edu-bars" id="kkDemoEducationList" role="list" aria-label="Educational background bars"></div>
        </article>

        <article class="kk-demo-chart-card">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Youth Classification</h3>
                    <p class="dash-section-sub">In school, OSY, working, specific needs</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoClassification" class="dash-filter-select" aria-label="Filter youth classification">
                        <option value="all" selected>All</option>
                        <option value="In school Youth">In school Youth</option>
                        <option value="Out of School Youth">Out of School Youth</option>
                        <option value="Working Youth">Working Youth</option>
                        <option value="Youth w/ Specific Needs">Youth w/ Specific Needs</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoClassificationTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-demo-chart-wrap kk-demo-chart-wrap--bar">
                <canvas id="chartDemoClassification" aria-label="Youth classification bar chart"></canvas>
            </div>
        </article>

        <article class="kk-demo-chart-card">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Youth With Specific Needs</h3>
                    <p class="dash-section-sub">PWD, CICL, and Indigenous People</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoNeeds" class="dash-filter-select" aria-label="Filter specific needs">
                        <option value="all" selected>All</option>
                        <option value="Person w/ Disability">Person w/ Disability</option>
                        <option value="Children In Conflict w/ Law">Children In Conflict w/ Law</option>
                        <option value="Indigenous People">Indigenous People</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoNeedsTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-demo-chart-wrap kk-demo-chart-wrap--bar">
                <canvas id="chartDemoSpecificNeeds" aria-label="Youth with specific needs bar chart"></canvas>
            </div>
        </article>

        <article class="kk-demo-chart-card">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Registered SK Voter</h3>
                    <p class="dash-section-sub">Yes vs No</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoSkVoter" class="dash-filter-select" aria-label="Filter SK voter">
                        <option value="all" selected>All</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoSkVoterTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-demo-chart-wrap">
                <canvas id="chartDemoSkVoter" aria-label="Registered SK voter donut chart"></canvas>
            </div>
            <div class="donut-legend" id="kkDemoSkVoterLegend"></div>
        </article>

        <article class="kk-demo-chart-card">
            <div class="dash-section-header">
                <div class="dash-section-header-text">
                    <h3 class="dash-section-title">Registered National Voter</h3>
                    <p class="dash-section-sub">Yes vs No</p>
                </div>
                <div class="dash-chart-header-actions">
                    <select id="filterDemoNationalVoter" class="dash-filter-select" aria-label="Filter national voter">
                        <option value="all" selected>All</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                    <span class="kk-demo-total" id="kkDemoNationalVoterTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
            </div>
            <div class="kk-demo-chart-wrap">
                <canvas id="chartDemoNationalVoter" aria-label="Registered national voter donut chart"></canvas>
            </div>
            <div class="donut-legend" id="kkDemoNationalVoterLegend"></div>
        </article>
    </div>
</section>
