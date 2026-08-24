// Admin Archive — Rejected KK Profiling
// Read-only monitoring view of rejected KK Profiling records from SK Officials.

document.addEventListener('DOMContentLoaded', function () {
    initAdminRejectedKK();
});

// ── Sample data mirroring SK Officials Rejected_KKProfiling records ───────────
// In production, replace with an API/AJAX call to the shared database.
const adrkkRecords = [
    {
        id: 'rkk-001',
        respondentNumber: '011',
        firstName: 'Benito',
        middleName: 'Cruz',
        lastName: 'Aquino',
        suffix: 'Jr.',
        sex: 'Male',
        age: 22,
        purokZone: 'Zone 2',
        barangay: 'VILLA GRACIA',
        youthClassification: 'Out of School Youth',
        workStatus: 'Unemployed',
        educationalBackground: 'High School Graduate',
        registeredSKVoter: 'No',
        rejectionReason: 'Incomplete requirements submitted',
        rejectedDate: 'Apr 20, 2023',
        rejectedTime: '10:20 AM',
        _rejectedTs: new Date('2023-04-20T10:20:00'),
    },
    {
        id: 'rkk-002',
        respondentNumber: '012',
        firstName: 'Carla',
        middleName: 'Reyes',
        lastName: 'Bautista',
        suffix: '',
        sex: 'Female',
        age: 19,
        purokZone: 'Zone 4',
        barangay: 'LUPANG PANGAKO',
        youthClassification: 'In School Youth',
        workStatus: 'Student',
        educationalBackground: 'College Level',
        registeredSKVoter: 'Yes',
        rejectionReason: 'Age does not meet eligibility criteria',
        rejectedDate: 'Apr 08, 2024',
        rejectedTime: '01:55 PM',
        _rejectedTs: new Date('2024-04-08T13:55:00'),
    },
    {
        id: 'rkk-003',
        respondentNumber: '013',
        firstName: 'Dante',
        middleName: '',
        lastName: 'Flores',
        suffix: '',
        sex: 'Male',
        age: 25,
        purokZone: 'Zone 7',
        barangay: 'BIGAYAN',
        youthClassification: 'Working Youth',
        workStatus: 'Employed',
        educationalBackground: 'College Graduate',
        registeredSKVoter: 'Yes',
        rejectionReason: 'Duplicate submission detected',
        rejectedDate: 'Apr 14, 2024',
        rejectedTime: '04:10 PM',
        _rejectedTs: new Date('2024-04-14T16:10:00'),
    },
    {
        id: 'rkk-004',
        respondentNumber: '020',
        firstName: 'Maria',
        middleName: 'Santos',
        lastName: 'Dela Cruz',
        suffix: '',
        sex: 'Female',
        age: 21,
        purokZone: 'Zone 1',
        barangay: 'DAMAYAN',
        youthClassification: 'In School Youth',
        workStatus: 'Student',
        educationalBackground: 'College Level',
        registeredSKVoter: 'Yes',
        rejectionReason: 'Invalid supporting documents',
        rejectedDate: 'May 05, 2025',
        rejectedTime: '09:30 AM',
        _rejectedTs: new Date('2025-05-05T09:30:00'),
    },
    {
        id: 'rkk-005',
        respondentNumber: '024',
        firstName: 'Eduardo',
        middleName: 'Lopez',
        lastName: 'Martinez',
        suffix: '',
        sex: 'Male',
        age: 20,
        purokZone: 'Zone 3',
        barangay: 'IMELDA',
        youthClassification: 'In School Youth',
        workStatus: 'Student',
        educationalBackground: 'College Level',
        registeredSKVoter: 'No',
        rejectionReason: 'Not a registered SK voter',
        rejectedDate: 'Jun 12, 2025',
        rejectedTime: '11:45 AM',
        _rejectedTs: new Date('2025-06-12T11:45:00'),
    },
    {
        id: 'rkk-006',
        respondentNumber: '028',
        firstName: 'Felicia',
        middleName: 'Garcia',
        lastName: 'Navarro',
        suffix: '',
        sex: 'Female',
        age: 18,
        purokZone: 'Zone 5',
        barangay: 'POBLACION',
        youthClassification: 'Out of School Youth',
        workStatus: 'Unemployed',
        educationalBackground: 'High School Level',
        registeredSKVoter: 'No',
        rejectionReason: 'Incomplete personal information',
        rejectedDate: 'Jul 20, 2026',
        rejectedTime: '03:30 PM',
        _rejectedTs: new Date('2026-07-20T15:30:00'),
    },
];

