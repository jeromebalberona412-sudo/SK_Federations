document.addEventListener('DOMContentLoaded', () => {
    initDeletedCalendarNotes();
});

let dcnRecords = [];
let dcnCurrentPage = 1;
let dcnPerPage = 10;
let dcnPendingRestoreId = null;
let dcnPendingDeleteId = null;
let dcnActiveFilter = 'all';
let dcnSearchQuery = '';
let dcnIsLoading = false;
let dcnSearchTimer = null;
let dcnLoadSeq = 0;
let dcnFooterApi = null;
let dcnActionsBound = false;

const DCN_TITLE_MAX = 36;
const DCN_CONTENT_MAX = 72;

function initDeletedCalendarNotes() {
    bindSearch();
    bindFilterTabs();
    bindRestoreModal();
    bindRestoreConflictModal();
    bindPermanentDeleteModal();
    bindViewModal();
    bindFooter();
    bindRowActions();
    loadData();
}

function bindFooter() {
    if (typeof window.bindTablePageFooter !== 'function') {
        return;
    }

    dcnFooterApi = window.bindTablePageFooter({
        prefix: 'dcn',
        getTotalRecords: () => dcnRecords.length,
        getCurrentPage: () => dcnCurrentPage,
        setCurrentPage: (page) => {
            dcnCurrentPage = page;
        },
        getRecordsPerPage: () => dcnPerPage,
        setRecordsPerPage: (n) => {
            dcnPerPage = n;
        },
        onPageChange: () => renderTable(),
    });
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function escHtml(value) {
    if (value == null || value === '') return '—';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function truncateDisplay(value, maxLen) {
    const text = String(value ?? '')
        .replace(/\r\n|\r|\n/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (!text) return { display: '—', full: '' };
    if (text.length <= maxLen) return { display: text, full: text };
    return {
        display: `${text.slice(0, Math.max(1, maxLen - 1))}…`,
        full: text,
    };
}

function bindRowActions() {
    if (dcnActionsBound) return;
    dcnActionsBound = true;

    document.addEventListener('click', (e) => {
        const item = e.target.closest('.row-actions-item[data-action]');
        if (!item || item.disabled) return;

        const dropdown = item.closest('.row-actions-dropdown');
        const ownerId = dropdown?.dataset?.ownerMenu;
        const menu = item.closest('.row-actions-menu')
            || (ownerId ? document.querySelector(`.row-actions-menu[data-menu-id="${ownerId}"]`) : null);

        if (!menu || menu.dataset.dcnActions !== '1') return;

        const action = item.dataset.action;
        const id = item.dataset.id;
        if (!id) return;

        if (action === 'view') openViewModal(id);
        if (action === 'restore') openRestoreModal(id);
        if (action === 'delete') openPermanentDeleteModal(id);
    });
}

function bindSearch() {
    const input = document.getElementById('dcnSearch');
    if (!input) return;

    input.addEventListener('input', () => {
        clearTimeout(dcnSearchTimer);
        dcnSearchTimer = setTimeout(() => {
            dcnSearchQuery = input.value.trim();
            dcnCurrentPage = 1;
            loadData();
        }, 250);
    });
}

function bindFilterTabs() {
    document.querySelectorAll('.deleted-calendar-notes-page .filter-tab').forEach((btn) => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.deleted-calendar-notes-page .filter-tab').forEach((b) => {
                b.classList.remove('active');
            });
            this.classList.add('active');
            dcnActiveFilter = this.dataset.filter || 'all';

            const labels = {
                all: 'All Deleted Notes',
                today: 'Deleted Today',
                week: 'Deleted This Week',
                month: 'Deleted This Month',
            };
            const label = document.getElementById('dcnSectionLabel');
            if (label) label.textContent = labels[dcnActiveFilter] || 'Deleted Notes';

            dcnCurrentPage = 1;
            loadData();
        });
    });
}

