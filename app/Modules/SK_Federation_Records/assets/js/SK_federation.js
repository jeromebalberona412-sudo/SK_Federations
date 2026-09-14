// Archived SK Federation Records Module

function formatRecordName(record) {
    return [record.lastName, record.firstName, record.middleName, record.suffix]
        .filter((part) => {
            const text = String(part ?? '').trim();
            if (text === '') return false;
            const lower = text.toLowerCase();
            return lower !== 'none' && lower !== 'n/a' && lower !== 'na' && text !== '-' && text !== '—';
        })
        .join(', ');
}

const ARFED_API = {
    data: window.__SK_FEDERATION_RECORDS_CONFIG__?.dataUrl || '/manage-archive/sk-federation-records/data',
};

const ARFED_POSITION_ORDER = [
    'President',
    'Vice President',
    'Secretary',
    'Treasurer',
    'PIO',
    'Sergeant at Arms',
];

let arfedRecords = [];
let arfedFiltered = [];
let arfedRequestSeq = 0;
let arfedCurrentPage = 1;
let arfedPerPage = 10;
let arfedSearchQ = '';
let arfedTermFilter = 'all';
let arfedBarangayFilter = 'all';
let arfedPositionFilter = 'all';
let arfedNameSortDir = null;
let arfedCatalog = window.__INITIAL_SK_FEDERATION_FILTERS__ || {};

function initArchivedSkFederation() {
    bindArfedSearch();
    bindArfedPagination();
    bindArfedViewModal();
    populateArfedFilters(arfedCatalog);

    if (Array.isArray(window.__INITIAL_SK_FEDERATION_RECORDS__)) {
        arfedRecords = window.__INITIAL_SK_FEDERATION_RECORDS__;
        applyArfedFilters();
    }

    if (window.ArchiveRowActions?.initGlobalClose) {
        window.ArchiveRowActions.initGlobalClose();
    }

    if (window.ArchiveFullnameSort?.init) {
        window.ArchiveFullnameSort.init({
            onSort: (dir) => {
                arfedNameSortDir = dir;
                arfedCurrentPage = 1;
                renderArfedTable();
            },
            getCurrentDir: () => arfedNameSortDir,
        });
    }

    loadArfedRecords();
}

