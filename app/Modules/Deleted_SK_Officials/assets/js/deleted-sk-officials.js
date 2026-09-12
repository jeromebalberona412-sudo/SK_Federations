// Deleted SK Officials — Live Data, 3-Dot Action Dropdown, View Modal & Restore Confirmation Modal

function onArchiveReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

onArchiveReady(initDeletedSkOfficials);

function formatRecordName(record) {
    if (!record) return '—';
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
    data: () => window.DsoConfig?.dataUrl || '/archived/deleted-sk-officials/data',
    show: (id) => `${(window.DsoConfig?.showUrl || '/archived/deleted-sk-officials').replace(/\/+$/, '')}/${encodeURIComponent(id)}`,
    restore: (id) => `${(window.DsoConfig?.restoreUrl || '/archived/deleted-sk-officials').replace(/\/+$/, '')}/${encodeURIComponent(id)}/restore`,
};

let dsoMasterRecords = [];
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
let dsoSearchTimer = null;

const DSO_POLL_MS = 20000;

function initDeletedSkOfficials() {
    if (window.__DSO_CURRENT_FILTERS__) {
        dsoActiveFilter = window.__DSO_CURRENT_FILTERS__.filter || 'all';
        dsoYearFilter = window.__DSO_CURRENT_FILTERS__.year || 'all';
        dsoActiveBarangay = window.__DSO_CURRENT_FILTERS__.barangay || '';
        dsoActivePosition = window.__DSO_CURRENT_FILTERS__.position || '';
    }

    // Set initial select values if present
    syncFilterSelects();

    bindDsoFullnameSort();
    bindDsoGlobalEvents();
    bindDsoSearch();
    bindDsoFilterTabs();
    bindDsoDropdowns();
    bindDsoPagination();
    bindDsoRestoreModal();
    bindDsoViewModal();
    bindDsoTableActions();

    if (Array.isArray(window.__INITIAL_DELETED_SK_OFFICIALS__) && window.__INITIAL_DELETED_SK_OFFICIALS__.length > 0) {
        dsoMasterRecords = window.__INITIAL_DELETED_SK_OFFICIALS__.map(normalizeDsoRecord);
        dsoRecords = [...dsoMasterRecords];
        dsoFiltered = applyClientFilters(dsoMasterRecords);
        if (window.__INITIAL_DSO_FILTERS__) {
            syncDropdownOptions(window.__INITIAL_DSO_FILTERS__);
        }
        renderDsoTable();
    } else {
        loadDsoRecords();
    }

    startDsoRealtimeRefresh();
}

function syncFilterSelects() {
    const yearEl = document.getElementById('dsoYearFilter');
    if (yearEl && dsoYearFilter) yearEl.value = dsoYearFilter;

    const bgEl = document.getElementById('dsoFilterBarangay');
    if (bgEl && dsoActiveBarangay) bgEl.value = dsoActiveBarangay;

    const posEl = document.getElementById('dsoFilterPosition');
    if (posEl && dsoActivePosition) posEl.value = dsoActivePosition;
}

function startDsoRealtimeRefresh() {
    const canRefresh = () => !document.hidden
        && !anyDsoModalOpen()
        && !dsoIsRestoring
        && !isDsoActionsMenuOpen();

    window.setInterval(() => {
        if (canRefresh()) loadDsoRecords({ silent: true });
    }, DSO_POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (canRefresh()) loadDsoRecords({ silent: true });
    });
}

function getCsrfToken() {
    return window.DsoConfig?.csrf
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('#dsoRestoreForm input[name="_token"]')?.value
        || '';
}