async function loadData() {
    const seq = ++dcnLoadSeq;
    dcnIsLoading = true;
    setTableLoading(true);

    const params = new URLSearchParams();
    if (dcnSearchQuery) params.set('search', dcnSearchQuery);
    if (dcnActiveFilter !== 'all') params.set('filter', dcnActiveFilter);

    try {
        const res = await fetch(`/deleted-calendar-notes/data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('Failed to load deleted calendar notes.');
        const json = await res.json();
        if (seq !== dcnLoadSeq) return;

        dcnRecords = Array.isArray(json.data) ? json.data : [];
        dcnCurrentPage = 1;
        renderTable();
    } catch (err) {
        if (seq !== dcnLoadSeq) return;
        showToast(err.message || 'Failed to load deleted calendar notes.', 'error');
        dcnRecords = [];
        dcnCurrentPage = 1;
        renderTable();
    } finally {
        if (seq === dcnLoadSeq) {
            dcnIsLoading = false;
        }
    }
}

function setTableLoading(loading) {
    const tbody = document.getElementById('dcnTableBody');
    if (!tbody || !loading) return;
    tbody.innerHTML = `<tr class="empty-state-row"><td colspan="7">Loading deleted notes…</td></tr>`;
}

function formatAutoDeleteShort(daysRemaining) {
    const days = Number(daysRemaining);
    if (!Number.isFinite(days) || days <= 0) return 'Soon';
    if (days === 1) return 'In 1 day';
    return `In ${days} days`;
}

function renderTable() {
    const tbody = document.getElementById('dcnTableBody');
    if (!tbody) return;

    const total = dcnRecords.length;
    const totalPages = Math.max(1, Math.ceil(total / dcnPerPage) || 1);
    if (dcnCurrentPage > totalPages) dcnCurrentPage = totalPages;
    if (dcnCurrentPage < 1) dcnCurrentPage = 1;

    const pageItems = typeof window.paginateSlice === 'function'
        ? window.paginateSlice(dcnRecords, dcnCurrentPage, dcnPerPage)
        : dcnRecords.slice((dcnCurrentPage - 1) * dcnPerPage, dcnCurrentPage * dcnPerPage);

    if (total === 0) {
        tbody.innerHTML = `<tr class="empty-state-row"><td colspan="7">No deleted calendar notes found.</td></tr>`;
        dcnFooterApi?.updateFooter();
        return;
    }

    tbody.innerHTML = pageItems
        .map((r) => {
            const daysLeft = r.days_remaining ?? 30;
            const urgent = daysLeft <= 7 ? ' is-urgent' : '';
            const titleBits = truncateDisplay(r.title, DCN_TITLE_MAX);
            const contentBits = truncateDisplay(r.content, DCN_CONTENT_MAX);
            const autoLabel = formatAutoDeleteShort(daysLeft);
            const ellipsis = window.ROW_ACTIONS_ELLIPSIS || '⋯';

            return `
        <tr data-id="${r.id}">
            <td class="dcn-col-title">
                <span class="dcn-cell-ellipsis dcn-title-text" title="${escHtml(titleBits.full || titleBits.display)}">${escHtml(titleBits.display)}</span>
            </td>
            <td class="dcn-col-date">
                <span class="dcn-cell-ellipsis" title="${escHtml(r.note_date_label)}">${escHtml(r.note_date_label)}</span>
            </td>
            <td class="dcn-col-content">
                <span class="dcn-cell-ellipsis dcn-content-preview" title="${escHtml(contentBits.full || contentBits.display)}">${escHtml(contentBits.display)}</span>
            </td>
            <td class="dcn-col-deleted-date">
                <span class="deleted-at-badge dcn-cell-ellipsis" title="${escHtml(r.deleted_date)}">${escHtml(r.deleted_date)}</span>
            </td>
            <td class="dcn-col-deleted-time">
                <span class="deleted-time-badge dcn-cell-ellipsis" title="${escHtml(r.deleted_time)}">${escHtml(r.deleted_time)}</span>
            </td>
            <td class="dcn-col-auto">
                <span class="dcn-auto-delete-badge dcn-cell-ellipsis${urgent}" title="${escHtml(r.auto_delete_label || autoLabel)}">${escHtml(autoLabel)}</span>
            </td>
            <td class="col-actions">
                <div class="row-actions-menu" data-dcn-actions="1">
                    <button type="button" class="row-actions-trigger" aria-label="Actions" aria-haspopup="true" aria-expanded="false">${ellipsis}</button>
                    <div class="row-actions-dropdown" role="menu">
                        <button type="button" class="row-actions-item row-actions-item-view" data-action="view" data-id="${r.id}" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>View</span>
                        </button>
                        <button type="button" class="row-actions-item row-actions-item-restore" data-action="restore" data-id="${r.id}" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                            <span>Restore</span>
                        </button>
                        <button type="button" class="row-actions-item row-actions-item-danger" data-action="delete" data-id="${r.id}" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <span>Delete permanently</span>
                        </button>
                    </div>
                </div>
            </td>
        </tr>`;
        })
        .join('');

    dcnFooterApi?.updateFooter();

    if (typeof window.bindRowActionsTable === 'function') {
        window.bindRowActionsTable(tbody);
    }
}

function findRecord(id) {
    return dcnRecords.find((r) => String(r.id) === String(id)) || null;
}

function bindViewModal() {
    const modal = document.getElementById('dcnViewModal');
    const closeBtn = document.getElementById('dcnViewClose');
    closeBtn?.addEventListener('click', closeViewModal);
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeViewModal();
    });
}

function openViewModal(id) {
    const record = findRecord(id);
    if (!record) return;

    document.getElementById('dcnViewNoteTitle').textContent = record.title || '—';
    document.getElementById('dcnViewNoteContent').textContent = record.content || '';
    document.getElementById('dcnViewNoteDate').textContent = record.note_date_label || '—';
    document.getElementById('dcnViewDeleted').textContent =
        `${record.deleted_date || '—'} · ${record.deleted_time || ''}`;
    document.getElementById('dcnViewAutoDelete').textContent = record.auto_delete_label || '—';

    const modal = document.getElementById('dcnViewModal');
    if (modal) modal.style.display = 'flex';
}

function closeViewModal() {
    const modal = document.getElementById('dcnViewModal');
    if (modal) modal.style.display = 'none';
}

function bindRestoreModal() {
    document.getElementById('dcnRestoreCancelBtn')?.addEventListener('click', closeRestoreModal);
    document.getElementById('dcnRestoreConfirmBtn')?.addEventListener('click', confirmRestore);
    document.getElementById('dcnRestoreModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'dcnRestoreModal') closeRestoreModal();
    });
}

function openRestoreModal(id) {
    const record = findRecord(id);
    if (!record) return;

    dcnPendingRestoreId = id;
    const nameEl = document.getElementById('dcnRestoreName');
    if (nameEl) {
        nameEl.textContent = `${record.title || 'Untitled'} (${record.note_date_label || '—'})`;
    }
    const modal = document.getElementById('dcnRestoreModal');
    if (modal) modal.style.display = 'flex';
}

function closeRestoreModal() {
    dcnPendingRestoreId = null;
    const modal = document.getElementById('dcnRestoreModal');
    if (modal) modal.style.display = 'none';
}

function syncDcnPermanentDeleteButton() {
    const input = document.getElementById('dcnPermanentDeleteInput');
    const btn = document.getElementById('dcnPermanentDeleteConfirm');
    if (!btn) return;
    const ok = (input?.value || '') === 'CONFIRM';
    btn.disabled = !ok;
    btn.classList.toggle('is-enabled', ok);
    btn.classList.toggle('is-disabled', !ok);
}

function bindPermanentDeleteModal() {
    document.getElementById('dcnPermanentDeleteCancel')?.addEventListener('click', closePermanentDeleteModal);
    document.getElementById('dcnPermanentDeleteConfirm')?.addEventListener('click', confirmPermanentDelete);
    document.getElementById('dcnPermanentDeleteModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'dcnPermanentDeleteModal') closePermanentDeleteModal();
    });
    document.getElementById('dcnPermanentDeleteInput')?.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^a-zA-Z]/g, '').toUpperCase().slice(0, 7);
        const err = document.getElementById('dcnPermanentDeleteError');
        if (err) err.style.display = 'none';
        syncDcnPermanentDeleteButton();
    });
}

function openPermanentDeleteModal(id) {
    const record = findRecord(id);
    if (!record) return;

    dcnPendingDeleteId = id;
    const msg = document.getElementById('dcnPermanentDeleteMessage');
    if (msg) {
        msg.textContent = `Permanently delete "${record.title || 'Untitled'}" (${record.note_date_label || '—'})?`;
    }
    const input = document.getElementById('dcnPermanentDeleteInput');
    const err = document.getElementById('dcnPermanentDeleteError');
    if (input) input.value = '';
    if (err) {
        err.style.display = 'none';
        err.textContent = '';
    }
    syncDcnPermanentDeleteButton();
    const modal = document.getElementById('dcnPermanentDeleteModal');
    if (modal) modal.style.display = 'flex';
    input?.focus();
}

function closePermanentDeleteModal() {
    dcnPendingDeleteId = null;
    const modal = document.getElementById('dcnPermanentDeleteModal');
    if (modal) modal.style.display = 'none';
}

async function confirmPermanentDelete() {
    if (!dcnPendingDeleteId || dcnIsLoading) return;

    const input = document.getElementById('dcnPermanentDeleteInput');
    if ((input?.value || '') !== 'CONFIRM') {
        const err = document.getElementById('dcnPermanentDeleteError');
        if (err) {
            err.textContent = 'Please type CONFIRM to permanently delete.';
            err.style.display = 'block';
        }
        return;
    }

    const id = dcnPendingDeleteId;
    const confirmBtn = document.getElementById('dcnPermanentDeleteConfirm');
    const defaultHtml = confirmBtn?.innerHTML;

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Deleting…';
    }

    try {
        const res = await fetch(`/deleted-calendar-notes/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(json.message || 'Failed to permanently delete note.');
        }

        closePermanentDeleteModal();
        showStatusBanner('Calendar note deleted successfully.');
        await loadData();
    } catch (err) {
        showStatusBanner(err.message || 'Failed to permanently delete note.', 'error');
        syncDcnPermanentDeleteButton();
    } finally {
        if (confirmBtn) {
            confirmBtn.innerHTML = defaultHtml || 'Delete Permanently';
            syncDcnPermanentDeleteButton();
        }
    }
}

