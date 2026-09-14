// Archived SK Officials Records Module

function formatRecordName(record) {
    return [record.lastName, record.firstName, record.middleName, record.suffix]
        .filter((part) => part && String(part).trim() !== '')
        .join(', ');
}

const AROFF_API = {
    data: window.__SK_OFFICIALS_RECORDS_CONFIG__?.dataUrl || '/manage-archive/sk-officials-records/data',
};

let aroffRecords = [];
let aroffFiltered = [];
let aroffRequestSeq = 0;
let aroffCurrentPage = 1;
let aroffPerPage = 10;
let aroffSearchQ = '';
let aroffTermFilter = 'all';
let aroffBarangayFilter = 'all';
let aroffPositionFilter = 'all';
let aroffNameSortDir = null;
let aroffCatalog = window.__INITIAL_SK_OFFICIALS_FILTERS__ || {};

function initArchivedSkOfficials() {
    bindAroffSearch();
    bindAroffPagination();
    bindAroffViewModal();
    populateAroffFilters(aroffCatalog);

    if (Array.isArray(window.__INITIAL_SK_OFFICIALS_RECORDS__)) {
        aroffRecords = window.__INITIAL_SK_OFFICIALS_RECORDS__;
        applyAroffFilters();
    }

    if (window.ArchiveRowActions?.initGlobalClose) {
        window.ArchiveRowActions.initGlobalClose();
    }

    if (window.ArchiveFullnameSort?.init) {
        window.ArchiveFullnameSort.init({
            onSort: (dir) => {
                aroffNameSortDir = dir;
                aroffCurrentPage = 1;
                renderAroffTable();
            },
            getCurrentDir: () => aroffNameSortDir,
        });
    }

    loadAroffRecords();
}

