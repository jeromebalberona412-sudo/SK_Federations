// Admin Archive — Rejected Scholarships
// Read-only monitoring view of rejected scholarship applications from SK Officials.
// Reads from the same localStorage key used by SK Officials scholarship_requests.js

document.addEventListener('DOMContentLoaded', function () {
    initAdminRejectedScholarships();
});

// ── State ─────────────────────────────────────────────────────────────────────
let adrscAllRecords  = [];
let adrscFiltered    = [];
let adrscCurrentPage = 1;
const adrscPerPage   = 10;
let adrscActiveFilter = 'all';
let adrscSearchQ      = '';
let adrscYearFilter   = 'all';
let adrscTermFilter   = 'all';

// ── Date helpers ──────────────────────────────────────────────────────────────
function adrscNow() { return new Date(); }

function adrscIsToday(ts) {
    const n = adrscNow();
    return ts.getFullYear() === n.getFullYear() && ts.getMonth() === n.getMonth() && ts.getDate() === n.getDate();
}

function adrscIsThisWeek(ts) {
    const n = adrscNow();
    const startOfWeek = new Date(n);
    startOfWeek.setDate(n.getDate() - n.getDay());
    startOfWeek.setHours(0, 0, 0, 0);
    return ts >= startOfWeek;
}

function adrscIsThisMonth(ts) {
    const n = adrscNow();
    return ts.getFullYear() === n.getFullYear() && ts.getMonth() === n.getMonth();
}

function adrscParseDate(str) {
    if (!str) return new Date(0);
    const d = new Date(str);
    return isNaN(d.getTime()) ? new Date(0) : d;
}

function adrscApplyTabFilter(records, filter) {
    if (filter === 'today') return records.filter(r => adrscIsToday(adrscParseDate(r.submitted_at)));
    if (filter === 'week')  return records.filter(r => adrscIsThisWeek(adrscParseDate(r.submitted_at)));
    if (filter === 'month') return records.filter(r => adrscIsThisMonth(adrscParseDate(r.submitted_at)));
    return records;
}

// ── Init ──────────────────────────────────────────────────────────────────────
function initAdminRejectedScholarships() {
    loadAdrscRecords();
    renderAdrscStats();
    applyAdrscFilters();
    bindAdrscSearch();
    bindAdrscFilterTabs();
    bindAdrscViewModal();
}

// ── Load from localStorage (shared with SK Officials) ─────────────────────────
function loadAdrscRecords() {
    const all = JSON.parse(localStorage.getItem('scholarship_requests') || '[]');
    adrscAllRecords = all.filter(r => r.status === 'Rejected');
}