let adrkkFiltered = [...adrkkRecords];
let adrkkCurrentPage = 1;
const adrkkPerPage = 10;
let adrkkActiveFilter = 'all';
let adrkkSearchQ = '';
let adrkkYearFilter = 'all';
let adrkkTermFilter = 'all';

// ── Date helpers ──────────────────────────────────────────────────────────────
function adrkkNow() { return new Date(); }

function adrkkIsToday(ts) {
    const n = adrkkNow();
    return ts.getFullYear() === n.getFullYear() && ts.getMonth() === n.getMonth() && ts.getDate() === n.getDate();
}

function adrkkIsThisWeek(ts) {
    const n = adrkkNow();
    const startOfWeek = new Date(n);
    startOfWeek.setDate(n.getDate() - n.getDay());
    startOfWeek.setHours(0, 0, 0, 0);
    return ts >= startOfWeek;
}

function adrkkIsThisMonth(ts) {
    const n = adrkkNow();
    return ts.getFullYear() === n.getFullYear() && ts.getMonth() === n.getMonth();
}

function adrkkApplyTabFilter(records, filter) {
    if (filter === 'today') return records.filter(r => adrkkIsToday(r._rejectedTs));
    if (filter === 'week')  return records.filter(r => adrkkIsThisWeek(r._rejectedTs));
    if (filter === 'month') return records.filter(r => adrkkIsThisMonth(r._rejectedTs));
    return records;
}

// ── Init ──────────────────────────────────────────────────────────────────────
function initAdminRejectedKK() {
    renderAdrkkStats();
    applyAdrkkFilters();
    bindAdrkkSearch();
    bindAdrkkFilterTabs();
    bindAdrkkViewModal();
}

// ── Apply all filters ─────────────────────────────────────────────────────────
function applyAdrkkFilters() {
    let base = adrkkApplyTabFilter(adrkkRecords, adrkkActiveFilter);
    
    // Year filter
    if (adrkkYearFilter !== 'all') {
        base = base.filter(r => r._rejectedTs.getFullYear() === parseInt(adrkkYearFilter, 10));
    }
    
    // Term filter
    if (adrkkTermFilter !== 'all') {
        const [termStart, termEnd] = adrkkTermFilter.split('-').map(y => parseInt(y, 10));
        base = base.filter(r => {
            const year = r._rejectedTs.getFullYear();
            return year >= termStart && year <= termEnd;
        });
    }
    
    // Search filter
    if (adrkkSearchQ) {
        base = base.filter(r => {
            const name = `${r.firstName} ${r.middleName || ''} ${r.lastName}`.toLowerCase();
            return name.includes(adrkkSearchQ)
                || (r.purokZone || '').toLowerCase().includes(adrkkSearchQ)
                || (r.rejectionReason || '').toLowerCase().includes(adrkkSearchQ);
        });
    }
    adrkkFiltered = base;
    adrkkCurrentPage = 1;
    renderAdrkkTable();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
function renderAdrkkStats() {
    const row = document.getElementById('adrkkStatsRow');
    if (!row) return;

    const total = adrkkRecords.length;
    const month = adrkkRecords.filter(r => adrkkIsThisMonth(r._rejectedTs)).length;
    const today = adrkkRecords.filter(r => adrkkIsToday(r._rejectedTs)).length;

    row.innerHTML = `
        <div class="adrkk-stat-card adrkk-stat-card-red">
            <div class="adrkk-stat-top">
                <span class="adrkk-stat-value">${total}</span>
                <div class="adrkk-stat-icon adrkk-icon-red">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
            </div>
            <span class="adrkk-stat-label">Total Rejected</span>
        </div>
        <div class="adrkk-stat-card adrkk-stat-card-orange">
            <div class="adrkk-stat-top">
                <span class="adrkk-stat-value">${month}</span>
                <div class="adrkk-stat-icon adrkk-icon-orange">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                </div>
            </div>
            <span class="adrkk-stat-label">This Month</span>
        </div>
        <div class="adrkk-stat-card adrkk-stat-card-blue">
            <div class="adrkk-stat-top">
                <span class="adrkk-stat-value">${today}</span>
                <div class="adrkk-stat-icon adrkk-icon-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <span class="adrkk-stat-label">Today</span>
        </div>`;
}

// ── Filter Tabs ───────────────────────────────────────────────────────────────
function bindAdrkkFilterTabs() {
    document.querySelectorAll('.adrkk-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.adrkk-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            adrkkActiveFilter = this.dataset.filter;
            const labels = { all: 'All Rejected Records', today: 'Rejected Today', week: 'Rejected This Week', month: 'Rejected This Month' };
            const label = document.getElementById('adrkkSectionLabel');
            if (label) label.textContent = labels[adrkkActiveFilter] || 'Rejected Records';
            applyAdrkkFilters();
        });
    });
}