async function loadAroffRecords() {
    const requestId = ++aroffRequestSeq;

    try {
        const response = await fetch(`${AROFF_API.data}?search=&term=all&barangay_id=all&position=all`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load archived records.');
        if (requestId !== aroffRequestSeq) return;

        const rows = Array.isArray(payload?.data) ? payload.data : [];
        aroffRecords = rows;
        if (payload?.filters) {
            aroffCatalog = payload.filters;
            populateAroffFilters(aroffCatalog);
        }
        applyAroffFilters();
    } catch (_error) {
        if (requestId === aroffRequestSeq && aroffRecords.length === 0) {
            aroffFiltered = [];
            renderAroffTable();
        }
    }
}

function optionValue(item) {
    if (item && typeof item === 'object') return String(item.value ?? '');
    return String(item ?? '');
}

function optionLabel(item) {
    if (item && typeof item === 'object') return String(item.label ?? item.value ?? '');
    return String(item ?? '');
}

function populateAroffFilters(filters) {
    const fill = (selectId, allLabel, items, current) => {
        const select = document.getElementById(selectId);
        if (!select || !Array.isArray(items)) return;
        const html = [`<option value="all">${allLabel}</option>`]
            .concat(items.map((item) => {
                const value = escapeAroffHtml(optionValue(item));
                const label = escapeAroffHtml(optionLabel(item));
                return `<option value="${value}">${label}</option>`;
            }))
            .join('');
        select.innerHTML = html;
        if ([...select.options].some((option) => option.value === current)) {
            select.value = current;
        } else {
            select.value = 'all';
        }
    };

    fill('aroffBarangayFilter', 'All Barangays', filters.barangays || [], aroffBarangayFilter);
    fill('aroffPositionFilter', 'All Positions', filters.positions || [], aroffPositionFilter);
    fill('aroffTermFilter', 'All Terms', filters.terms || [], aroffTermFilter);
}

function escapeAroffHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function displayValue(value) {
    const text = String(value ?? '').trim();
    return text !== '' ? text : '—';
}

function aroffProfileGroup(iconClass, title, fields) {
    const cells = fields.map(([label, value]) => `
        <div class="account-profile-field">
            <label>${escapeAroffHtml(label)}</label>
            <div class="aroff-view-value">${escapeAroffHtml(displayValue(value))}</div>
        </div>`).join('');

    return `
        <div class="account-profile-group">
            <div class="account-profile-group-label">
                <i class="fa-solid ${iconClass}"></i> ${escapeAroffHtml(title)}
            </div>
            <div class="account-profile-row">${cells}</div>
        </div>`;
}

function matchesAroffTerm(record, termValue) {
    if (!termValue || termValue === 'all') return true;
    if (!termValue.includes('|')) return true;
    const [left, right] = termValue.split('|', 2);
    if (!left || !right) return true;
    if (/^\d{4}-\d{2}-\d{2}$/.test(left) && /^\d{4}-\d{2}-\d{2}$/.test(right)) {
        return String(record.termStartRaw || '') === left && String(record.termEndRaw || '') === right;
    }
    if (/^\d{4}$/.test(left) && /^\d{4}$/.test(right)) {
        return String(record.termStartRaw || '').startsWith(left)
            && String(record.termEndRaw || '').startsWith(right);
    }
    return true;
}

function applyAroffFilters() {
    const query = aroffSearchQ.toLowerCase();
    aroffFiltered = aroffRecords.filter((record) => {
        if (query) {
            const haystack = [
                record.firstName,
                record.lastName,
                record.middleName,
                record.suffix,
                record.position,
                record.barangay,
                record.email,
                formatRecordName(record),
            ].join(' ').toLowerCase();
            if (!haystack.includes(query)) return false;
        }

        if (aroffBarangayFilter !== 'all') {
            if (String(record.barangayId ?? '') !== String(aroffBarangayFilter)
                && String(record.barangay || '') !== String(aroffBarangayFilter)) {
                return false;
            }
        }

        if (aroffPositionFilter !== 'all'
            && String(record.position || '') !== String(aroffPositionFilter)) {
            return false;
        }

        if (!matchesAroffTerm(record, aroffTermFilter)) return false;

        return true;
    });

    const pages = Math.max(1, Math.ceil(aroffFiltered.length / aroffPerPage) || 1);
    if (aroffCurrentPage > pages) aroffCurrentPage = pages;
    renderAroffTable();
}

function getAroffSortedRecords() {
    const list = [...aroffFiltered];
    if (!aroffNameSortDir) return list;
    list.sort((a, b) => {
        const cmp = formatRecordName(a).localeCompare(formatRecordName(b), undefined, { sensitivity: 'base' });
        return aroffNameSortDir === 'asc' ? cmp : -cmp;
    });
    return list;
}

function renderAroffTable() {
    const tbody = document.getElementById('aroffTableBody');
    const info = document.getElementById('aroffPaginationInfo');
    if (!tbody) return;

    const sorted = getAroffSortedRecords();
    const start = (aroffCurrentPage - 1) * aroffPerPage;
    const page = sorted.slice(start, start + aroffPerPage);

    if (aroffFiltered.length === 0) {
        tbody.innerHTML = `<tr class="aroff-empty-row"><td colspan="8">No previous-term records found.</td></tr>`;
        if (info) info.textContent = '0 records';
        renderAroffPagination(0);
        return;
    }

    tbody.innerHTML = page.map((r) => {
        const id = escapeAroffHtml(r.id);
        return `
        <tr>
            <td class="aroff-name-cell">${escapeAroffHtml(formatRecordName(r))}</td>
            <td style="text-align:center;">${escapeAroffHtml(r.position || '—')}</td>
            <td style="text-align:center;">${escapeAroffHtml(r.barangay || '—')}</td>
            <td style="text-align:center;">${escapeAroffHtml(r.termStart || '—')}</td>
            <td style="text-align:center;">${escapeAroffHtml(r.termEnd || '—')}</td>
            <td style="text-align:center;"><span class="aroff-completed-badge">${escapeAroffHtml(r.status || 'EXPIRED')}</span></td>
            <td style="text-align:center;">${escapeAroffHtml(r.archivedAt || '—')}</td>
            <td class="aroff-col-actions">
                <div class="aroff-action-btns">
                    <button type="button" class="aroff-btn-view" data-aroff-view="${id}" aria-label="View record">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `${aroffFiltered.length} record${aroffFiltered.length === 1 ? '' : 's'}`;
    renderAroffPagination(aroffFiltered.length);
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
        aroffCurrentPage = Math.min(pages, Math.max(1, parseInt(this.value, 10) || 1));
        renderAroffTable();
    });
    document.getElementById('aroffRowsPerPageSelect')?.addEventListener('change', function () {
        aroffPerPage = Math.max(1, parseInt(this.value, 10) || 10);
        aroffCurrentPage = 1;
        renderAroffTable();
    });
}

function resetAroffFilters() {
    aroffSearchQ = '';
    aroffBarangayFilter = 'all';
    aroffPositionFilter = 'all';
    aroffTermFilter = 'all';
    aroffCurrentPage = 1;

    const search = document.getElementById('aroffSearch');
    if (search) search.value = '';
    populateAroffFilters(aroffCatalog);
    applyAroffFilters();
}

function bindAroffSearch() {
    const input = document.getElementById('aroffSearch');
    if (input) {
        input.addEventListener('input', function () {
            aroffSearchQ = this.value.trim();
            aroffCurrentPage = 1;
            applyAroffFilters();
        });
    }

    const bindSelect = (id, setter) => {
        const select = document.getElementById(id);
        if (!select || select.dataset.bound === '1') return;
        select.dataset.bound = '1';
        select.addEventListener('change', function () {
            setter(this.value);
            aroffCurrentPage = 1;
            applyAroffFilters();
        });
    };

    bindSelect('aroffBarangayFilter', (value) => { aroffBarangayFilter = value; });
    bindSelect('aroffPositionFilter', (value) => { aroffPositionFilter = value; });
    bindSelect('aroffTermFilter', (value) => { aroffTermFilter = value; });

    document.getElementById('aroffClearFiltersBtn')?.addEventListener('click', resetAroffFilters);
}

function closeAroffViewModal() {
    const modal = document.getElementById('aroffViewModal');
    if (modal) modal.style.display = 'none';
}

function openAroffViewModal(id) {
    const r = aroffRecords.find((x) => String(x.id) === String(id));
    if (!r) return;

    const body = document.getElementById('aroffViewBody');
    if (body) {
        body.innerHTML = `
            <div class="aroff-view-form account-modal-card">
                ${aroffProfileGroup('fa-user', 'Personal Information', [
                    ['First Name', r.firstName],
                    ['Last Name', r.lastName],
                    ['Middle Name', r.middleName],
                    ['Suffix', r.suffix],
                    ['Sex', r.sex],
                    ['Date of Birth', r.dateOfBirth],
                    ['Age', r.age],
                    ['Contact Number', r.contactNumber],
                ])}
                ${aroffProfileGroup('fa-briefcase', 'Position & Account', [
                    ['Position', r.position],
                    ['Email Address', r.email],
                    ['Status', r.status || 'EXPIRED'],
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
                    ['Archived Date', r.archivedAt],
                ])}
            </div>
            <div class="aroff-modal-footer">
                <button type="button" class="aroff-btn-view" id="aroffViewFooterClose">Close</button>
            </div>`;
        document.getElementById('aroffViewFooterClose')?.addEventListener('click', closeAroffViewModal);
    }

    const modal = document.getElementById('aroffViewModal');
    if (modal) modal.style.display = 'flex';
}

function bindAroffViewModal() {
    const tbody = document.getElementById('aroffTableBody');
    if (tbody && tbody.dataset.aroffViewBound !== '1') {
        tbody.dataset.aroffViewBound = '1';
        tbody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-aroff-view]');
            if (!button) return;
            openAroffViewModal(button.getAttribute('data-aroff-view'));
        });
    }

    document.getElementById('aroffViewClose')?.addEventListener('click', closeAroffViewModal);
    document.getElementById('aroffViewModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'aroffViewModal') closeAroffViewModal();
    });

    if (window.ArchiveRowActions?.bindViewModalControls) {
        window.ArchiveRowActions.bindViewModalControls('aroff');
    }
}

function onArchiveReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

onArchiveReady(initArchivedSkOfficials);