function applyClientFilters(records) {
    const search = (document.getElementById('dsoSearch')?.value || '').trim().toLowerCase();
    const position = (dsoActivePosition || '').trim().toLowerCase();
    const barangay = (dsoActiveBarangay || '').trim().toLowerCase();
    const year = (dsoYearFilter || 'all').trim();
    const tab = dsoActiveFilter || 'all';

    const now = new Date();
    const todayStr = now.toISOString().slice(0, 10);

    const startOfWeek = new Date(now);
    const day = now.getDay();
    const diffToMonday = (day === 0 ? -6 : 1) - day;
    startOfWeek.setDate(now.getDate() + diffToMonday);
    startOfWeek.setHours(0, 0, 0, 0);

    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0);

    return (records || []).filter((r) => {
        // Position filter
        if (position) {
            const rPos = (r.position || '').toLowerCase();
            if (position === 'chairperson' || position === 'sk chairperson') {
                if (rPos !== 'chairperson' && rPos !== 'sk chairperson') return false;
            } else if (rPos !== position) {
                return false;
            }
        }

        // Barangay filter
        if (barangay) {
            const rBrgy = (r.barangay || '').toLowerCase();
            if (rBrgy !== barangay) return false;
        }

        // Year filter
        if (year && year !== 'all') {
            const delDateStr = r.deletedDate || '';
            const dt = r._deletedTs || (r.deleted_at ? new Date(r.deleted_at) : null);
            const rYear = dt ? String(dt.getFullYear()) : (delDateStr.match(/\d{4}/)?.[0] || '');
            if (rYear !== year) return false;
        }

        // Tab filter (today / week / month)
        if (tab === 'today') {
            const dt = r._deletedTs || (r.deleted_at ? new Date(r.deleted_at) : null);
            if (!dt) return false;
            const rDateStr = dt.toISOString().slice(0, 10);
            if (rDateStr !== todayStr) return false;
        } else if (tab === 'week') {
            const dt = r._deletedTs || (r.deleted_at ? new Date(r.deleted_at) : null);
            if (!dt || dt < startOfWeek) return false;
        } else if (tab === 'month') {
            const dt = r._deletedTs || (r.deleted_at ? new Date(r.deleted_at) : null);
            if (!dt || dt < startOfMonth) return false;
        }

        // Search text
        if (search) {
            const fullName = formatRecordName(r).toLowerCase();
            const rBrgy = (r.barangay || '').toLowerCase();
            const rPos = (r.position || '').toLowerCase();
            const rEmail = (r.email || '').toLowerCase();
            const rMuni = (r.municipality || '').toLowerCase();
            if (!fullName.includes(search)
                && !rBrgy.includes(search)
                && !rPos.includes(search)
                && !rEmail.includes(search)
                && !rMuni.includes(search)) {
                return false;
            }
        }

        return true;
    });
}