// ── Search ────────────────────────────────────────────────────────────────────
function bindAdrkkSearch() {
    const input = document.getElementById('adrkkSearch');
    const yearSelect = document.getElementById('adrkkYearFilter');
    const termSelect = document.getElementById('adrkkTermFilter');
    
    if (input) {
        input.addEventListener('input', function () {
            adrkkSearchQ = this.value.toLowerCase();
            applyAdrkkFilters();
        });
    }
    
    if (yearSelect) {
        yearSelect.addEventListener('change', function () {
            adrkkYearFilter = this.value;
            applyAdrkkFilters();
        });
    }
    
    if (termSelect) {
        termSelect.addEventListener('change', function () {
            adrkkTermFilter = this.value;
            applyAdrkkFilters();
        });
    }
}

// ── Render Table ──────────────────────────────────────────────────────────────
function renderAdrkkTable() {
    const tbody = document.getElementById('adrkkTableBody');
    const info  = document.getElementById('adrkkPaginationInfo');
    if (!tbody) return;

    const start = (adrkkCurrentPage - 1) * adrkkPerPage;
    const end   = start + adrkkPerPage;
    const page  = adrkkFiltered.slice(start, end);

    if (adrkkFiltered.length === 0) {
        tbody.innerHTML = `<tr class="adrkk-empty-row"><td colspan="9">No rejected KK Profiling records found.</td></tr>`;
        if (info) info.textContent = 'No records found';
        renderAdrkkPagination(0);
        return;
    }

    tbody.innerHTML = page.map(r => {
        const fullName = `${r.lastName}, ${r.firstName}${r.middleName ? ' ' + r.middleName : ''}${r.suffix ? ' ' + r.suffix : ''}`;
        return `
        <tr>
            <td style="font-weight:600;color:#111827;text-align:left;">${fullName}</td>
            <td>${r.age || '—'}</td>
            <td>${r.sex || '—'}</td>
            <td>${r.purokZone || '—'}</td>
            <td>${r.youthClassification || '—'}</td>
            <td><span class="adrkk-reason-cell" title="${r.rejectionReason}">${r.rejectionReason}</span></td>
            <td><span class="adrkk-rejected-badge">${r.rejectedDate}</span></td>
            <td><span class="adrkk-time-badge">${r.rejectedTime}</span></td>
            <td>
                <div class="adrkk-action-btns">
                    <button class="adrkk-btn-view" data-id="${r.id}" aria-label="View details">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `Showing ${start + 1}–${Math.min(end, adrkkFiltered.length)} of ${adrkkFiltered.length} records`;

    renderAdrkkPagination(adrkkFiltered.length);

    tbody.querySelectorAll('.adrkk-btn-view').forEach(btn => {
        btn.addEventListener('click', function () { openAdrkkViewModal(this.dataset.id); });
    });
}

// ── Pagination ────────────────────────────────────────────────────────────────
function renderAdrkkPagination(total) {
    const pages = Math.ceil(total / adrkkPerPage);
    const nums  = document.getElementById('adrkkPageNumbers');
    const prev  = document.getElementById('adrkkPrevBtn');
    const next  = document.getElementById('adrkkNextBtn');

    if (nums) {
        nums.innerHTML = Array.from({ length: pages }, (_, i) => `
            <button class="adrkk-page-btn ${i + 1 === adrkkCurrentPage ? 'active' : ''}">${i + 1}</button>
        `).join('');
        nums.querySelectorAll('.adrkk-page-btn').forEach((btn, i) => {
            btn.addEventListener('click', () => { adrkkCurrentPage = i + 1; renderAdrkkTable(); });
        });
    }
    if (prev) { prev.disabled = adrkkCurrentPage === 1; prev.onclick = () => { adrkkCurrentPage--; renderAdrkkTable(); }; }
    if (next) { next.disabled = adrkkCurrentPage >= pages || pages === 0; next.onclick = () => { adrkkCurrentPage++; renderAdrkkTable(); }; }
}

// ── View Modal ────────────────────────────────────────────────────────────────
function openAdrkkViewModal(id) {
    const r = adrkkRecords.find(x => x.id === id);
    if (!r) return;

    const body = document.getElementById('adrkkViewBody');
    if (body) {
        const fullName = `${r.lastName}, ${r.firstName}${r.middleName ? ' ' + r.middleName : ''}${r.suffix ? ' ' + r.suffix : ''}`;
        body.innerHTML = `
            <div class="adrkk-readonly-notice">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Admin view only — this record was rejected by SK Officials.
            </div>
            <div class="adrkk-view-section-block">
                <div class="adrkk-view-section-header">
                    <span class="adrkk-view-section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a8.38 8.38 0 0 1 13 0"/></svg>
                    </span>
                    <span class="adrkk-view-section-label">Personal Information</span>
                </div>
                <div class="adrkk-view-info-grid">
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Full Name</span>
                        <span class="adrkk-view-value adrkk-view-fullname">${fullName}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Age</span>
                        <span class="adrkk-view-value">${r.age || '—'}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Sex</span>
                        <span class="adrkk-view-value">${r.sex || '—'}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Purok / Zone</span>
                        <span class="adrkk-view-value">${r.purokZone || '—'}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Barangay</span>
                        <span class="adrkk-view-value">${r.barangay || '—'}</span>
                    </div>
                </div>
            </div>
            <div class="adrkk-view-section-block">
                <div class="adrkk-view-section-header">
                    <span class="adrkk-view-section-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.657 2.686 3 6 3s6-1.343 6-3v-5"/></svg>
                    </span>
                    <span class="adrkk-view-section-label">Classification & Status</span>
                </div>
                <div class="adrkk-view-info-grid">
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Youth Classification</span>
                        <span class="adrkk-badge adrkk-badge-blue">${r.youthClassification || '—'}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Work Status</span>
                        <span class="adrkk-badge adrkk-badge-green">${r.workStatus || '—'}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Education</span>
                        <span class="adrkk-view-value">${r.educationalBackground || '—'}</span>
                    </div>
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Registered SK Voter</span>
                        <span class="adrkk-view-value">${r.registeredSKVoter || '—'}</span>
                    </div>
                </div>
            </div>
            <div class="adrkk-rejection-block">
                <div class="adrkk-rejection-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span class="adrkk-rejection-label">Rejection Details</span>
                </div>
                <div class="adrkk-rejection-body">
                    <div class="adrkk-view-field">
                        <span class="adrkk-view-label">Reason</span>
                        <p class="adrkk-rejection-reason-text">${r.rejectionReason || '—'}</p>
                    </div>
                    <div class="adrkk-rejection-dates">
                        <div class="adrkk-view-field">
                            <span class="adrkk-view-label">Rejected Date</span>
                            <span class="adrkk-view-value-danger">${r.rejectedDate}</span>
                        </div>
                        <div class="adrkk-view-field">
                            <span class="adrkk-view-label">Rejected Time</span>
                            <span class="adrkk-view-value-danger">${r.rejectedTime}</span>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    const modal = document.getElementById('adrkkViewModal');
    if (modal) modal.style.display = 'flex';
}

function bindAdrkkViewModal() {
    const modal     = document.getElementById('adrkkViewModal');
    const box       = document.getElementById('adrkkViewModalBox');
    const closeBtn  = document.getElementById('adrkkViewClose');
    const toggleBtn = document.getElementById('adrkkViewToggle');

    const close = () => {
        if (modal) { modal.style.display = 'none'; modal.classList.remove('adrkk-maximized'); }
        if (box)   box.classList.remove('adrkk-maximized');
        if (toggleBtn) toggleBtn.textContent = '□';
    };

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (modal)    modal.addEventListener('click', e => { if (e.target === modal) close(); });

    if (toggleBtn && box) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isMax = !box.classList.contains('adrkk-maximized');
            modal.classList.toggle('adrkk-maximized', isMax);
            box.classList.toggle('adrkk-maximized', isMax);
            toggleBtn.textContent = isMax ? '⧉' : '□';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') close();
    });
}