async function loadArfedRecords() {
    const requestId = ++arfedRequestSeq;

    try {
        const response = await fetch(`${ARFED_API.data}?search=&term=all&barangay_id=all&position=all`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const payload = await response.json();
        if (!response.ok) throw new Error('Failed to load archived records.');
        if (requestId !== arfedRequestSeq) return;

        const rows = Array.isArray(payload?.data) ? payload.data : [];
        arfedRecords = rows;
        if (payload?.filters) {
            arfedCatalog = payload.filters;
            populateArfedFilters(arfedCatalog);
        }
        applyArfedFilters();
    } catch (_error) {
        if (requestId === arfedRequestSeq && arfedRecords.length === 0) {
            arfedFiltered = [];
            renderArfedTable();
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

function populateArfedFilters(filters) {
    const fill = (selectId, allLabel, items, current) => {
        const select = document.getElementById(selectId);
        if (!select || !Array.isArray(items)) return;
        const html = [`<option value="all">${allLabel}</option>`]
            .concat(items.map((item) => {
                const value = escapeArfedHtml(optionValue(item));
                const label = escapeArfedHtml(optionLabel(item));
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

    fill('arfedBarangayFilter', 'All Barangays', filters.barangays || [], arfedBarangayFilter);
    fill('arfedPositionFilter', 'All Positions', filters.positions || [], arfedPositionFilter);
    fill('arfedTermFilter', 'All Terms', filters.terms || [], arfedTermFilter);
}

function escapeArfedHtml(value) {
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

function displaySuffixValue(value) {
    const text = String(value ?? '').trim();
    if (text === '' || text === '—' || text === '-'
        || text.toLowerCase() === 'none'
        || text.toLowerCase() === 'n/a'
        || text.toLowerCase() === 'na') {
        return 'None';
    }
    return text;
}

function limitedEmailDisplay(value, maxLen = 64) {
    const text = String(value ?? '').trim();
    if (text === '' || text === '—') return { display: '—', title: '' };
    if (text.length <= maxLen) return { display: text, title: text };
    return { display: `${text.slice(0, maxLen)}…`, title: text };
}

function arfedProfileGroup(iconClass, title, fields) {
    const cells = fields.map(([label, value]) => {
        const isEmail = String(label).toLowerCase().includes('email');
        if (isEmail) {
            const email = limitedEmailDisplay(value);
            return `
        <div class="account-profile-field">
            <label>${escapeArfedHtml(label)}</label>
            <div class="arfed-view-value" title="${escapeArfedHtml(email.title)}">${escapeArfedHtml(email.display)}</div>
        </div>`;
        }
        return `
        <div class="account-profile-field">
            <label>${escapeArfedHtml(label)}</label>
            <div class="arfed-view-value">${escapeArfedHtml(displayValue(value))}</div>
        </div>`;
    }).join('');

    return `
        <div class="account-profile-group">
            <div class="account-profile-group-label">
                <i class="fa-solid ${iconClass}"></i> ${escapeArfedHtml(title)}
            </div>
            <div class="account-profile-row">${cells}</div>
        </div>`;
}

function federationPositionRank(position) {
    const index = ARFED_POSITION_ORDER.indexOf(String(position || '').trim());
    return index === -1 ? 999 : index;
}

function matchesArfedTerm(record, termValue) {
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

function applyArfedFilters() {
    const query = arfedSearchQ.toLowerCase();
    arfedFiltered = arfedRecords.filter((record) => {
        if (query) {
            const haystack = [
                record.firstName,
                record.lastName,
                record.middleName,
                record.suffix,
                record.federationPosition,
                record.position,
                record.barangay,
                record.email,
                formatRecordName(record),
            ].join(' ').toLowerCase();
            if (!haystack.includes(query)) return false;
        }

        if (arfedBarangayFilter !== 'all') {
            if (String(record.barangayId ?? '') !== String(arfedBarangayFilter)
                && String(record.barangay || '') !== String(arfedBarangayFilter)) {
                return false;
            }
        }

        if (arfedPositionFilter !== 'all') {
            const position = String(record.federationPosition || record.position || '');
            if (position !== String(arfedPositionFilter)) return false;
        }

        if (!matchesArfedTerm(record, arfedTermFilter)) return false;

        return true;
    });

    const pages = Math.max(1, Math.ceil(arfedFiltered.length / arfedPerPage) || 1);
    if (arfedCurrentPage > pages) arfedCurrentPage = pages;
    renderArfedTable();
}

function getArfedSortedRecords() {
    const list = [...arfedFiltered];
    if (arfedNameSortDir) {
        list.sort((a, b) => {
            const cmp = formatRecordName(a).localeCompare(formatRecordName(b), undefined, { sensitivity: 'base' });
            return arfedNameSortDir === 'asc' ? cmp : -cmp;
        });
        return list;
    }

    list.sort((a, b) => {
        const rankDiff = federationPositionRank(a.federationPosition || a.position)
            - federationPositionRank(b.federationPosition || b.position);
        if (rankDiff !== 0) return rankDiff;
        return formatRecordName(a).localeCompare(formatRecordName(b), undefined, { sensitivity: 'base' });
    });
    return list;
}

function renderArfedTable() {
    const tbody = document.getElementById('arfedTableBody');
    const info = document.getElementById('arfedPaginationInfo');
    if (!tbody) return;

    const sorted = getArfedSortedRecords();
    const start = (arfedCurrentPage - 1) * arfedPerPage;
    const page = sorted.slice(start, start + arfedPerPage);

    if (arfedFiltered.length === 0) {
        tbody.innerHTML = `<tr class="arfed-empty-row"><td colspan="7">No previous-term records found.</td></tr>`;
        if (info) info.textContent = '0 records';
        renderArfedPagination(0);
        return;
    }

    tbody.innerHTML = page.map((r) => {
        const id = escapeArfedHtml(r.id);
        return `
        <tr>
            <td class="arfed-name-cell">${escapeArfedHtml(formatRecordName(r))}</td>
            <td style="text-align:center;">${escapeArfedHtml(r.federationPosition || r.position || '—')}</td>
            <td style="text-align:center;">${escapeArfedHtml(r.termStart || '—')}</td>
            <td style="text-align:center;">${escapeArfedHtml(r.termEnd || '—')}</td>
            <td style="text-align:center;"><span class="arfed-completed-badge">${escapeArfedHtml(r.status || r.termStatus || 'EXPIRED')}</span></td>
            <td style="text-align:center;">${escapeArfedHtml(r.archivedAt || '—')}</td>
            <td class="arfed-col-actions">
                <div class="arfed-action-btns">
                    <button type="button" class="arfed-btn-view" data-arfed-view="${id}" aria-label="View record">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (info) info.textContent = `${arfedFiltered.length} record${arfedFiltered.length === 1 ? '' : 's'}`;
    renderArfedPagination(arfedFiltered.length);
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

function resetArfedFilters() {
    arfedSearchQ = '';
    arfedBarangayFilter = 'all';
    arfedPositionFilter = 'all';
    arfedTermFilter = 'all';
    arfedCurrentPage = 1;

    const search = document.getElementById('arfedSearch');
    if (search) search.value = '';
    populateArfedFilters(arfedCatalog);
    applyArfedFilters();
}

function bindArfedSearch() {
    const input = document.getElementById('arfedSearch');
    if (input) {
        input.addEventListener('input', function () {
            arfedSearchQ = this.value.trim();
            arfedCurrentPage = 1;
            applyArfedFilters();
        });
    }

    const bindSelect = (id, setter) => {
        const select = document.getElementById(id);
        if (!select || select.dataset.bound === '1') return;
        select.dataset.bound = '1';
        select.addEventListener('change', function () {
            setter(this.value);
            arfedCurrentPage = 1;
            applyArfedFilters();
        });
    };

    bindSelect('arfedBarangayFilter', (value) => { arfedBarangayFilter = value; });
    bindSelect('arfedPositionFilter', (value) => { arfedPositionFilter = value; });
    bindSelect('arfedTermFilter', (value) => { arfedTermFilter = value; });

    document.getElementById('arfedClearFiltersBtn')?.addEventListener('click', resetArfedFilters);
}

function closeArfedViewModal() {
    const modal = document.getElementById('arfedViewModal');
    if (modal) modal.style.display = 'none';
}

function openArfedViewModal(id) {
    const r = arfedRecords.find((x) => String(x.id) === String(id));
    if (!r) return;

    const body = document.getElementById('arfedViewBody');
    if (body) {
        body.innerHTML = `
            <div class="arfed-view-form account-modal-card">
                ${arfedProfileGroup('fa-user', 'Personal Information', [
                    ['First Name', r.firstName],
                    ['Last Name', r.lastName],
                    ['Middle Name', r.middleName],
                    ['Suffix', displaySuffixValue(r.suffix)],
                    ['Sex', r.sex],
                    ['Date of Birth', r.dateOfBirth],
                    ['Age', r.age],
                    ['Contact Number', r.contactNumber],
                ])}
                ${arfedProfileGroup('fa-briefcase', 'Federation Position & Account', [
                    ['Federation Position', r.federationPosition || r.position],
                    ['Email Address', r.email],
                    ['Status', r.status || r.termStatus || 'EXPIRED'],
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
                    ['Archived Date', r.archivedAt],
                ])}
            </div>
            <div class="arfed-modal-footer">
                <button type="button" class="arfed-btn-view" id="arfedViewFooterClose">Close</button>
            </div>`;
        document.getElementById('arfedViewFooterClose')?.addEventListener('click', closeArfedViewModal);
    }

    const modal = document.getElementById('arfedViewModal');
    if (modal) modal.style.display = 'flex';
}

function bindArfedViewModal() {
    const tbody = document.getElementById('arfedTableBody');
    if (tbody && tbody.dataset.arfedViewBound !== '1') {
        tbody.dataset.arfedViewBound = '1';
        tbody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-arfed-view]');
            if (!button) return;
            openArfedViewModal(button.getAttribute('data-arfed-view'));
        });
    }

    document.getElementById('arfedViewClose')?.addEventListener('click', closeArfedViewModal);
    document.getElementById('arfedViewModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'arfedViewModal') closeArfedViewModal();
    });

    if (window.ArchiveRowActions?.bindViewModalControls) {
        window.ArchiveRowActions.bindViewModalControls('arfed');
    }
}

function onArchiveReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

onArchiveReady(initArchivedSkFederation);