// ── Apply all filters ─────────────────────────────────────────────────────────
function applyAdrscFilters() {
    let base = adrscApplyTabFilter(adrscAllRecords, adrscActiveFilter);
    
    // Year filter
    if (adrscYearFilter !== 'all') {
        base = base.filter(r => {
            const date = adrscParseDate(r.submitted_at);
            return date && date.getFullYear() === parseInt(adrscYearFilter, 10);
        });
    }
    
    // Term filter
    if (adrscTermFilter !== 'all') {
        const [termStart, termEnd] = adrscTermFilter.split('-').map(y => parseInt(y, 10));
        base = base.filter(r => {
            const date = adrscParseDate(r.submitted_at);
            if (!date) return false;
            const year = date.getFullYear();
            return year >= termStart && year <= termEnd;
        });
    }
    
    // Search filter
    if (adrscSearchQ) {
        base = base.filter(r => {
            const name   = `${r.last_name || ''} ${r.first_name || ''}`.toLowerCase();
            const school = (r.school_name || '').toLowerCase();
            return name.includes(adrscSearchQ) || school.includes(adrscSearchQ);
        });
    }
    adrscFiltered = base;
    adrscCurrentPage = 1;
    renderAdrscTable();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
function renderAdrscStats() {
    const row = document.getElementById('adrscStatsRow');
    if (!row) return;

    const total = adrscAllRecords.length;
    const month = adrscAllRecords.filter(r => adrscIsThisMonth(adrscParseDate(r.submitted_at))).length;
    const today = adrscAllRecords.filter(r => adrscIsToday(adrscParseDate(r.submitted_at))).length;

    row.innerHTML = `
        <div class="adrsc-stat-card adrsc-stat-card-red">
            <div class="adrsc-stat-top">
                <span class="adrsc-stat-value">${total}</span>
                <div class="adrsc-stat-icon adrsc-icon-red">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
            </div>
            <span class="adrsc-stat-label">Total Rejected</span>
        </div>
        <div class="adrsc-stat-card adrsc-stat-card-orange">
            <div class="adrsc-stat-top">
                <span class="adrsc-stat-value">${month}</span>
                <div class="adrsc-stat-icon adrsc-icon-orange">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                </div>
            </div>
            <span class="adrsc-stat-label">This Month</span>
        </div>
        <div class="adrsc-stat-card adrsc-stat-card-blue">
            <div class="adrsc-stat-top">
                <span class="adrsc-stat-value">${today}</span>
                <div class="adrsc-stat-icon adrsc-icon-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <span class="adrsc-stat-label">Today</span>
        </div>`;
}

// ── Filter Tabs ───────────────────────────────────────────────────────────────
function bindAdrscFilterTabs() {
    document.querySelectorAll('.adrsc-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.adrsc-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            adrscActiveFilter = this.dataset.filter;
            const labels = { all: 'All Rejected Records', today: 'Rejected Today', week: 'Rejected This Week', month: 'Rejected This Month' };
            const label = document.getElementById('adrscSectionLabel');
            if (label) label.textContent = labels[adrscActiveFilter] || 'Rejected Records';
            applyAdrscFilters();
        });
    });
}

// ── Search ────────────────────────────────────────────────────────────────────
function bindAdrscSearch() {
    const input = document.getElementById('adrscSearch');
    const yearSelect = document.getElementById('adrscYearFilter');
    const termSelect = document.getElementById('adrscTermFilter');
    
    if (input) {
        input.addEventListener('input', function () {
            adrscSearchQ = this.value.toLowerCase();
            applyAdrscFilters();
        });
    }
    
    if (yearSelect) {
        yearSelect.addEventListener('change', function () {
            adrscYearFilter = this.value;
            applyAdrscFilters();
        });
    }
    
    if (termSelect) {
        termSelect.addEventListener('change', function () {
            adrscTermFilter = this.value;
            applyAdrscFilters();
        });
    }
}

