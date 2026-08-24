// Archived SK Officials Records Module

document.addEventListener('DOMContentLoaded', function () {
    initArchivedSkOfficials();
});

function formatRecordName(record) {
    return [record.lastName, record.firstName, record.middleName, record.suffix]
        .filter((part) => part && String(part).trim() !== '')
        .join(', ');
}

const AROFF_API = {
    data: '/manage-archive/sk-officials-records/data',
};

let aroffRecords = [];
let aroffFiltered = [];
let aroffIsLoading = false;
let aroffCurrentPage = 1;
let aroffPerPage = 10;
let aroffSearchQ = '';
let aroffYearFilter = 'all';
let aroffTermFilter = 'all';

// ── Init ──────────────────────────────────────────────────────────────────────
const AROFF_POLL_MS = 20000;

function initArchivedSkOfficials() {
    bindAroffSearch();
    bindAroffPagination();
    bindAroffViewModal();
    loadAroffRecords();
    startAroffRealtimeRefresh();
}

function startAroffRealtimeRefresh() {
    window.setInterval(() => {
        if (!document.hidden) {
            loadAroffRecords();
        }
    }, AROFF_POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            loadAroffRecords();
        }
    });
}

async function loadAroffRecords() {
    if (aroffIsLoading) return;
    aroffIsLoading = true;

    const params = new URLSearchParams({
        search: aroffSearchQ,
        year: aroffYearFilter,
        term: aroffTermFilter,
    });

    try {
        const response = await fetch(`${AROFF_API.data}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load archived records.');

        aroffRecords = payload.data || [];
        aroffFiltered = [...aroffRecords];
        populateAroffFilters(payload.filters || {});
        const pages = Math.max(1, Math.ceil(aroffFiltered.length / aroffPerPage) || 1);
        if (aroffCurrentPage > pages) {
            aroffCurrentPage = pages;
        }
        renderAroffTable();
    } catch (error) {
        aroffRecords = [];
        aroffFiltered = [];
        renderAroffTable();
    } finally {
        aroffIsLoading = false;
    }
}

function populateAroffFilters(filters) {
    const yearSelect = document.getElementById('aroffYearFilter');
    const termSelect = document.getElementById('aroffTermFilter');

    if (yearSelect && Array.isArray(filters.years)) {
        const currentYear = yearSelect.value;
        const yearOptions = ['<option value="all">All Years</option>']
            .concat(filters.years.map((year) => `<option value="${year}">${year}</option>`));
        yearSelect.innerHTML = yearOptions.join('');
        if ([...yearSelect.options].some((option) => option.value === currentYear)) {
            yearSelect.value = currentYear;
        }
    }

    if (!termSelect || !Array.isArray(filters.terms)) {
        return;
    }

    const current = termSelect.value;
    const options = ['<option value="all">All Terms</option>']
        .concat(filters.terms.map((term) => {
            const value = typeof term === 'string' ? term : term.value;
            const label = typeof term === 'string' ? term.replace('-', ' - ') : term.label;
            return `<option value="${value}">${label}</option>`;
        }));
    termSelect.innerHTML = options.join('');
    if ([...termSelect.options].some((option) => option.value === current)) {
        termSelect.value = current;
    }
}

function escapeAroffHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function aroffProfileGroup(iconClass, title, fields) {
    const cells = fields.map(([label, value]) => `
        <div class="account-profile-field">
            <label>${escapeAroffHtml(label)}</label>
            <p>${escapeAroffHtml(value || '-')}</p>
        </div>
    `).join('');

    return `
        <div class="account-profile-group">
            <div class="account-profile-group-label">
                <i class="fa-solid ${iconClass}"></i> ${escapeAroffHtml(title)}
            </div>
            <div class="account-profile-row">${cells}</div>
        </div>
    `;
}

// ── Render Table ──────────────────────────────────────────────────────────────
function renderAroffTable() {
    const tbody = document.getElementById('aroffTableBody');
    const info = document.getElementById('aroffPaginationInfo');
    if (!tbody) return;

    const start = (aroffCurrentPage - 1) * aroffPerPage;
    const end = start + aroffPerPage;
    const page = aroffFiltered.slice(start, end);

    if (aroffFiltered.length === 0) {
        tbody.innerHTML = `<tr class="aroff-empty-row"><td colspan="5">No archived SK Officials records found.</td></tr>`;
        if (info) info.textContent = '0 records';
        renderAroffPagination(0);
        return;
    }

    tbody.innerHTML = page.map(r => {
        const fullName = formatRecordName(r);
        const term = `${r.termStart} – ${r.termEnd}`;
        return `
        <tr>
            <td class="aroff-name-cell">${fullName}</td>
            <td style="text-align:center;">${r.position || '—'}</td>
            <td style="text-align:center;"><span class="aroff-term-badge">${term}</span></td>
            <td style="text-align:center;"><span class="aroff-completed-badge">Completed Term</span></td>
            <td>
                <div class="aroff-action-btns">
                    <button type="button" class="aroff-btn-view" data-id="${r.id}" aria-label="View details for ${fullName}">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `${aroffFiltered.length} record${aroffFiltered.length === 1 ? '' : 's'}`;

    renderAroffPagination(aroffFiltered.length);

    tbody.querySelectorAll('.aroff-btn-view').forEach(btn => {
        btn.addEventListener('click', function () { openAroffViewModal(this.dataset.id); });
    });
}

function renderAroffPagination(total) {
    const pages = Math.max(1, Math.ceil(total / aroffPerPage) || 1);
    const prev = document.getElementById('aroffPrevBtn');
    const next = document.getElementById('aroffNextBtn');
    const pageInput = document.getElementById('aroffPageInput');
    const totalPages = document.getElementById('aroffTotalPages');

    if (totalPages) totalPages.textContent = String(pages);
    if (pageInput) {
        pageInput.value = String(aroffCurrentPage);
        pageInput.max = String(pages);
    }
    if (prev) prev.disabled = aroffCurrentPage <= 1 || total === 0;
    if (next) next.disabled = aroffCurrentPage >= pages || total === 0;
}

function bindAroffPagination() {
    document.getElementById('aroffPrevBtn')?.addEventListener('click', () => {
        if (aroffCurrentPage > 1) {
            aroffCurrentPage -= 1;
            renderAroffTable();
        }
    });
    document.getElementById('aroffNextBtn')?.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(aroffFiltered.length / aroffPerPage) || 1);
        if (aroffCurrentPage < pages) {
            aroffCurrentPage += 1;
            renderAroffTable();
        }
    });
    document.getElementById('aroffPageInput')?.addEventListener('change', function () {
        const pages = Math.max(1, Math.ceil(aroffFiltered.length / aroffPerPage) || 1);
        const nextPage = Math.min(pages, Math.max(1, parseInt(this.value, 10) || 1));
        aroffCurrentPage = nextPage;
        renderAroffTable();
    });
    document.getElementById('aroffRowsPerPageSelect')?.addEventListener('change', function () {
        aroffPerPage = Math.max(1, parseInt(this.value, 10) || 10);
        aroffCurrentPage = 1;
        renderAroffTable();
    });
}

