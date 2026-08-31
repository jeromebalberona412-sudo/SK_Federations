(function () {
    'use strict';

    const cfg = window.FedArchiveConfig || {};
    let currentPage = 1;
    let archiveRows = [];
    let pendingRestoreId = null;

    function escapeHtml(v) {
        return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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

    function openArchiveViewModal(id) {
        const row = archiveRows.find((item) => String(item.id) === String(id));
        if (!row) return;

        const body = document.getElementById('archiveViewBody');
        if (body) {
            body.innerHTML = `
                <div class="account-modal-card">
                    <div class="account-profile-group">
                        <div class="account-profile-group-label"><i class="fa-solid fa-newspaper"></i> Post Details</div>
                        <div class="account-profile-row">
                            <div class="account-profile-field"><label>Type</label><p>${escapeHtml(row.type_label)}</p></div>
                            <div class="account-profile-field"><label>Posted By</label><p>${escapeHtml(row.author_name)}</p></div>
                            <div class="account-profile-field"><label>Posted Date</label><p>${escapeHtml(formatDate(row.created_at))}</p></div>
                            <div class="account-profile-field"><label>Archived Date</label><p>${escapeHtml(formatDate(row.archived_at))}</p></div>
                            <div class="account-profile-field account-profile-field--wide"><label>Title</label><p>${escapeHtml(row.title || '—')}</p></div>
                            <div class="account-profile-field account-profile-field--wide"><label>Content</label><p>${escapeHtml(row.body || '—')}</p></div>
                            <div class="account-profile-field"><label>Days Left</label><p>${escapeHtml(row.auto_delete_label)}</p></div>
                        </div>
                    </div>
                </div>`;
        }

        const modal = document.getElementById('archiveViewModal');
        if (modal) modal.style.display = 'flex';
    }

    function openRestoreModal(id) {
        const row = archiveRows.find((item) => String(item.id) === String(id));
        if (!row) return;

        pendingRestoreId = id;
        const titleEl = document.getElementById('apRestoreTitle');
        if (titleEl) {
            titleEl.textContent = row.title || row.type_label || 'Untitled post';
        }

        const modal = document.getElementById('apRestoreModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('ap-modal-open');
        }
    }

    function closeRestoreModal() {
        pendingRestoreId = null;
        const modal = document.getElementById('apRestoreModal');
        if (modal) modal.style.display = 'none';
        document.body.classList.remove('ap-modal-open');

        const confirmBtn = document.getElementById('apRestoreConfirmBtn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Restore';
        }
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

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Restoring...';

            try {
                await api(cfg.restoreUrl + '/' + pendingRestoreId + '/restore', { method: 'POST' });
                closeRestoreModal();
                await loadArchive(currentPage);
            } catch (err) {
                showError(err);
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Restore';
            }
        });
    }

    async function loadArchive(page) {
        currentPage = page || 1;
        const search = document.getElementById('archiveSearch')?.value.trim() || '';
        const params = new URLSearchParams({ page: String(currentPage) });
        if (search) params.set('search', search);

        const tbody = document.getElementById('archiveTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6">Loading archived posts…</td></tr>';

        const data = await api(cfg.dataUrl + '?' + params.toString());
        archiveRows = data.data || [];

        if (!tbody) return;

        if (!archiveRows.length) {
            tbody.innerHTML = '<tr><td colspan="6">No archived posts found.</td></tr>';
            return;
        }

        tbody.innerHTML = archiveRows.map(function (row) {
            return '<tr>' +
                '<td>' + escapeHtml(row.type_label) + '</td>' +
                '<td>' + escapeHtml(row.author_name) + '</td>' +
                '<td>' + escapeHtml(formatDate(row.created_at)) + '</td>' +
                '<td>' + escapeHtml(formatDate(row.archived_at)) + '</td>' +
                '<td><span class="days-badge ' + tierClass(row.days_tier) + '">' + escapeHtml(row.auto_delete_label) + '</span></td>' +
                '<td class="col-actions">' +
                    ArchiveRowActions.renderMenu(row.id, { view: true, restore: true, deletePermanent: true }) +
                '</td>' +
            '</tr>';
        }).join('');

        ArchiveRowActions.bind(tbody, function (action, id) {
            if (action === 'view') {
                openArchiveViewModal(id);
                return;
            }
            if (action === 'restore') {
                openRestoreModal(id);
                return;
            }
            if (action === 'delete') {
                if (!confirm('Permanently delete this archived post? This cannot be undone.')) return;
                api(cfg.restoreUrl + '/' + id, { method: 'DELETE' })
                    .then(function () { loadArchive(currentPage).catch(showError); })
                    .catch(showError);
            }
        });
    }

    function showError(err) {
        alert(err.message || 'Request failed.');
    }

    document.addEventListener('DOMContentLoaded', function () {
        ArchiveRowActions.initGlobalClose();
        ArchiveRowActions.bindViewModalControls('archive');
        bindRestoreModal();

        loadArchive(1).catch(showError);

        document.getElementById('archiveSearch')?.addEventListener('input', function () {
            clearTimeout(window._fedArchiveSearchTimer);
            window._fedArchiveSearchTimer = setTimeout(function () {
                loadArchive(1).catch(showError);
            }, 250);
        });
    });
})();