async function loadDsoRecords(options = {}) {
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
        const incoming = (payload.data || []).map(normalizeDsoRecord);
        dsoRecords = incoming;

        // Merge incoming into master records so client filtering retains full knowledge
        incoming.forEach((rec) => {
            const idx = dsoMasterRecords.findIndex((m) => String(m.id) === String(rec.id));
            if (idx >= 0) {
                dsoMasterRecords[idx] = rec;
            } else {
                dsoMasterRecords.push(rec);
            }
        });

        // If active filters are empty, master is incoming
        if (dsoActiveFilter === 'all' && dsoYearFilter === 'all' && !dsoActiveBarangay && !dsoActivePosition && !params.get('search')) {
            dsoMasterRecords = [...incoming];
        }

        dsoFiltered = incoming;
        syncDropdownOptions(payload.filters || {});
        const pages = Math.max(1, Math.ceil(dsoFiltered.length / dsoPerPage) || 1);
        if (dsoCurrentPage > pages) dsoCurrentPage = pages;
        renderDsoTable();
    };

    if (cached?.payload) {
        paint(cached.payload);
        if (window.SkLocalCache.isFresh(cached, 5 * 60 * 1000)) {
            dsoIsLoading = false;
            fetch(`${DSO_API.data()}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json().then((payload) => ({ ok: response.ok, payload })))
                .then(({ ok, payload }) => {
                    if (!ok) return;
                    window.SkLocalCache?.set(cacheKey, payload);
                    paint(payload);
                })
                .catch(() => {});
            return;
        }
    } else if (!options.silent && dsoFiltered.length === 0) {
        renderDsoLoadingState();
    }

    try {
        const response = await fetch(`${DSO_API.data()}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load deleted records.');

        window.SkLocalCache?.set(cacheKey, payload);
        paint(payload);
    } catch (error) {
        if (!cached?.payload && dsoFiltered.length === 0) {
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
    const configured = Number(window.DsoConfig?.retentionDays || window.__DSO_RETENTION_DAYS__);
    return Number.isFinite(configured) && configured > 0 ? configured : 30;
}

function dsoRetentionTier(daysLeft) {
    if (daysLeft <= 3) return 'red';
    if (daysLeft <= 7) return 'orange';
    return 'green';
}

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

function syncDropdownOptions(filters) {
    ensureSelectOptions('dsoFilterBarangay', filters.barangays || [], dsoActiveBarangay);
    ensureSelectOptions('dsoFilterPosition', filters.positions || [], dsoActivePosition);
    ensureSelectOptions('dsoYearFilter', (filters.years || []).map(String), dsoYearFilter);
}

function ensureSelectOptions(selectId, values, currentVal) {
    const select = document.getElementById(selectId);
    if (!select || !Array.isArray(values) || values.length === 0) return;

    const existingValues = new Set([...select.options].map((opt) => opt.value));

    values.forEach((item) => {
        const val = typeof item === 'object' && item !== null ? String(item.value) : String(item);
        const label = typeof item === 'object' && item !== null ? item.label : item;
        if (!existingValues.has(val)) {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = label;
            select.appendChild(opt);
            existingValues.add(val);
        }
    });

    if (currentVal !== undefined && currentVal !== null) {
        select.value = currentVal;
    }
}

function bindDsoDropdowns() {
    const bgSelect = document.getElementById('dsoFilterBarangay');
    if (bgSelect && bgSelect.dataset.dsoBound !== '1') {
        bgSelect.dataset.dsoBound = '1';
        bgSelect.addEventListener('change', function () {
            dsoActiveBarangay = this.value;
            dsoCurrentPage = 1;
            dsoFiltered = applyClientFilters(dsoMasterRecords);
            renderDsoTable();
            loadDsoRecords();
        });
    }

    const posSelect = document.getElementById('dsoFilterPosition');
    if (posSelect && posSelect.dataset.dsoBound !== '1') {
        posSelect.dataset.dsoBound = '1';
        posSelect.addEventListener('change', function () {
            dsoActivePosition = this.value;
            dsoCurrentPage = 1;
            dsoFiltered = applyClientFilters(dsoMasterRecords);
            renderDsoTable();
            loadDsoRecords();
        });
    }

    const yearSelect = document.getElementById('dsoYearFilter');
    if (yearSelect && yearSelect.dataset.dsoBound !== '1') {
        yearSelect.dataset.dsoBound = '1';
        yearSelect.addEventListener('change', function () {
            dsoYearFilter = this.value || 'all';
            dsoCurrentPage = 1;
            dsoFiltered = applyClientFilters(dsoMasterRecords);
            renderDsoTable();
            loadDsoRecords();
        });
    }
}

function bindDsoFilterTabs() {
    document.querySelectorAll('.dso-tab').forEach((btn) => {
        if (btn.dataset.dsoBound === '1') return;
        btn.dataset.dsoBound = '1';

        btn.addEventListener('click', function () {
            document.querySelectorAll('.dso-tab').forEach((b) => b.classList.remove('active'));
            this.classList.add('active');
            dsoActiveFilter = this.dataset.filter || 'all';
            dsoCurrentPage = 1;
            dsoFiltered = applyClientFilters(dsoMasterRecords);
            renderDsoTable();
            loadDsoRecords();
        });
    });
}

function bindDsoSearch() {
    const searchInput = document.getElementById('dsoSearch');
    if (!searchInput || searchInput.dataset.dsoBound === '1') return;
    searchInput.dataset.dsoBound = '1';

    searchInput.addEventListener('input', function () {
        dsoCurrentPage = 1;
        // Instant client filter
        dsoFiltered = applyClientFilters(dsoMasterRecords);
        renderDsoTable();

        clearTimeout(dsoSearchTimer);
        dsoSearchTimer = setTimeout(() => {
            loadDsoRecords();
        }, 280);
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
    if (!btn || btn.dataset.dsoBound === '1') return;
    btn.dataset.dsoBound = '1';

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

/* ── 3-Dot Action Dropdown Menu & Positioning ── */

function isDsoActionsMenuOpen() {
    return Boolean(document.querySelector('.ar-row-actions-menu.is-open'));
}

function closeAllDsoActionMenus(exceptMenu = null) {
    document.querySelectorAll('.ar-row-actions-menu.is-open').forEach((menu) => {
        if (exceptMenu && menu === exceptMenu) return;
        menu.classList.remove('is-open');
        const trigger = menu.querySelector('.ar-row-actions-trigger');
        const dropdown = menu.querySelector('.ar-row-actions-dropdown');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        if (dropdown) {
            dropdown.hidden = true;
            dropdown.classList.remove('is-floating');
            dropdown.style.position = '';
            dropdown.style.top = '';
            dropdown.style.right = '';
            dropdown.style.left = '';
        }
    });
}

function positionDsoDropdown(menu) {
    const trigger = menu.querySelector('.ar-row-actions-trigger');
    const dropdown = menu.querySelector('.ar-row-actions-dropdown');
    if (!trigger || !dropdown) return;

    dropdown.hidden = false;
    dropdown.classList.add('is-floating');
    dropdown.style.position = 'fixed';
    dropdown.style.zIndex = '1400';

    const rect = trigger.getBoundingClientRect();
    const width = dropdown.offsetWidth || 155;
    const height = dropdown.offsetHeight || 96;

    let top = rect.bottom + 4;
    if (top + height > window.innerHeight - 8) {
        top = Math.max(8, rect.top - height - 4);
    }

    let right = Math.max(8, window.innerWidth - rect.right);
    if (right + width > window.innerWidth - 8) {
        right = 8;
    }

    dropdown.style.top = `${top}px`;
    dropdown.style.right = `${right}px`;
    dropdown.style.left = 'auto';
}

function bindDsoGlobalEvents() {
    if (document.body.dataset.dsoGlobalBound === '1') return;
    document.body.dataset.dsoGlobalBound = '1';

    document.addEventListener('click', (event) => {
        // Close sort menu if click outside
        const sortMenu = document.getElementById('dsoFullnameSortMenu');
        if (sortMenu && !sortMenu.hidden
            && !sortMenu.contains(event.target)
            && !event.target.closest('#dsoFullnameSortBtn')) {
            closeDsoSortMenu();
        }

        // Close 3-dot row action menus if click outside
        if (!event.target.closest('.ar-row-actions-menu')) {
            closeAllDsoActionMenus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeAllDsoActionMenus();
        closeDsoSortMenu();
        if (document.getElementById('dsoViewModal')?.style.display === 'flex') {
            closeDsoViewModal();
        }
        if (document.getElementById('dsoRestoreModal')?.style.display === 'flex' && !dsoIsRestoring) {
            closeDsoRestoreModal();
        }
    });

    window.addEventListener('scroll', () => {
        closeDsoSortMenu();
        closeAllDsoActionMenus();
    }, true);

    window.addEventListener('resize', () => {
        closeDsoSortMenu();
        closeAllDsoActionMenus();
    });
}

function renderDsoTable() {
    const tbody = document.getElementById('dsoTableBody');
    const info = document.getElementById('dsoPaginationInfo');
    if (!tbody) return;

    closeAllDsoActionMenus();

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

        const actionsHtml = `
            <div class="ar-row-actions-menu">
                <button type="button"
                        class="ar-row-actions-trigger"
                        aria-label="More actions for ${escapeDsoHtml(fullName)}"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        title="More actions">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="5" cy="12" r="2"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                        <circle cx="19" cy="12" r="2"></circle>
                    </svg>
                </button>
                <div class="ar-row-actions-dropdown" role="menu" hidden>
                    <button type="button"
                            class="ar-row-actions-item"
                            data-action="view"
                            data-ar-action="view"
                            data-id="${escapeDsoHtml(r.id)}"
                            role="menuitem"
                            title="View Details"
                            aria-label="View details for ${escapeDsoHtml(fullName)}">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                        <span>View</span>
                    </button>
                    <button type="button"
                            class="ar-row-actions-item"
                            data-action="restore"
                            data-ar-action="restore"
                            data-id="${escapeDsoHtml(r.id)}"
                            role="menuitem"
                            title="Restore Account"
                            aria-label="Restore account for ${escapeDsoHtml(fullName)}">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        <span>Restore</span>
                    </button>
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
}

function bindDsoTableActions() {
    const tbody = document.getElementById('dsoTableBody');
    if (!tbody || tbody.dataset.dsoActionsBound === '1') return;
    tbody.dataset.dsoActionsBound = '1';

    tbody.addEventListener('click', (e) => {
        // 1. Toggle 3-dot trigger
        const trigger = e.target.closest('.ar-row-actions-trigger');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            const menu = trigger.closest('.ar-row-actions-menu');
            if (!menu) return;

            const isOpen = menu.classList.contains('is-open');
            closeAllDsoActionMenus();

            if (!isOpen) {
                menu.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                positionDsoDropdown(menu);
            }
            return;
        }

        // 2. Click action item inside dropdown (View / Restore)
        const actionBtn = e.target.closest('.ar-row-actions-item, [data-action], [data-ar-action]');
        if (!actionBtn) return;

        const action = actionBtn.getAttribute('data-action') || actionBtn.getAttribute('data-ar-action');
        const id = actionBtn.getAttribute('data-id') || actionBtn.closest('tr')?.dataset?.recordId;
        if (!id || !action) return;

        e.preventDefault();
        e.stopPropagation();
        closeAllDsoActionMenus();

        if (action === 'view') {
            openDsoViewModal(id);
        } else if (action === 'restore') {
            openDsoRestoreModal(id);
        }
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

function findDsoRecord(id) {
    if (!id) return null;
    const strId = String(id);
    return dsoMasterRecords.find((r) => String(r.id) === strId || String(r.archive_id) === strId || String(r.original_user_id) === strId)
        || dsoRecords.find((r) => String(r.id) === strId || String(r.archive_id) === strId || String(r.original_user_id) === strId)
        || dsoFiltered.find((r) => String(r.id) === strId || String(r.archive_id) === strId || String(r.original_user_id) === strId)
        || null;
}

function anyDsoModalOpen() {
    return ['dsoViewModal', 'dsoRestoreModal']
        .some((modalId) => document.getElementById(modalId)?.style.display === 'flex');
}

function syncDsoModalBodyLock() {
    document.body.classList.toggle('dso-modal-open', anyDsoModalOpen());
}

/* ── View Modal Flow ── */

async function openDsoViewModal(id) {
    if (!id) return;
    const modal = document.getElementById('dsoViewModal');
    const body = document.getElementById('dsoViewBody');
    if (!modal || !body) return;

    let r = findDsoRecord(id);
    if (!r) {
        body.innerHTML = `
            <div class="dso-modal-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading official details...</p>
            </div>
        `;
        modal.style.display = 'flex';
        syncDsoModalBodyLock();

        try {
            const res = await fetch(DSO_API.show(id), {
                headers: { Accept: 'application/json' },
            });
            const payload = await res.json().catch(() => ({}));
            if (payload && payload.success && payload.data) {
                r = normalizeDsoRecord(payload.data);
                dsoMasterRecords.push(r);
            }
        } catch (_) {}
    }

    if (!r) {
        closeDsoViewModal();
        showDsoToast('This record is no longer available. Refreshing the list...', 'error');
        loadDsoRecords();
        return;
    }

    modal.dataset.currentId = String(r.id);
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
        </div>
    `;

    const footerRestoreBtn = document.getElementById('dsoViewFooterRestoreBtn');
    if (footerRestoreBtn) {
        footerRestoreBtn.setAttribute('data-id', String(r.id));
    }

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
    const closeBtn = document.getElementById('dsoViewClose');
    const footerCloseBtn = document.getElementById('dsoViewFooterCloseBtn');
    const footerRestoreBtn = document.getElementById('dsoViewFooterRestoreBtn');
    const maximizeBtn = document.getElementById('dsoViewMaximizeBtn');
    const restoreBtn = document.getElementById('dsoViewRestoreBtn');

    closeBtn?.addEventListener('click', closeDsoViewModal);
    footerCloseBtn?.addEventListener('click', closeDsoViewModal);

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeDsoViewModal();
    });

    footerRestoreBtn?.addEventListener('click', () => {
        const id = footerRestoreBtn.getAttribute('data-id') || modal?.dataset?.currentId;
        if (id) {
            openDsoRestoreModal(id);
        }
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

/* ── Restore Confirmation Modal Flow ── */

async function openDsoRestoreModal(id) {
    if (!id) return;
    let record = findDsoRecord(id);
    if (!record) {
        try {
            const res = await fetch(DSO_API.show(id), {
                headers: { Accept: 'application/json' },
            });
            const payload = await res.json().catch(() => ({}));
            if (payload && payload.success && payload.data) {
                record = normalizeDsoRecord(payload.data);
                dsoMasterRecords.push(record);
            }
        } catch (_) {}
    }

    if (!record) {
        showDsoToast('This record is no longer available. Refreshing the list...', 'error');
        loadDsoRecords();
        return;
    }

    // Close view modal if it was open
    const viewModal = document.getElementById('dsoViewModal');
    if (viewModal && viewModal.style.display === 'flex') {
        closeDsoViewModal();
    }

    dsoPendingId = id;

    const idInput = document.getElementById('dsoRestoreAccountId');
    if (idInput) idInput.value = String(id);

    const nameEl = document.getElementById('dsoRestoreName');
    if (nameEl) nameEl.textContent = formatRecordName(record);

    const posEl = document.getElementById('dsoRestorePosition');
    if (posEl) posEl.textContent = record.position || 'SK Official';

    const brgyEl = document.getElementById('dsoRestoreBarangay');
    if (brgyEl) brgyEl.textContent = record.barangay || '—';

    const confirmBtn = document.getElementById('dsoRestoreConfirmBtn');
    const cancelBtn = document.getElementById('dsoRestoreCancelBtn');
    const closeBtn = document.getElementById('dsoRestoreCloseBtn');
    if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-rotate-left" aria-hidden="true"></i> <span>Restore</span>';
    }
    if (cancelBtn) cancelBtn.disabled = false;
    if (closeBtn) closeBtn.disabled = false;

    const modal = document.getElementById('dsoRestoreModal');
    if (modal) {
        modal.style.display = 'flex';
        syncDsoModalBodyLock();
        confirmBtn?.focus();
    }
}

function closeDsoRestoreModal() {
    if (dsoIsRestoring) return;
    dsoPendingId = null;
    const modal = document.getElementById('dsoRestoreModal');
    if (modal) modal.style.display = 'none';
    syncDsoModalBodyLock();
}

function bindDsoRestoreModal() {
    const cancelBtn = document.getElementById('dsoRestoreCancelBtn');
    const closeBtn = document.getElementById('dsoRestoreCloseBtn');
    const confirmBtn = document.getElementById('dsoRestoreConfirmBtn');
    const form = document.getElementById('dsoRestoreForm');
    const modal = document.getElementById('dsoRestoreModal');

    cancelBtn?.addEventListener('click', closeDsoRestoreModal);
    closeBtn?.addEventListener('click', closeDsoRestoreModal);

    modal?.addEventListener('click', (e) => {
        if (e.target === modal && !dsoIsRestoring) {
            closeDsoRestoreModal();
        }
    });

    const submitRestore = async (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const restoreId = dsoPendingId || document.getElementById('dsoRestoreAccountId')?.value;
        if (!restoreId || dsoIsRestoring) return;

        dsoIsRestoring = true;
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> <span>Restoring...</span>';
        }
        if (cancelBtn) cancelBtn.disabled = true;
        if (closeBtn) closeBtn.disabled = true;

        try {
            const response = await fetch(DSO_API.restore(restoreId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    account_id: restoreId,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to restore account.');
            }

            closeDsoRestoreModal();
            showDsoToast(data.message || 'SK Official restored successfully.');
            window.SkLocalCache?.clearByPrefix('sk_fed_deleted_sk_officials_v1_');

            const idStr = String(restoreId);
            dsoMasterRecords = dsoMasterRecords.filter((r) => String(r.id) !== idStr && String(r.archive_id) !== idStr);
            dsoRecords = dsoRecords.filter((r) => String(r.id) !== idStr && String(r.archive_id) !== idStr);
            dsoFiltered = dsoFiltered.filter((r) => String(r.id) !== idStr && String(r.archive_id) !== idStr);
            renderDsoTable();

            await loadDsoRecords({ silent: true });
        } catch (error) {
            showDsoToast(error.message || 'Failed to restore account. Please try again.', 'error');
        } finally {
            dsoIsRestoring = false;
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-rotate-left" aria-hidden="true"></i> <span>Restore</span>';
            }
            if (cancelBtn) cancelBtn.disabled = false;
            if (closeBtn) closeBtn.disabled = false;
        }
    };

    form?.addEventListener('submit', submitRestore);
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
