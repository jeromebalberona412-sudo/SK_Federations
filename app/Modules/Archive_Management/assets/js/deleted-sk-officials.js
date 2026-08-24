// Deleted SK Officials — live data from users archive

document.addEventListener('DOMContentLoaded', function () {
    initDeletedSkOfficials();
});

function formatRecordName(record) {
    return [record.lastName, record.firstName, record.middleName, record.suffix]
        .filter((part) => part && String(part).trim() !== '')
        .join(', ');
}

function escapeDsoHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function dsoProfileGroup(iconClass, title, fields) {
    const cells = fields.map(([label, value]) => `
        <div class="account-profile-field">
            <label>${escapeDsoHtml(label)}</label>
            <p>${escapeDsoHtml(value || '-')}</p>
        </div>
    `).join('');

    return `
        <div class="account-profile-group">
            <div class="account-profile-group-label">
                <i class="fa-solid ${iconClass}"></i> ${escapeDsoHtml(title)}
            </div>
            <div class="account-profile-row">${cells}</div>
        </div>
    `;
}

const DSO_API = {
    data: '/archived/deleted-sk-officials/data',
    restore: (id) => `/archived/deleted-sk-officials/${id}/restore`,
};

let dsoRecords = [];
let dsoFiltered = [];
let dsoCurrentPage = 1;
let dsoPerPage = 10;
let dsoPendingId = null;
let dsoActiveFilter = 'all';
let dsoActiveBarangay = '';
let dsoActivePosition = '';
let dsoActiveTerm = '';
let dsoYearFilter = 'all';
let dsoIsLoading = false;

const DSO_POLL_MS = 20000;

function initDeletedSkOfficials() {
    bindDsoSearch();
    bindDsoFilterTabs();
    bindDsoDropdowns();
    bindDsoPagination();
    bindDsoRestoreModal();
    bindDsoViewModal();
    loadDsoRecords();
    startDsoRealtimeRefresh();
}