// ── Search ────────────────────────────────────────────────────────────────────
function bindAroffSearch() {
    const input = document.getElementById('aroffSearch');
    const yearSelect = document.getElementById('aroffYearFilter');
    const termSelect = document.getElementById('aroffTermFilter');
    let searchTimer = null;

    if (input) {
        input.addEventListener('input', function () {
            aroffSearchQ = this.value.trim();
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                aroffCurrentPage = 1;
                loadAroffRecords();
            }, 300);
        });
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', function () {
            aroffYearFilter = this.value;
            aroffCurrentPage = 1;
            loadAroffRecords();
        });
    }

    if (termSelect) {
        termSelect.addEventListener('change', function () {
            aroffTermFilter = this.value;
            aroffCurrentPage = 1;
            loadAroffRecords();
        });
    }
}

// ── View Modal ────────────────────────────────────────────────────────────────
function openAroffViewModal(id) {
    const r = aroffRecords.find((x) => String(x.id) === String(id));
    if (!r) return;

    const body = document.getElementById('aroffViewBody');
    if (body) {
        body.innerHTML = `
            <div class="account-modal-card">
                ${aroffProfileGroup('fa-user', 'Personal Information', [
                    ['Full Name', formatRecordName(r)],
                    ['Sex', r.sex],
                    ['Date of Birth', r.dateOfBirth],
                    ['Age', r.age],
                    ['Contact Number', r.contactNumber],
                ])}
                ${aroffProfileGroup('fa-briefcase', 'Position & Account', [
                    ['Position', r.position],
                    ['Email Address', r.email],
                    ['Email Verification', r.emailVerification || r.emailVerifiedAt || 'Not Verified'],
                ])}
                ${aroffProfileGroup('fa-location-dot', 'Address', [
                    ['Region', r.region || 'IV-A CALABARZON'],
                    ['Province', r.province || 'Laguna'],
                    ['Municipality', r.municipality || 'Santa Cruz'],
                    ['Barangay', r.barangay],
                ])}
                ${aroffProfileGroup('fa-calendar-check', 'Term Information', [
                    ['Term Start', r.termStart],
                    ['Term End', r.termEnd],
                ])}
            </div>`;
    }

    const modal = document.getElementById('aroffViewModal');
    if (modal) modal.style.display = 'flex';
}

function bindAroffViewModal() {
    const modal = document.getElementById('aroffViewModal');
    const box = document.getElementById('aroffViewModalBox');
    const closeBtn = document.getElementById('aroffViewClose');
    const closeFooterBtn = document.getElementById('aroffViewCloseFooter');
    const toggleBtn = document.getElementById('aroffViewToggle');

    const close = () => {
        if (modal) { modal.style.display = 'none'; modal.classList.remove('aroff-maximized'); }
        if (box) box.classList.remove('aroff-maximized');
        if (toggleBtn) toggleBtn.textContent = '□';
    };

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (closeFooterBtn) closeFooterBtn.addEventListener('click', close);
    if (modal) modal.addEventListener('click', e => { if (e.target === modal) close(); });

    if (toggleBtn && box) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isMax = !box.classList.contains('aroff-maximized');
            modal.classList.toggle('aroff-maximized', isMax);
            box.classList.toggle('aroff-maximized', isMax);
            toggleBtn.textContent = isMax ? '⧉' : '□';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') close();
    });
}