async function confirmRestore() {
    if (!dcnPendingRestoreId || dcnIsLoading) return;

    const id = dcnPendingRestoreId;
    const confirmBtn = document.getElementById('dcnRestoreConfirmBtn');
    const defaultHtml = confirmBtn?.innerHTML;

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Restoring…';
    }

    try {
        const res = await fetch(`/deleted-calendar-notes/${id}/restore`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            if (res.status === 422 && (json.code === 'restore_conflict' || /already exists/i.test(String(json.message || '')))) {
                closeRestoreModal();
                openRestoreConflictModal(json);
                return;
            }
            throw new Error(json.message || 'Failed to restore note.');
        }

        closeRestoreModal();
        showStatusBanner(json.message || 'Note restored to Calendar.');
        await loadData();
    } catch (err) {
        showStatusBanner(err.message || 'Failed to restore note.', 'error');
    } finally {
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = defaultHtml || 'Restore';
        }
    }
}

function bindRestoreConflictModal() {
    document.getElementById('dcnRestoreConflictOkBtn')?.addEventListener('click', closeRestoreConflictModal);
    document.getElementById('dcnRestoreConflictModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'dcnRestoreConflictModal') closeRestoreConflictModal();
    });
}

function openRestoreConflictModal(payload = {}) {
    const titleEl = document.getElementById('dcnRestoreConflictTitle');
    const messageEl = document.getElementById('dcnRestoreConflictMessage');
    const title = payload.title || 'Cannot restore calendar note';
    const message = payload.message
        || 'A calendar note already exists for this date. Please delete or move the existing note before restoring this archived note.';

    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;

    const modal = document.getElementById('dcnRestoreConflictModal');
    if (modal) modal.style.display = 'flex';
}

