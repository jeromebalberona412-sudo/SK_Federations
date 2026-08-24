// Archived SK Federation Records Module

document.addEventListener('DOMContentLoaded', function () {
    initArchivedSkFederation();
});

function formatRecordName(record) {
    return [record.lastName, record.firstName, record.middleName, record.suffix]
        .filter((part) => part && String(part).trim() !== '')
        .join(', ');
}

const ARFED_API = {
    data: '/manage-archive/sk-federation-records/data',
};

let arfedRecords = [];
let arfedFiltered = [];
let arfedIsLoading = false;
let arfedCurrentPage = 1;
let arfedPerPage = 10;
let arfedSearchQ = '';
let arfedYearFilter = 'all';
let arfedTermFilter = 'all';

// ── Init ──────────────────────────────────────────────────────────────────────
const ARFED_POLL_MS = 20000;

function initArchivedSkFederation() {
    bindArfedSearch();
    bindArfedPagination();
    bindArfedViewModal();
    loadArfedRecords();
    startArfedRealtimeRefresh();
}

function startArfedRealtimeRefresh() {
    window.setInterval(() => {
        if (!document.hidden) {
            loadArfedRecords();
        }
    }, ARFED_POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            loadArfedRecords();
        }
    });
}

async function loadArfedRecords() {
    if (arfedIsLoading) return;
    arfedIsLoading = true;

    const params = new URLSearchParams({
        search: arfedSearchQ,
        year: arfedYearFilter,
        term: arfedTermFilter,
    });

    try {
        const response = await fetch(`${ARFED_API.data}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load archived records.');

        arfedRecords = payload.data || [];
        arfedFiltered = [...arfedRecords];
        populateArfedFilters(payload.filters || {});
        const pages = Math.max(1, Math.ceil(arfedFiltered.length / arfedPerPage) || 1);
        if (arfedCurrentPage > pages) {
            arfedCurrentPage = pages;
        }
        renderArfedTable();
    } catch (error) {
        arfedRecords = [];
        arfedFiltered = [];
        renderArfedTable();
    } finally {
        arfedIsLoading = false;
    }
}

function populateArfedFilters(filters) {
    const yearSelect = document.getElementById('arfedYearFilter');
    const termSelect = document.getElementById('arfedTermFilter');

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

function escapeArfedHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function arfedProfileGroup(iconClass, title, fields) {
    const cells = fields.map(([label, value]) => `
        <div class="account-profile-field">
            <label>${escapeArfedHtml(label)}</label>
            <p>${escapeArfedHtml(value || '-')}</p>
        </div>
    `).join('');

    return `
        <div class="account-profile-group">
            <div class="account-profile-group-label">
                <i class="fa-solid ${iconClass}"></i> ${escapeArfedHtml(title)}
            </div>
            <div class="account-profile-row">${cells}</div>
        </div>
    `;
}

// ── Render Table ──────────────────────────────────────────────────────────────
function renderArfedTable() {
    const tbody = document.getElementById('arfedTableBody');
    const info = document.getElementById('arfedPaginationInfo');
    if (!tbody) return;

    const start = (arfedCurrentPage - 1) * arfedPerPage;
    const end = start + arfedPerPage;
    const page = arfedFiltered.slice(start, end);

    if (arfedFiltered.length === 0) {
        tbody.innerHTML = `<tr class="arfed-empty-row"><td colspan="5">No archived SK Federation records found.</td></tr>`;
        if (info) info.textContent = '0 records';
        renderArfedPagination(0);
        return;
    }

    tbody.innerHTML = page.map(r => {
        const fullName = formatRecordName(r);
        const term = `${r.termStart} – ${r.termEnd}`;
        return `
        <tr>
            <td class="arfed-name-cell">${fullName}</td>
            <td style="text-align:center;">${r.position || '—'}</td>
            <td style="text-align:center;"><span class="arfed-term-badge">${term}</span></td>
            <td style="text-align:center;"><span class="arfed-completed-badge">Completed Term</span></td>
            <td>
                <div class="arfed-action-btns">
                    <button type="button" class="arfed-btn-view" data-id="${r.id}" aria-label="View details for ${fullName}">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `${arfedFiltered.length} record${arfedFiltered.length === 1 ? '' : 's'}`;

    renderArfedPagination(arfedFiltered.length);

    tbody.querySelectorAll('.arfed-btn-view').forEach(btn => {
        btn.addEventListener('click', function () { openArfedViewModal(this.dataset.id); });
    });
}

function renderArfedPagination(total) {
    const pages = Math.max(1, Math.ceil(total / arfedPerPage) || 1);
    const prev = document.getElementById('arfedPrevBtn');
    const next = document.getElementById('arfedNextBtn');
    const pageInput = document.getElementById('arfedPageInput');
    const totalPages = document.getElementById('arfedTotalPages');

    if (totalPages) totalPages.textContent = String(pages);
    if (pageInput) {
        pageInput.value = String(arfedCurrentPage);
        pageInput.max = String(pages);
    }
    if (prev) prev.disabled = arfedCurrentPage <= 1 || total === 0;
    if (next) next.disabled = arfedCurrentPage >= pages || total === 0;
}

function bindArfedPagination() {
    document.getElementById('arfedPrevBtn')?.addEventListener('click', () => {
        if (arfedCurrentPage > 1) {
            arfedCurrentPage -= 1;
            renderArfedTable();
        }
    });
    document.getElementById('arfedNextBtn')?.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(arfedFiltered.length / arfedPerPage) || 1);
        if (arfedCurrentPage < pages) {
            arfedCurrentPage += 1;
            renderArfedTable();
        }
    });
    document.getElementById('arfedPageInput')?.addEventListener('change', function () {
        const pages = Math.max(1, Math.ceil(arfedFiltered.length / arfedPerPage) || 1);
        arfedCurrentPage = Math.min(pages, Math.max(1, parseInt(this.value, 10) || 1));
        renderArfedTable();
    });
    document.getElementById('arfedRowsPerPageSelect')?.addEventListener('change', function () {
        arfedPerPage = Math.max(1, parseInt(this.value, 10) || 10);
        arfedCurrentPage = 1;
        renderArfedTable();
    });
}