// ── Render Table ──────────────────────────────────────────────────────────────
function renderAdrscTable() {
    const tbody = document.getElementById('adrscTableBody');
    const info  = document.getElementById('adrscPaginationInfo');
    if (!tbody) return;

    const start = (adrscCurrentPage - 1) * adrscPerPage;
    const end   = start + adrscPerPage;
    const page  = adrscFiltered.slice(start, end);

    if (adrscFiltered.length === 0) {
        tbody.innerHTML = `<tr class="adrsc-empty-row"><td colspan="6">No rejected scholarship applications found.</td></tr>`;
        if (info) info.textContent = 'No records found';
        renderAdrscPagination(0);
        return;
    }

    tbody.innerHTML = page.map((r, idx) => {
        const name = `${r.last_name || ''}, ${r.first_name || ''}${r.middle_name ? ' ' + r.middle_name.charAt(0) + '.' : ''}`;
        return `
        <tr>
            <td>${start + idx + 1}</td>
            <td style="text-align:left;font-weight:600;color:#111827;">${name}</td>
            <td style="text-align:left;font-size:12px;">${r.school_name || '—'}</td>
            <td><span class="adrsc-status-pill">Rejected</span></td>
            <td><span class="adrsc-date-badge">${r.submitted_at || '—'}</span></td>
            <td>
                <div class="adrsc-action-btns">
                    <button class="adrsc-btn-view" data-id="${r.id}" aria-label="View application details">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `Showing ${start + 1}–${Math.min(end, adrscFiltered.length)} of ${adrscFiltered.length} records`;

    renderAdrscPagination(adrscFiltered.length);

    tbody.querySelectorAll('.adrsc-btn-view').forEach(btn => {
        btn.addEventListener('click', function () { openAdrscViewModal(parseInt(this.dataset.id, 10)); });
    });
}

// ── Pagination ────────────────────────────────────────────────────────────────
function renderAdrscPagination(total) {
    const pages = Math.ceil(total / adrscPerPage);
    const nums  = document.getElementById('adrscPageNumbers');
    const prev  = document.getElementById('adrscPrevBtn');
    const next  = document.getElementById('adrscNextBtn');

    if (nums) {
        nums.innerHTML = Array.from({ length: pages }, (_, i) => `
            <button class="adrsc-page-btn ${i + 1 === adrscCurrentPage ? 'active' : ''}">${i + 1}</button>
        `).join('');
        nums.querySelectorAll('.adrsc-page-btn').forEach((btn, i) => {
            btn.addEventListener('click', () => { adrscCurrentPage = i + 1; renderAdrscTable(); });
        });
    }
    if (prev) { prev.disabled = adrscCurrentPage === 1; prev.onclick = () => { adrscCurrentPage--; renderAdrscTable(); }; }
    if (next) { next.disabled = adrscCurrentPage >= pages || pages === 0; next.onclick = () => { adrscCurrentPage++; renderAdrscTable(); }; }
}

// ── View Modal ────────────────────────────────────────────────────────────────
function openAdrscViewModal(id) {
    const all = JSON.parse(localStorage.getItem('scholarship_requests') || '[]');
    const r   = all.find(x => x.id === id);
    if (!r) return;

    const body = document.getElementById('adrscViewBody');
    if (!body) return;

    const allPurposes = ['Tuition Fees', 'Books/Equipments', 'Living Expenses', 'Others'];
    const purposeList = r.purpose_list || [];

    const purposeHTML = allPurposes.map(p => {
        const checked = purposeList.some(v => v.toLowerCase().replace(/\s/g, '') === p.toLowerCase().replace(/\s/g, ''));
        const extra   = (p === 'Others' && r.purpose_others) ? ` (${r.purpose_others})` : '';
        return `<div class="adrsc-pdf-check-item">
            <span class="adrsc-pdf-checkbox ${checked ? 'adrsc-pdf-checked' : ''}"></span>
            ${p}${extra}
        </div>`;
    }).join('');

    const f = (val, w) => `<span class="adrsc-pdf-inline-filled" style="min-width:${w || 80}px;">${val || '—'}</span>`;

    body.innerHTML = `
        <div class="adrsc-readonly-notice">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Admin view only — this application was rejected by SK Officials.
        </div>
        <div class="adrsc-rejected-banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            This application was rejected on ${r.submitted_at || '—'}.
        </div>

        <div class="adrsc-pdf-form">

            <div class="adrsc-pdf-header">
                <img src="/images/barangay_logo.png" alt="Barangay Logo" class="adrsc-pdf-logo-img">
                <h2 class="adrsc-pdf-title">SCHOLARSHIP APPLICATION FORM</h2>
                <div class="adrsc-pdf-picture-box"><span>Picture<br>Here</span></div>
            </div>

            <div class="adrsc-pdf-section">
                <p class="adrsc-pdf-inline-title">APPLICANT'S PERSONAL INFORMATION:</p>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">Last Name:</span>${f(r.last_name, 110)}
                    <span class="adrsc-pdf-inline-label">First Name:</span>${f(r.first_name, 110)}
                    <span class="adrsc-pdf-inline-label">Middle Name:</span>${f(r.middle_name, 100)}
                </div>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">Date of Birth:</span>${f(r.date_of_birth, 90)}
                    <span class="adrsc-pdf-inline-label">Gender:</span>${f(r.gender, 70)}
                    <span class="adrsc-pdf-inline-label">Age:</span>${f(r.age, 40)}
                    <span class="adrsc-pdf-inline-label">Contact No:</span>${f(r.contact_no, 110)}
                </div>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">Complete Address:</span>
                    <span class="adrsc-pdf-inline-filled" style="flex:1;">${r.address || '—'}</span>
                </div>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">Email Address:</span>
                    <span class="adrsc-pdf-inline-filled" style="min-width:200px;">${r.email || '—'}</span>
                </div>
            </div>

            <div class="adrsc-pdf-section">
                <p class="adrsc-pdf-inline-title">ACADEMIC INFORMATION:</p>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">Name of School:</span>
                    <span class="adrsc-pdf-inline-filled" style="flex:1;">${r.school_name || '—'}</span>
                </div>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">School Address:</span>
                    <span class="adrsc-pdf-inline-filled" style="flex:1;">${r.school_address || '—'}</span>
                </div>
                <div class="adrsc-pdf-inline-row">
                    <span class="adrsc-pdf-inline-label">Year/Grade Level:</span>${f(r.year_level, 110)}
                    <span class="adrsc-pdf-inline-label" style="margin-left:14px;">Program/Strand:</span>${f(r.program_strand, 110)}
                </div>
            </div>

            <div class="adrsc-pdf-section adrsc-pdf-bottom-section">
                <div class="adrsc-pdf-bottom-left">
                    <p class="adrsc-pdf-inline-title">SCHOLARSHIP INFORMATION:</p>
                    <p class="adrsc-pdf-purpose-label">Purpose of Scholarship:</p>
                    <div class="adrsc-pdf-check-list">${purposeHTML}</div>
                </div>
                <div class="adrsc-pdf-bottom-right">
                    <p class="adrsc-pdf-inline-title">SUBMITTED REQUIREMENTS:</p>
                    <div class="adrsc-pdf-check-list" style="margin-top:8px;">
                        <div class="adrsc-pdf-check-item">
                            <span class="adrsc-pdf-checkbox ${r.cor_certified ? 'adrsc-pdf-checked' : ''}"></span>
                            COR – CERTIFIED TRUE COPY
                        </div>
                        <div class="adrsc-pdf-check-item">
                            <span class="adrsc-pdf-checkbox ${r.photo_id ? 'adrsc-pdf-checked' : ''}"></span>
                            PHOTO COPY OF ID (FRONT AND BACK)
                        </div>
                    </div>
                </div>
            </div>

            <div class="adrsc-pdf-sig-section">
                <div class="adrsc-pdf-sig-line"></div>
                <p class="adrsc-pdf-sig-label">${r.first_name || ''} ${r.middle_name ? r.middle_name + ' ' : ''}${r.last_name || ''}</p>
            </div>

        </div>
    `;

    const modal = document.getElementById('adrscViewModal');
    if (modal) modal.style.display = 'flex';
}

function bindAdrscViewModal() {
    const modal     = document.getElementById('adrscViewModal');
    const box       = document.getElementById('adrscViewModalBox');
    const closeBtn  = document.getElementById('adrscViewClose');
    const toggleBtn = document.getElementById('adrscViewToggle');

    const close = () => {
        if (modal) { modal.style.display = 'none'; modal.classList.remove('adrsc-maximized'); }
        if (box)   box.classList.remove('adrsc-maximized');
        if (toggleBtn) toggleBtn.textContent = '□';
    };

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (modal)    modal.addEventListener('click', e => { if (e.target === modal) close(); });

    if (toggleBtn && box) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isMax = !box.classList.contains('adrsc-maximized');
            modal.classList.toggle('adrsc-maximized', isMax);
            box.classList.toggle('adrsc-maximized', isMax);
            toggleBtn.textContent = isMax ? '⧉' : '□';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') close();
    });
}