function startDsoRealtimeRefresh() {
    window.setInterval(() => {
        if (!document.hidden) {
            loadDsoRecords();
        }
    }, DSO_POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            loadDsoRecords();
        }
    });
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function loadDsoRecords() {
    if (dsoIsLoading) return;
    dsoIsLoading = true;
    renderDsoLoadingState();

    const params = new URLSearchParams({
        filter: dsoActiveFilter,
        year: dsoYearFilter,
        search: document.getElementById('dsoSearch')?.value?.trim() || '',
        barangay: dsoActiveBarangay,
        position: dsoActivePosition,
        term: dsoActiveTerm,
    });

    try {
        const response = await fetch(`${DSO_API.data}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load deleted records.');

        dsoRecords = (payload.data || []).map(normalizeDsoRecord);
        dsoFiltered = [...dsoRecords];
        populateDsoDropdowns(payload.filters || {});
        const pages = Math.max(1, Math.ceil(dsoFiltered.length / dsoPerPage) || 1);
        if (dsoCurrentPage > pages) {
            dsoCurrentPage = pages;
        }
        renderDsoTable();
    } catch (error) {
        dsoRecords = [];
        dsoFiltered = [];
        renderDsoErrorState(error.message || 'Unable to load deleted SK Officials records.');
    } finally {
        dsoIsLoading = false;
    }
}

function normalizeDsoRecord(record) {
    return {
        ...record,
        _deletedTs: record.deleted_at ? new Date(record.deleted_at) : null,
    };
}

function renderDsoLoadingState() {
    const tbody = document.getElementById('dsoTableBody');
    if (tbody) {
        tbody.innerHTML = '<tr class="dso-empty-row"><td colspan="8">Loading deleted records...</td></tr>';
    }
}

function renderDsoErrorState(message) {
    const tbody = document.getElementById('dsoTableBody');
    const info = document.getElementById('dsoPaginationInfo');
    if (tbody) tbody.innerHTML = `<tr class="dso-empty-row"><td colspan="8">${message}</td></tr>`;
    if (info) info.textContent = '0 records';
    renderDsoPagination(0);
}

function populateDsoDropdowns(filters) {
    fillSelectOptions('dsoFilterBarangay', 'All Barangays', filters.barangays || [], dsoActiveBarangay);
    fillSelectOptions('dsoFilterPosition', 'All Positions', filters.positions || [], dsoActivePosition);
    fillSelectOptions('dsoFilterTerm', 'All Terms', filters.terms || [], dsoActiveTerm);
    fillSelectOptions('dsoYearFilter', 'All Years', (filters.years || []).map(String), dsoYearFilter === 'all' ? '' : dsoYearFilter, true);
}

function fillSelectOptions(selectId, defaultLabel, values, selectedValue, isYear = false) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const current = selectedValue || (isYear ? dsoYearFilter : '') || '';
    select.innerHTML = '';

    const defaultOpt = document.createElement('option');
    defaultOpt.value = isYear ? 'all' : '';
    defaultOpt.textContent = defaultLabel;
    if ((isYear && dsoYearFilter === 'all') || (!isYear && !current)) {
        defaultOpt.selected = true;
    }
    select.appendChild(defaultOpt);

    values.forEach((value) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = value;
        if (String(current) === String(value)) opt.selected = true;
        select.appendChild(opt);
    });
}

function bindDsoDropdowns() {
    document.getElementById('dsoFilterBarangay')?.addEventListener('change', function () {
        dsoActiveBarangay = this.value;
        dsoCurrentPage = 1;
        loadDsoRecords();
    });
    document.getElementById('dsoFilterPosition')?.addEventListener('change', function () {
        dsoActivePosition = this.value;
        dsoCurrentPage = 1;
        loadDsoRecords();
    });
    document.getElementById('dsoFilterTerm')?.addEventListener('change', function () {
        dsoActiveTerm = this.value;
        dsoCurrentPage = 1;
        loadDsoRecords();
    });
}

function bindDsoFilterTabs() {
    document.querySelectorAll('.dso-tab').forEach((btn) => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.dso-tab').forEach((b) => b.classList.remove('active'));
            this.classList.add('active');
            dsoActiveFilter = this.dataset.filter || 'all';
            dsoCurrentPage = 1;
            loadDsoRecords();
        });
    });
}

function renderDsoTable() {
    const tbody = document.getElementById('dsoTableBody');
    const info = document.getElementById('dsoPaginationInfo');
    if (!tbody) return;

    dsoFiltered = [...dsoRecords];
    const start = (dsoCurrentPage - 1) * dsoPerPage;
    const end = start + dsoPerPage;
    const page = dsoFiltered.slice(start, end);

    if (dsoFiltered.length === 0) {
        tbody.innerHTML = '<tr class="dso-empty-row"><td colspan="8">No deleted SK Officials records found.</td></tr>';
        if (info) info.textContent = '0 records';
        renderDsoPagination(0);
        return;
    }

    tbody.innerHTML = page.map((r) => {
        const fullName = formatRecordName(r);
        return `
        <tr>
            <td class="dso-name-cell">${fullName}</td>
            <td>${r.position || '—'}</td>
            <td>${r.barangay || '—'}</td>
            <td>${r.municipality || '—'}</td>
            <td>${r.term || '—'}</td>
            <td><span class="dso-deleted-badge">${r.deletedDate}</span></td>
            <td><span class="dso-time-badge">${r.deletedTime}</span></td>
            <td>
                <div class="dso-action-btns">
                    <button type="button" class="dso-btn-view" data-id="${r.id}">View</button>
                    <button type="button" class="dso-btn-restore" data-id="${r.id}">Restore</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `${dsoFiltered.length} record${dsoFiltered.length === 1 ? '' : 's'}`;
    renderDsoPagination(dsoFiltered.length);

    tbody.querySelectorAll('.dso-btn-restore').forEach((btn) => {
        btn.addEventListener('click', () => openDsoRestoreModal(btn.dataset.id));
    });
    tbody.querySelectorAll('.dso-btn-view').forEach((btn) => {
        btn.addEventListener('click', () => openDsoViewModal(btn.dataset.id));
    });
}

function renderDsoPagination(total) {
    const pages = Math.max(1, Math.ceil(total / dsoPerPage) || 1);
    const prev = document.getElementById('dsoPrevBtn');
    const next = document.getElementById('dsoNextBtn');
    const pageInput = document.getElementById('dsoPageInput');
    const totalPages = document.getElementById('dsoTotalPages');

    if (totalPages) totalPages.textContent = String(pages);
    if (pageInput) {
        pageInput.value = String(dsoCurrentPage);
        pageInput.max = String(pages);
    }
    if (prev) prev.disabled = dsoCurrentPage <= 1 || total === 0;
    if (next) next.disabled = dsoCurrentPage >= pages || total === 0;
}

function bindDsoPagination() {
    document.getElementById('dsoPrevBtn')?.addEventListener('click', () => {
        if (dsoCurrentPage > 1) {
            dsoCurrentPage -= 1;
            renderDsoTable();
        }
    });
    document.getElementById('dsoNextBtn')?.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(dsoFiltered.length / dsoPerPage) || 1);
        if (dsoCurrentPage < pages) {
            dsoCurrentPage += 1;
            renderDsoTable();
        }
    });
    document.getElementById('dsoPageInput')?.addEventListener('change', function () {
        const pages = Math.max(1, Math.ceil(dsoFiltered.length / dsoPerPage) || 1);
        dsoCurrentPage = Math.min(pages, Math.max(1, parseInt(this.value, 10) || 1));
        renderDsoTable();
    });
    document.getElementById('dsoRowsPerPageSelect')?.addEventListener('change', function () {
        dsoPerPage = Math.max(1, parseInt(this.value, 10) || 10);
        dsoCurrentPage = 1;
        renderDsoTable();
    });
}

function bindDsoSearch() {
    let timer = null;
    document.getElementById('dsoSearch')?.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => {
            dsoCurrentPage = 1;
            loadDsoRecords();
        }, 300);
    });
    document.getElementById('dsoYearFilter')?.addEventListener('change', function () {
        dsoYearFilter = this.value || 'all';
        dsoCurrentPage = 1;
        loadDsoRecords();
    });
}