// ── Search ────────────────────────────────────────────────────────────────────
function bindArfedSearch() {
    const input = document.getElementById('arfedSearch');
    const yearSelect = document.getElementById('arfedYearFilter');
    const termSelect = document.getElementById('arfedTermFilter');
    let searchTimer = null;

    if (input) {
        input.addEventListener('input', function () {
            arfedSearchQ = this.value.trim();
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                arfedCurrentPage = 1;
                loadArfedRecords();
            }, 300);
        });
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', function () {
            arfedYearFilter = this.value;
            arfedCurrentPage = 1;
            loadArfedRecords();
        });
    }

    if (termSelect) {
        termSelect.addEventListener('change', function () {
            arfedTermFilter = this.value;
            arfedCurrentPage = 1;
            loadArfedRecords();
        });
    }
}

// ── View Modal ────────────────────────────────────────────────────────────────
function openArfedViewModal(id) {
    const r = arfedRecords.find((x) => String(x.id) === String(id));
    if (!r) return;

    const body = document.getElementById('arfedViewBody');
    if (body) {
        body.innerHTML = `
            <div class="account-modal-card">
                ${arfedProfileGroup('fa-user', 'Personal Information', [
                    ['Full Name', formatRecordName(r)],
                    ['Sex', r.sex],
                    ['Date of Birth', r.dateOfBirth],
                    ['Age', r.age],
                    ['Contact Number', r.contactNumber],
                ])}
                ${arfedProfileGroup('fa-briefcase', 'Position & Account', [
                    ['Position', r.position],
                    ['Email Address', r.email],
                    ['Email Verification', r.emailVerification || r.emailVerifiedAt || 'Not Verified'],
                ])}
                ${arfedProfileGroup('fa-location-dot', 'Address', [
                    ['Region', r.region || 'IV-A CALABARZON'],
                    ['Province', r.province || 'Laguna'],
                    ['Municipality', r.municipality || 'Santa Cruz'],
                    ['Barangay', r.barangay],
                ])}
                ${arfedProfileGroup('fa-calendar-check', 'Term Information', [
                    ['Term Start', r.termStart],
                    ['Term End', r.termEnd],
                ])}
            </div>`;
    }

    const modal = document.getElementById('arfedViewModal');
    if (modal) modal.style.display = 'flex';
}

function bindArfedViewModal() {
    const modal = document.getElementById('arfedViewModal');
    const box = document.getElementById('arfedViewModalBox');
    const closeBtn = document.getElementById('arfedViewClose');
    const closeFooterBtn = document.getElementById('arfedViewCloseFooter');
    const toggleBtn = document.getElementById('arfedViewToggle');

    const close = () => {
        if (modal) { modal.style.display = 'none'; modal.classList.remove('arfed-maximized'); }
        if (box) box.classList.remove('arfed-maximized');
        if (toggleBtn) toggleBtn.textContent = '□';
    };

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (closeFooterBtn) closeFooterBtn.addEventListener('click', close);
    if (modal) modal.addEventListener('click', e => { if (e.target === modal) close(); });

    if (toggleBtn && box) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isMax = !box.classList.contains('arfed-maximized');
            modal.classList.toggle('arfed-maximized', isMax);
            box.classList.toggle('arfed-maximized', isMax);
            toggleBtn.textContent = isMax ? '⧉' : '□';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') close();
    });
}
