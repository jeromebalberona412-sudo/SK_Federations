(function () {
    'use strict';

    const cfg = window.FedRejectedAbyipConfig || {};
    let archiveRows = [];
    let pendingRestoreId = null;
    let pendingDeleteId = null;
    let loadSeq = 0;

    function escapeHtml(v) {
        return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    async function api(url, options) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': cfg.csrf || '',
                ...(options?.headers || {}),
            },
            ...options,
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.message || 'Request failed.');
        return data;
    }

    function tierClass(tier) {
        if (tier === 'green') return 'days-green';
        if (tier === 'orange') return 'days-orange';
        return 'days-red';
    }

    function syncBodyLock() {
        const viewOpen = document.getElementById('archiveViewModal')?.style.display === 'flex';
        const restoreOpen = document.getElementById('apRestoreModal')?.style.display === 'flex';
        const deleteOpen = document.getElementById('apPermanentDeleteModal')?.style.display === 'flex';
        document.body.classList.toggle('ap-modal-open', Boolean(viewOpen || restoreOpen || deleteOpen));
    }

    function emptyTableMessage() {
        const tbody = document.getElementById('archiveTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr class="ap-empty-row"><td colspan="6"><span class="ap-empty-message">No rejected ABYIP found.</span></td></tr>';
    }

    function removeRowById(id) {
        archiveRows = archiveRows.filter(function (row) {
            return String(row.id) !== String(id);
        });

        const tr = document.querySelector('#archiveTableBody tr[data-row-id="' + String(id) + '"]');
        if (tr) tr.remove();

        const remaining = document.querySelectorAll('#archiveTableBody tr[data-row-id]');
        if (!remaining.length) {
            emptyTableMessage();
        }
    }

    async function openArchiveViewModal(id) {
        const body = document.getElementById('archiveViewBody');
        const modal = document.getElementById('archiveViewModal');
        if (!body || !modal) return;

        body.innerHTML = '<p class="ap-modal-message">Loading document…</p>';
        modal.style.display = 'flex';
        syncBodyLock();

        try {
            const payload = await api((cfg.showUrl || '') + '/' + id);
            const row = payload.data || {};
            const preview = row.file_url
                ? '<iframe class="ra-preview-frame" title="ABYIP PDF preview" src="' + escapeHtml(row.file_url) + '"></iframe>'
                : (row.document_html
                    ? '<div class="ra-preview-html">' + String(row.document_html) + '</div>'
                    : '<p class="ap-modal-message">No document preview available.</p>');

            body.innerHTML = `
                <div class="account-modal-card">
                    <div class="account-profile-group">
                        <div class="account-profile-group-label"><i class="fa-solid fa-file-lines"></i> Rejected ABYIP Details</div>
                        <div class="account-profile-row">
                            <div class="account-profile-field account-profile-field--wide"><label>Title</label><p>${escapeHtml(row.title || '—')}</p></div>
                            <div class="account-profile-field"><label>Barangay</label><p>${escapeHtml(row.barangay_name || '—')}</p></div>
                            <div class="account-profile-field"><label>CY</label><p>${escapeHtml(row.calendar_year || row.fiscal_year || '—')}</p></div>
                            <div class="account-profile-field"><label>Submitted By</label><p>${escapeHtml(row.submitted_by || '—')}</p></div>
                            <div class="account-profile-field"><label>Rejected Date</label><p>${escapeHtml(formatDate(row.rejected_at))}</p></div>
                            <div class="account-profile-field"><label>Days Left</label><p>${escapeHtml(row.auto_delete_label || '—')}</p></div>
                            <div class="account-profile-field account-profile-field--wide"><label>Rejection Reason</label><p>${escapeHtml(row.rejection_reason || '—')}</p></div>
                            <div class="account-profile-field account-profile-field--wide"><label>Document</label>${preview}</div>
                        </div>
                    </div>
                </div>`;
        } catch (err) {
            body.innerHTML = '<p class="ap-modal-message">' + escapeHtml(err.message || 'Failed to load document.') + '</p>';
        }
    }

    function openRestoreModal(id) {
        const row = archiveRows.find((item) => String(item.id) === String(id));
        if (!row) return;

        pendingRestoreId = id;
        const titleEl = document.getElementById('apRestoreTitle');
        if (titleEl) {
            titleEl.textContent = row.title || 'Rejected ABYIP';
        }

        const modal = document.getElementById('apRestoreModal');
        if (modal) {
            modal.style.display = 'flex';
            syncBodyLock();
        }
    }

    function closeRestoreModal() {
        pendingRestoreId = null;
        const modal = document.getElementById('apRestoreModal');
        if (modal) modal.style.display = 'none';

        const confirmBtn = document.getElementById('apRestoreConfirmBtn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Restore';
        }

        syncBodyLock();
    }

    function syncDeleteConfirmState() {
        const input = document.getElementById('apPermanentDeleteInput');
        const confirmBtn = document.getElementById('apPermanentDeleteConfirm');
        const error = document.getElementById('apPermanentDeleteError');
        if (!input || !confirmBtn) return;

        const value = String(input.value || '').trim().toUpperCase();
        const matched = value === 'DELETE';
        confirmBtn.disabled = !matched;
        confirmBtn.classList.toggle('is-enabled', matched);
        confirmBtn.classList.toggle('is-disabled', !matched);
        if (error) {
            error.style.display = value.length > 0 && !matched ? 'block' : 'none';
        }
    }

    function openPermanentDeleteModal(id) {
        const row = archiveRows.find((item) => String(item.id) === String(id));
        if (!row) return;

        pendingDeleteId = id;
        const message = document.getElementById('apPermanentDeleteMessage');
        if (message) {
            message.textContent = 'Permanently delete “' + (row.title || 'this rejected ABYIP') + '”?';
        }

        const input = document.getElementById('apPermanentDeleteInput');
        if (input) {
            input.value = '';
            input.focus();
        }
        syncDeleteConfirmState();

        const modal = document.getElementById('apPermanentDeleteModal');
        if (modal) {
            modal.style.display = 'flex';
            syncBodyLock();
        }
    }

    function closePermanentDeleteModal() {
        pendingDeleteId = null;
        const modal = document.getElementById('apPermanentDeleteModal');
        if (modal) modal.style.display = 'none';

        const input = document.getElementById('apPermanentDeleteInput');
        if (input) input.value = '';
        syncDeleteConfirmState();

        const confirmBtn = document.getElementById('apPermanentDeleteConfirm');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Delete Permanently';
            confirmBtn.classList.add('is-disabled');
            confirmBtn.classList.remove('is-enabled');
        }

        syncBodyLock();
    }

    function bindRestoreModal() {
        const cancelBtn = document.getElementById('apRestoreCancelBtn');
        const confirmBtn = document.getElementById('apRestoreConfirmBtn');
        const modal = document.getElementById('apRestoreModal');

        cancelBtn?.addEventListener('click', closeRestoreModal);
        modal?.addEventListener('click', function (e) {
            if (e.target === modal) closeRestoreModal();
        });

        confirmBtn?.addEventListener('click', async function () {
            if (!pendingRestoreId) return;

            const id = pendingRestoreId;
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Restoring...';

            try {
                await api((cfg.restoreUrl || '') + '/' + id + '/restore', { method: 'POST' });
                closeRestoreModal();
                removeRowById(id);
            } catch (err) {
                showError(err);
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Restore';
            }
        });
    }

    function bindPermanentDeleteModal() {
        const cancelBtn = document.getElementById('apPermanentDeleteCancel');
        const confirmBtn = document.getElementById('apPermanentDeleteConfirm');
        const modal = document.getElementById('apPermanentDeleteModal');
        const input = document.getElementById('apPermanentDeleteInput');

        cancelBtn?.addEventListener('click', closePermanentDeleteModal);
        modal?.addEventListener('click', function (e) {
            if (e.target === modal) closePermanentDeleteModal();
        });

        input?.addEventListener('input', function (e) {
            e.target.value = String(e.target.value || '')
                .replace(/[^a-zA-Z]/g, '')
                .toUpperCase()
                .slice(0, 6);
            syncDeleteConfirmState();
        });

        input?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !confirmBtn?.disabled) {
                e.preventDefault();
                confirmBtn?.click();
            }
        });

        confirmBtn?.addEventListener('click', async function () {
            if (!pendingDeleteId || confirmBtn.disabled) return;

            const typed = String(input?.value || '').trim().toUpperCase();
            if (typed !== 'DELETE') {
                syncDeleteConfirmState();
                return;
            }

            const id = pendingDeleteId;
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Deleting...';

            try {
                await api((cfg.restoreUrl || '') + '/' + id, { method: 'DELETE' });
                closePermanentDeleteModal();
                removeRowById(id);
            } catch (err) {
                showError(err);
                confirmBtn.textContent = 'Delete Permanently';
                syncDeleteConfirmState();
            }
        });
    }

    function paintRows(rows) {
        const tbody = document.getElementById('archiveTableBody');
        if (!tbody) return;

        archiveRows = rows || [];
        if (!archiveRows.length) {
            emptyTableMessage();
            return;
        }

        tbody.innerHTML = archiveRows.map(function (row) {
            return '<tr data-row-id="' + escapeHtml(row.id) + '">' +
                '<td>' + escapeHtml(row.title || '—') + '</td>' +
                '<td>' + escapeHtml(row.barangay_name || '—') + '</td>' +
                '<td>' + escapeHtml(row.calendar_year || row.fiscal_year || '—') + '</td>' +
                '<td>' + escapeHtml(formatDate(row.rejected_at)) + '</td>' +
                '<td><span class="days-badge ' + tierClass(row.days_tier) + '">' + escapeHtml(row.auto_delete_label) + '</span></td>' +
                '<td class="col-actions">' +
                    window.ArchiveRowActions.renderMenu(row.id, { view: true, restore: true, deletePermanent: true }) +
                '</td>' +
            '</tr>';
        }).join('');

        window.ArchiveRowActions.bind(tbody, function (action, id) {
            if (action === 'view') {
                openArchiveViewModal(id);
                return;
            }
            if (action === 'restore') {
                openRestoreModal(id);
                return;
            }
            if (action === 'delete') {
                openPermanentDeleteModal(id);
            }
        });
    }

    async function loadArchive() {
        const seq = ++loadSeq;
        const search = document.getElementById('archiveSearch')?.value.trim() || '';
        const params = new URLSearchParams();
        if (search) params.set('search', search);

        const url = (cfg.dataUrl || '') + (params.toString() ? ('?' + params.toString()) : '');
        const data = await api(url);
        if (seq !== loadSeq) return;
        paintRows(data.data || []);
    }

    function showError(err) {
        alert(err.message || 'Request failed.');
    }

    function onArchiveReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onArchiveReady(function () {
        if (!window.ArchiveRowActions) {
            console.error('ArchiveRowActions is not available.');
            return;
        }
        window.ArchiveRowActions.initGlobalClose();
        window.ArchiveRowActions.bindViewModalControls('archive', { bodyClass: 'ap-modal-open' });
        bindRestoreModal();
        bindPermanentDeleteModal();

        loadArchive().catch(showError);

        document.getElementById('archiveSearch')?.addEventListener('input', function () {
            clearTimeout(window._fedRejectedAbyipSearchTimer);
            window._fedRejectedAbyipSearchTimer = setTimeout(function () {
                loadArchive().catch(showError);
            }, 150);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (document.getElementById('apPermanentDeleteModal')?.style.display === 'flex') {
                closePermanentDeleteModal();
                return;
            }
            if (document.getElementById('apRestoreModal')?.style.display === 'flex') {
                closeRestoreModal();
            }
        });
    });
})();
