// Deleted SK Officials — live data from users archive

function onArchiveReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

onArchiveReady(initDeletedSkOfficials);

function formatRecordName(record) {
    const composed = [record.lastName, record.firstName, record.middleName, record.suffix]
        .filter((part) => part && String(part).trim() !== '')
        .join(', ');
    if (composed) {
        return composed;
    }
    return String(record.fullName || record.full_name || record.email || '—').trim() || '—';
}

function escapeDsoHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function dsoProfileGroup(iconClass, title, fields) {
    const cells = fields.map(([label, value, fieldType]) => {
        if (fieldType === 'fullname') {
            return `
        <div class="account-profile-field account-profile-field--fullname">
            <label>${escapeDsoHtml(label)} <span class="table-col-hint">LN, FN, MN, Suffix</span></label>
            <p>${escapeDsoHtml(value || '-')}</p>
        </div>`;
        }
        return `
        <div class="account-profile-field">
            <label>${escapeDsoHtml(label)}</label>
            <p>${escapeDsoHtml(value ?? '') || '-'}</p>
        </div>`;
    }).join('');

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
    show: (id) => `/archived/deleted-sk-officials/${encodeURIComponent(id)}`,
    restore: (id) => `/archived/deleted-sk-officials/${encodeURIComponent(id)}/restore`,
};

let dsoRecords = [];
let dsoFiltered = [];
let dsoCurrentPage = 1;
let dsoPerPage = 10;
let dsoPendingId = null;
let dsoActiveFilter = 'all';
let dsoActiveBarangay = '';
let dsoActivePosition = '';
let dsoYearFilter = 'all';
let dsoIsLoading = false;
let dsoIsRestoring = false;
let dsoNameSortDir = null;

const DSO_POLL_MS = 20000;

function initDeletedSkOfficials() {
    if (window.__DSO_CURRENT_FILTERS__) {
        dsoActiveFilter = window.__DSO_CURRENT_FILTERS__.filter || 'all';
        dsoYearFilter = window.__DSO_CURRENT_FILTERS__.year || 'all';
        dsoActiveBarangay = window.__DSO_CURRENT_FILTERS__.barangay || '';
        dsoActivePosition = window.__DSO_CURRENT_FILTERS__.position || '';
    }

    if (window.ArchiveRowActions) {
        window.ArchiveRowActions.initGlobalClose();
        window.ArchiveRowActions.bindViewModalControls('dso', { bodyClass: 'dso-modal-open' });
    }

    bindDsoFullnameSort();
    bindDsoGlobalMenuClose();
    bindDsoSearch();
    bindDsoFilterTabs();
    bindDsoDropdowns();
    bindDsoPagination();
    bindDsoRestoreModal();
    bindDsoViewModal();

    const tbody = document.getElementById('dsoTableBody');
    if (tbody) {
        bindDsoTableActions(tbody);
    }

    if (Array.isArray(window.__INITIAL_DELETED_SK_OFFICIALS__) && window.__INITIAL_DELETED_SK_OFFICIALS__.length > 0) {
        dsoRecords = window.__INITIAL_DELETED_SK_OFFICIALS__.map(normalizeDsoRecord);
        dsoFiltered = [...dsoRecords];
        if (window.__INITIAL_DSO_FILTERS__) {
            populateDsoDropdowns(window.__INITIAL_DSO_FILTERS__);
        }
        renderDsoTable();
    } else {
        loadDsoRecords();
    }

    startDsoRealtimeRefresh();
}