function openDsoViewModal(id) {
    const r = dsoRecords.find((x) => String(x.id) === String(id));
    if (!r) return;

    const body = document.getElementById('dsoViewBody');
    if (!body) return;

    body.innerHTML = `
        <div class="account-modal-card">
            ${dsoProfileGroup('fa-user', 'Personal Information', [
                ['Full Name', formatRecordName(r)],
                ['Sex', r.sex],
                ['Date of Birth', r.dateOfBirth],
                ['Age', r.age],
                ['Contact Number', r.contactNumber],
            ])}
            ${dsoProfileGroup('fa-briefcase', 'Position & Account', [
                ['Position', r.position],
                ['Email Address', r.email],
                ['Email Verification', r.emailVerification || 'Not Verified'],
            ])}
            ${dsoProfileGroup('fa-location-dot', 'Address', [
                ['Region', r.region || 'IV-A CALABARZON'],
                ['Province', r.province || 'Laguna'],
                ['Municipality', r.municipality || 'Santa Cruz'],
                ['Barangay', r.barangay],
            ])}
            ${dsoProfileGroup('fa-calendar-check', 'Term Information', [
                ['Term Start', r.termStart],
                ['Term End', r.termEnd],
            ])}
        </div>`;

    const modal = document.getElementById('dsoViewModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.classList.add('dso-modal-open');
    }
}

function bindDsoViewModal() {
    const modal = document.getElementById('dsoViewModal');
    const box = document.getElementById('dsoViewModalBox');
    const closeBtn = document.getElementById('dsoViewClose');
    const closeFooterBtn = document.getElementById('dsoViewCloseFooter');
    const toggleBtn = document.getElementById('dsoViewToggle');
    const toggleIcon = document.getElementById('dsoViewToggleIcon');

    const close = () => {
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('dso-maximized');
        }
        if (box) box.classList.remove('dso-maximized');
        if (toggleBtn) toggleBtn.title = 'Maximize';
        if (toggleIcon) toggleIcon.innerHTML = '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>';
        document.body.classList.remove('dso-modal-open');
    };

    closeBtn?.addEventListener('click', close);
    closeFooterBtn?.addEventListener('click', close);
    modal?.addEventListener('click', (e) => { if (e.target === modal) close(); });

    toggleBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isMax = !box?.classList.contains('dso-maximized');
        modal?.classList.toggle('dso-maximized', isMax);
        box?.classList.toggle('dso-maximized', isMax);
        toggleBtn.title = isMax ? 'Restore Down' : 'Maximize';
        if (toggleIcon) {
            toggleIcon.innerHTML = isMax
                ? '<path d="M4 14h6v6"></path><path d="M20 10h-6V4"></path><path d="M14 10l7-7"></path><path d="M3 21l7-7"></path>'
                : '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>';
        }
    });
}

function openDsoRestoreModal(id) {
    const record = dsoRecords.find((r) => String(r.id) === String(id));
    if (!record) return;
    dsoPendingId = id;
    const nameEl = document.getElementById('dsoRestoreName');
    if (nameEl) nameEl.textContent = formatRecordName(record);
    const modal = document.getElementById('dsoRestoreModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.classList.add('dso-modal-open');
    }
}

function closeDsoRestoreModal() {
    dsoPendingId = null;
    const modal = document.getElementById('dsoRestoreModal');
    if (modal) modal.style.display = 'none';
    document.body.classList.remove('dso-modal-open');
}

function bindDsoRestoreModal() {
    const cancelBtn = document.getElementById('dsoRestoreCancelBtn');
    const confirmBtn = document.getElementById('dsoRestoreConfirmBtn');
    const modal = document.getElementById('dsoRestoreModal');

    cancelBtn?.addEventListener('click', closeDsoRestoreModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeDsoRestoreModal(); });

    confirmBtn?.addEventListener('click', async function () {
        if (!dsoPendingId) return;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Restoring...';

        try {
            const response = await fetch(DSO_API.restore(dsoPendingId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to restore account.');
            }
            closeDsoRestoreModal();
            showDsoToast(data.message || 'Account restored successfully.');
            await loadDsoRecords();
        } catch (error) {
            alert(error.message || 'Failed to restore account. Please try again.');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Restore';
        }
    });
}

function showDsoToast(message) {
    const existing = document.getElementById('dso-toast');
    if (existing) {
        clearTimeout(existing._timer);
        existing.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'dso-toast';
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    Object.assign(toast.style, {
        position: 'fixed',
        top: '72px',
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: '100000',
        padding: '11px 28px',
        borderRadius: '8px',
        fontSize: '0.875rem',
        fontWeight: '600',
        color: '#fff',
        background: '#22c55e',
        boxShadow: '0 4px 18px rgba(0,0,0,.18)',
    });
    document.body.appendChild(toast);
    toast._timer = setTimeout(() => toast.remove(), 3000);
}