function closeRestoreConflictModal() {
    const modal = document.getElementById('dcnRestoreConflictModal');
    if (modal) modal.style.display = 'none';
}

function showStatusBanner(message, type = 'success', detail = '') {
    const banner = document.getElementById('dcnStatusBanner');
    const titleEl = document.getElementById('dcnStatusBannerTitle');
    const detailEl = document.getElementById('dcnStatusBannerDetail');
    const iconEl = document.getElementById('dcnStatusBannerIcon');
    if (!banner || !titleEl) return;

    titleEl.textContent = message;
    if (detailEl) {
        if (detail) {
            detailEl.textContent = detail;
            detailEl.style.display = 'block';
        } else {
            detailEl.textContent = '';
            detailEl.style.display = 'none';
        }
    }
    if (iconEl) iconEl.textContent = type === 'error' ? '!' : '✓';

    banner.classList.toggle('is-error', type === 'error');
    banner.classList.add('show');
    banner.style.display = 'flex';

    clearTimeout(showStatusBanner._timer);
    showStatusBanner._timer = setTimeout(() => {
        banner.classList.remove('show');
        banner.style.display = 'none';
    }, type === 'error' ? 7000 : 4500);
}

function showRestoreBanner(message) {
    showStatusBanner(message, 'success');
}

function showToast(message, type = 'success') {
    showStatusBanner(message, type === 'error' ? 'error' : 'success');
}