function startDsoRealtimeRefresh() {
    // Skip background refreshes while a modal or row menu is open so the user's place is preserved.
    const canRefresh = () => !document.hidden
        && !anyDsoModalOpen()
        && !dsoIsRestoring
        && !isDsoActionsMenuOpen();

    window.setInterval(() => {
        if (canRefresh()) loadDsoRecords();
    }, DSO_POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (canRefresh()) loadDsoRecords();
    });
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function loadDsoRecords() {
    if (dsoIsLoading) return;
    dsoIsLoading = true;

    const params = new URLSearchParams({
        filter: dsoActiveFilter,
        year: dsoYearFilter,
        search: document.getElementById('dsoSearch')?.value?.trim() || '',
        barangay: dsoActiveBarangay,
        position: dsoActivePosition,
    });
    const cacheKey = 'sk_fed_deleted_sk_officials_v1_' + params.toString();
    const cached = window.SkLocalCache?.get(cacheKey);
    const paint = (payload) => {
        dsoRecords = (payload.data || []).map(normalizeDsoRecord);
        dsoFiltered = [...dsoRecords];
        populateDsoDropdowns(payload.filters || {});
        const pages = Math.max(1, Math.ceil(dsoFiltered.length / dsoPerPage) || 1);
        if (dsoCurrentPage > pages) dsoCurrentPage = pages;
        renderDsoTable();
    };

    if (cached?.payload) {
        paint(cached.payload);
        if (window.SkLocalCache.isFresh(cached, 5 * 60 * 1000)) {
            dsoIsLoading = false;
            fetch(`${DSO_API.data}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json().then((payload) => ({ ok: response.ok, payload })))
                .then(({ ok, payload }) => {
                    if (!ok) return;
                    window.SkLocalCache?.set(cacheKey, payload);
                    paint(payload);
                })
                .catch(() => {});
            return;
        }
    } else {
        renderDsoLoadingState();
    }

    try {
        const response = await fetch(`${DSO_API.data}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load deleted records.');

        window.SkLocalCache?.set(cacheKey, payload);
        paint(payload);
    } catch (error) {
        if (!cached?.payload) {
            dsoRecords = [];
            dsoFiltered = [];
            renderDsoErrorState(error.message || 'Unable to load deleted SK Officials records.');
        }
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

function dsoRetentionDays() {
    const configured = Number(window.__DSO_RETENTION_DAYS__);
    return Number.isFinite(configured) && configured > 0 ? configured : 30;
}

function dsoRetentionTier(daysLeft) {
    if (daysLeft <= 3) return 'red';
    if (daysLeft <= 7) return 'orange';
    return 'green';
}

/**
 * Recomputed from deleted_at on every render so a cached payload never shows a
 * stale countdown. Falls back to the server-supplied values when the timestamp
 * is missing.
 */
function dsoRetentionInfo(record) {
    const deletedAt = record._deletedTs;
    const hasTimestamp = deletedAt instanceof Date && !Number.isNaN(deletedAt.getTime());

    let daysLeft;
    let expiresLabel;

    if (hasTimestamp) {
        const expiresAt = new Date(deletedAt.getTime());
        expiresAt.setDate(expiresAt.getDate() + dsoRetentionDays());
        const msLeft = expiresAt.getTime() - Date.now();
        daysLeft = msLeft <= 0 ? 0 : Math.max(1, Math.ceil(msLeft / 86400000));
        expiresLabel = expiresAt.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    } else {
        const reported = Number(record.daysRemaining);
        daysLeft = Number.isFinite(reported) ? reported : dsoRetentionDays();
        expiresLabel = record.autoDeleteDate || '—';
    }

    return {
        days: daysLeft,
        tier: dsoRetentionTier(daysLeft),
        label: daysLeft <= 0 ? 'Deleting now' : `${daysLeft} ${daysLeft === 1 ? 'day' : 'days'} left`,
        expiresLabel,
    };
}

function renderDsoLoadingState() {
    const tbody = document.getElementById('dsoTableBody');
    if (tbody) {
        tbody.innerHTML = '<tr class="dso-empty-row"><td colspan="9">Loading deleted records...</td></tr>';
    }
}

function renderDsoErrorState(message) {
    const tbody = document.getElementById('dsoTableBody');
    const info = document.getElementById('dsoPaginationInfo');
    if (tbody) tbody.innerHTML = `<tr class="dso-empty-row"><td colspan="9">${escapeDsoHtml(message)}</td></tr>`;
    if (info) info.textContent = '0 records';
    renderDsoPagination(0);
}

function populateDsoDropdowns(filters) {
    fillSelectOptions('dsoFilterBarangay', 'All Barangays', filters.barangays || [], dsoActiveBarangay);
    fillSelectOptions('dsoFilterPosition', 'All Positions', filters.positions || [], dsoActivePosition);
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

    values.forEach((item) => {
        const opt = document.createElement('option');
        if (typeof item === 'object' && item !== null) {
            opt.value = item.value;
            opt.textContent = item.label;
            if (String(current) === String(item.value)) opt.selected = true;
        } else {
            opt.value = item;
            opt.textContent = item;
            if (String(current) === String(item)) opt.selected = true;
        }
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

function getDsoSortedRecords() {
    const list = [...dsoFiltered];
    if (!dsoNameSortDir) return list;
    list.sort((a, b) => {
        const cmp = formatRecordName(a).localeCompare(formatRecordName(b), undefined, { sensitivity: 'base' });
        return dsoNameSortDir === 'asc' ? cmp : -cmp;
    });
    return list;
}

/* ── Full Name A→Z / Z→A sort menu (mirrors the Accounts table behavior) ── */
function updateDsoSortHeaderState() {
    const th = document.querySelector('.dso-table .ar-th-sortable[data-sort-key="fullname"]');
    if (!th) return;
    th.classList.remove('is-sorted-asc', 'is-sorted-desc');
    th.setAttribute('aria-sort', 'none');
    if (dsoNameSortDir === 'asc') {
        th.classList.add('is-sorted-asc');
        th.setAttribute('aria-sort', 'ascending');
    } else if (dsoNameSortDir === 'desc') {
        th.classList.add('is-sorted-desc');
        th.setAttribute('aria-sort', 'descending');
    }
}

function closeDsoSortMenu() {
    const menu = document.getElementById('dsoFullnameSortMenu');
    if (menu) menu.hidden = true;
    document.getElementById('dsoFullnameSortBtn')?.setAttribute('aria-expanded', 'false');
}

function openDsoSortMenu(anchor) {
    const menu = document.getElementById('dsoFullnameSortMenu');
    if (!menu || !anchor) return;

    menu.innerHTML = '';
    [
        { dir: 'asc', label: 'Sort ascending', hint: 'A → Z', icon: '↑' },
        { dir: 'desc', label: 'Sort descending', hint: 'Z → A', icon: '↓' },
    ].forEach((opt) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dso-sort-option' + (dsoNameSortDir === opt.dir ? ' is-active' : '');
        btn.setAttribute('role', 'menuitem');
        btn.innerHTML = `<span class="dso-sort-option-icon">${opt.icon}</span><span class="dso-sort-option-text"><span class="dso-sort-option-label">${opt.label}</span><span class="dso-sort-option-hint">${opt.hint}</span></span>`;
        btn.addEventListener('click', () => {
            dsoNameSortDir = opt.dir;
            dsoCurrentPage = 1;
            renderDsoTable();
            closeDsoSortMenu();
        });
        menu.appendChild(btn);
    });

    const rect = anchor.getBoundingClientRect();
    menu.hidden = false;
    const width = menu.offsetWidth || 220;
    menu.style.top = `${rect.bottom + 4}px`;
    menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`;
    anchor.setAttribute('aria-expanded', 'true');
}

function bindDsoFullnameSort() {
    const btn = document.getElementById('dsoFullnameSortBtn');
    if (!btn) return;

    btn.addEventListener('click', (event) => {
        event.stopPropagation();
        const menu = document.getElementById('dsoFullnameSortMenu');
        if (menu && !menu.hidden) {
            closeDsoSortMenu();
            return;
        }
        openDsoSortMenu(btn);
    });
}

/* ── Row actions menu ── */
function isDsoActionsMenuOpen() {
    return Boolean(document.querySelector('.ar-row-actions-menu.is-open'));
}

function closeAllDsoActionMenus() {
    if (window.ArchiveRowActions) {
        window.ArchiveRowActions.closeAll();
    }
}

function bindDsoGlobalMenuClose() {
    if (document.body.dataset.dsoMenusBound === '1') return;
    document.body.dataset.dsoMenusBound = '1';

    document.addEventListener('click', (event) => {
        const sortMenu = document.getElementById('dsoFullnameSortMenu');
        if (sortMenu && !sortMenu.hidden
            && !sortMenu.contains(event.target)
            && !event.target.closest('#dsoFullnameSortBtn')) {
            closeDsoSortMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeAllDsoActionMenus();
        closeDsoSortMenu();
        if (document.getElementById('dsoViewModal')?.style.display === 'flex') closeDsoViewModal();
        if (document.getElementById('dsoRestoreModal')?.style.display === 'flex' && !dsoIsRestoring) closeDsoRestoreModal();
    });

    window.addEventListener('scroll', () => {
        closeDsoSortMenu();
    }, true);

    window.addEventListener('resize', () => {
        closeDsoSortMenu();
    });
}

function renderDsoTable() {
    const tbody = document.getElementById('dsoTableBody');
    const info = document.getElementById('dsoPaginationInfo');
    if (!tbody) return;

    const sorted = getDsoSortedRecords();
    const start = (dsoCurrentPage - 1) * dsoPerPage;
    const end = start + dsoPerPage;
    const page = sorted.slice(start, end);

    if (dsoFiltered.length === 0) {
        tbody.innerHTML = '<tr class="dso-empty-row"><td colspan="9">No deleted SK Officials found.</td></tr>';
        if (info) info.textContent = '0 records';
        renderDsoPagination(0);
        return;
    }

    closeAllDsoActionMenus();

    tbody.innerHTML = page.map((r) => {
        const fullName = formatRecordName(r);
        const position = r.position || '—';
        const barangay = r.barangay || '—';
        const municipality = r.municipality || 'Santa Cruz';
        const term = (r.term && r.term !== '—')
            ? r.term
            : (r.termStart && r.termEnd && r.termStart !== '—' && r.termEnd !== '—'
                ? `${r.termStart} — ${r.termEnd}`
                : (r.termLabel || '—'));
        const deletedDate = r.deletedDate || '—';
        const deletedTime = r.deletedTime || '—';
        const deletedBy = r.deleted_by || '—';

        const actionsHtml = window.ArchiveRowActions
            ? window.ArchiveRowActions.renderMenu(r.id, { view: true, restore: true })
            : `
                <div class="ar-row-actions-menu">
                    <button type="button" class="ar-row-actions-trigger" aria-label="More actions" aria-haspopup="menu" aria-expanded="false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle></svg>
                    </button>
                    <div class="ar-row-actions-dropdown" role="menu" hidden>
                        <button type="button" class="ar-row-actions-item" data-ar-action="view" data-id="${escapeDsoHtml(r.id)}" role="menuitem"><i class="fas fa-eye" aria-hidden="true"></i><span>View</span></button>
                        <button type="button" class="ar-row-actions-item" data-ar-action="restore" data-id="${escapeDsoHtml(r.id)}" role="menuitem"><i class="fas fa-rotate-left" aria-hidden="true"></i><span>Restore</span></button>
                    </div>
                </div>
            `;

        return `
        <tr data-record-id="${r.id}">
            <td class="dso-name-cell" title="${escapeDsoHtml(fullName)}">${escapeDsoHtml(fullName)}</td>
            <td title="${escapeDsoHtml(position)}">${escapeDsoHtml(position)}</td>
            <td title="${escapeDsoHtml(barangay)}">${escapeDsoHtml(barangay)}</td>
            <td title="${escapeDsoHtml(municipality)}">${escapeDsoHtml(municipality)}</td>
            <td title="${escapeDsoHtml(term)}">${escapeDsoHtml(term)}</td>
            <td title="${escapeDsoHtml(deletedDate)}"><span class="dso-deleted-badge">${escapeDsoHtml(deletedDate)}</span></td>
            <td title="${escapeDsoHtml(deletedTime)}"><span class="dso-time-badge">${escapeDsoHtml(deletedTime)}</span></td>
            <td title="${escapeDsoHtml(deletedBy)}">${escapeDsoHtml(deletedBy)}</td>
            <td class="dso-col-actions col-actions">${actionsHtml}</td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `${dsoFiltered.length} record${dsoFiltered.length === 1 ? '' : 's'}`;
    renderDsoPagination(dsoFiltered.length);
    updateDsoSortHeaderState();

    bindDsoTableActions(tbody);
}

function bindDsoTableActions(tbody) {
    if (!tbody) return;

    if (window.ArchiveRowActions) {
        window.ArchiveRowActions.bind(tbody, (action, id) => {
            if (action === 'view') {
                openDsoViewModal(id);
            } else if (action === 'restore') {
                openDsoRestoreModal(id);
            }
        });
    } else {
        if (tbody.dataset.dsoActionsFallbackBound === '1') return;
        tbody.dataset.dsoActionsFallbackBound = '1';
        tbody.addEventListener('click', (e) => {
            const actionBtn = e.target.closest('[data-ar-action]');
            if (!actionBtn) return;
            e.preventDefault();
            const action = actionBtn.getAttribute('data-ar-action');
            const id = actionBtn.getAttribute('data-id');
            if (action === 'view') openDsoViewModal(id);
            if (action === 'restore') openDsoRestoreModal(id);
        });
    }
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

function findDsoRecord(id) {
    return dsoRecords.find((record) => String(record.id) === String(id))
        || dsoFiltered.find((record) => String(record.id) === String(id))
        || null;
}

function anyDsoModalOpen() {
    return ['dsoViewModal', 'dsoRestoreModal']
        .some((modalId) => document.getElementById(modalId)?.style.display === 'flex');
}

function syncDsoModalBodyLock() {
    document.body.classList.toggle('dso-modal-open', anyDsoModalOpen());
}

async function openDsoViewModal(id) {
    let r = findDsoRecord(id);
    if (!r) {
        try {
            const res = await fetch(DSO_API.show(id), {
                headers: { Accept: 'application/json' },
            });
            const payload = await res.json().catch(() => ({}));
            if (payload && payload.success && payload.data) {
                r = normalizeDsoRecord(payload.data);
            }
        } catch (_) {}
    }

    if (!r) {
        showDsoToast('This record is no longer available. Refreshing the list...', 'error');
        loadDsoRecords();
        return;
    }

    const body = document.getElementById('dsoViewBody');
    const modal = document.getElementById('dsoViewModal');
    if (!body || !modal) return;

    const retention = dsoRetentionInfo(r);
    const term = (r.term && r.term !== '—')
        ? r.term
        : (r.termStart && r.termEnd && r.termStart !== '—' && r.termEnd !== '—'
            ? `${r.termStart} — ${r.termEnd}`
            : (r.termLabel || '—'));

    body.innerHTML = `
        <div class="account-modal-card">
            ${dsoProfileGroup('fa-user', 'Personal Information', [
                ['Full Name', formatRecordName(r), 'fullname'],
                ['First Name', r.firstName || '—'],
                ['Middle Name', r.middleName || '—'],
                ['Last Name', r.lastName || '—'],
                ['Suffix', r.suffix || '—'],
                ['Sex', r.sex || '—'],
                ['Date of Birth', r.dateOfBirth || '—'],
                ['Age', r.age ?? '—'],
                ['Contact Number', r.contactNumber || '—'],
            ])}
            ${dsoProfileGroup('fa-briefcase', 'Position & Account', [
                ['Position', r.position || '—'],
                ['Username', r.username || (r.email ? r.email.split('@')[0] : '—')],
                ['Email Address', r.email || '—'],
                ['Email Verification', r.emailVerification || 'Not Verified'],
                ['Account Status', r.accountStatus || '—'],
            ])}
            ${dsoProfileGroup('fa-location-dot', 'Address', [
                ['Region', r.region || 'IV-A CALABARZON'],
                ['Province', r.province || 'Laguna'],
                ['Municipality', r.municipality || 'Santa Cruz'],
                ['Barangay', r.barangay || '—'],
            ])}
            ${dsoProfileGroup('fa-calendar-check', 'Term Information', [
                ['Term', term],
                ['Term Start', r.termStart || '—'],
                ['Term End', r.termEnd || '—'],
                ['Term Status', r.termStatus || '—'],
            ])}
            ${dsoProfileGroup('fa-trash-can', 'Deletion & Retention Details', [
                ['Date Deleted', r.deletedDate || '—'],
                ['Time Deleted', r.deletedTime || '—'],
                ['Deleted By', r.deleted_by || '—'],
                ['Auto-Delete In', retention.label],
                ['Scheduled Permanent Deletion', retention.expiresLabel],
                ['Original Account ID', r.original_user_id || '—'],
                ['Archive ID', r.archive_id || r.id],
            ])}
        </div>`;

    modal.style.display = 'flex';
    syncDsoModalBodyLock();
    document.getElementById('dsoViewClose')?.focus();
}

function closeDsoViewModal() {
    const modal = document.getElementById('dsoViewModal');
    const box = document.getElementById('dsoViewModalBox');
    const maximizeBtn = document.getElementById('dsoViewMaximizeBtn');
    const restoreBtn = document.getElementById('dsoViewRestoreBtn');

    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('dso-maximized');
    }
    box?.classList.remove('dso-maximized');
    if (maximizeBtn) maximizeBtn.hidden = false;
    if (restoreBtn) restoreBtn.hidden = true;
    syncDsoModalBodyLock();
}

function bindDsoViewModal() {
    const modal = document.getElementById('dsoViewModal');
    const box = document.getElementById('dsoViewModalBox');
    const maximizeBtn = document.getElementById('dsoViewMaximizeBtn');
    const restoreBtn = document.getElementById('dsoViewRestoreBtn');

    document.getElementById('dsoViewClose')?.addEventListener('click', closeDsoViewModal);
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeDsoViewModal();
    });

    maximizeBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        modal?.classList.add('dso-maximized');
        box?.classList.add('dso-maximized');
        maximizeBtn.hidden = true;
        if (restoreBtn) restoreBtn.hidden = false;
    });

    restoreBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        modal?.classList.remove('dso-maximized');
        box?.classList.remove('dso-maximized');
        if (maximizeBtn) maximizeBtn.hidden = false;
        restoreBtn.hidden = true;
    });
}

function openDsoRestoreModal(id) {
    const record = findDsoRecord(id);
    if (!record) {
        showDsoToast('This record is no longer available. Refreshing the list...', 'error');
        loadDsoRecords();
        return;
    }

    dsoPendingId = id;
    const nameEl = document.getElementById('dsoRestoreName');
    if (nameEl) nameEl.textContent = formatRecordName(record);

    const modal = document.getElementById('dsoRestoreModal');
    if (modal) {
        modal.style.display = 'flex';
        syncDsoModalBodyLock();
        document.getElementById('dsoRestoreConfirmBtn')?.focus();
    }
}

function closeDsoRestoreModal() {
    dsoPendingId = null;
    const modal = document.getElementById('dsoRestoreModal');
    if (modal) modal.style.display = 'none';
    syncDsoModalBodyLock();
}

function bindDsoRestoreModal() {
    const cancelBtn = document.getElementById('dsoRestoreCancelBtn');
    const confirmBtn = document.getElementById('dsoRestoreConfirmBtn');
    const modal = document.getElementById('dsoRestoreModal');

    cancelBtn?.addEventListener('click', closeDsoRestoreModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeDsoRestoreModal(); });

    confirmBtn?.addEventListener('click', async function () {
        if (!dsoPendingId || dsoIsRestoring) return;
        dsoIsRestoring = true;
        const restoreId = dsoPendingId;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Restoring...';
        if (cancelBtn) cancelBtn.disabled = true;

        try {
            const response = await fetch(DSO_API.restore(restoreId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to restore account.');
            }
            closeDsoRestoreModal();
            showDsoToast(data.message || 'SK Official restored successfully.');
            window.SkLocalCache?.clearByPrefix('sk_fed_deleted_sk_officials_v1_');
            dsoRecords = dsoRecords.filter((r) => String(r.id) !== String(restoreId));
            dsoFiltered = dsoFiltered.filter((r) => String(r.id) !== String(restoreId));
            renderDsoTable();
            await loadDsoRecords();
        } catch (error) {
            showDsoToast(error.message || 'Failed to restore account. Please try again.', 'error');
        } finally {
            dsoIsRestoring = false;
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Restore';
            if (cancelBtn) cancelBtn.disabled = false;
        }
    });
}

function showDsoToast(message, type = 'success') {
    const existing = document.getElementById('dso-toast');
    if (existing) {
        clearTimeout(existing._timer);
        existing.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'dso-toast';
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    Object.assign(toast.style, {
        position: 'fixed',
        top: '72px',
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: '100000',
        maxWidth: 'calc(100vw - 32px)',
        padding: '11px 28px',
        background: type === 'error' ? '#b91c1c' : '#15803d',
        color: '#fff',
        borderRadius: '8px',
        fontSize: '14px',
        fontWeight: '600',
        textAlign: 'center',
        boxShadow: '0 4px 14px rgba(0,0,0,0.2)',
        pointerEvents: 'none',
        transition: 'opacity 0.3s ease',
    });
    document.body.appendChild(toast);

    toast._timer = setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}
