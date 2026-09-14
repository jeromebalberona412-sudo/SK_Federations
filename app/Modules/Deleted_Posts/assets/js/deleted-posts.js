(function () {
    'use strict';

    const cfg = window.FedArchiveConfig || {};
    let currentPage = 1;
    let archiveRows = [];
    let pendingRestoreId = null;
    let pendingDeleteId = null;
    let loadSeq = 0;

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
        if (modal) {
            modal.style.display = 'flex';
            syncBodyLock();
        }
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

    function syncBodyLock() {
        const viewOpen = document.getElementById('archiveViewModal')?.style.display === 'flex';
        const restoreOpen = document.getElementById('apRestoreModal')?.style.display === 'flex';
        const deleteOpen = document.getElementById('apPermanentDeleteModal')?.style.display === 'flex';
        document.body.classList.toggle('ap-modal-open', Boolean(viewOpen || restoreOpen || deleteOpen));
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
            const label = row.title || row.type_label || 'this archived post';
            message.textContent = `Permanently delete “${label}”?`;
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

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Restoring...';

            try {
                await api(cfg.restoreUrl + '/' + pendingRestoreId + '/restore', { method: 'POST' });
                window.SkLocalCache?.clearByPrefix('sk_fed_deleted_posts_v1_');
                closeRestoreModal();
                await loadArchive(currentPage);
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

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Deleting...';

            try {
                await api(cfg.restoreUrl + '/' + pendingDeleteId, { method: 'DELETE' });
                window.SkLocalCache?.clearByPrefix('sk_fed_deleted_posts_v1_');
                closePermanentDeleteModal();
                await loadArchive(currentPage);
            } catch (err) {
                showError(err);
                confirmBtn.textContent = 'Delete Permanently';
                syncDeleteConfirmState();
            }
        });
    }

    async function loadArchive(page) {
        const seq = ++loadSeq;
        currentPage = page || 1;
        const search = document.getElementById('archiveSearch')?.value.trim() || '';
        const params = new URLSearchParams({ page: String(currentPage) });
        if (search) params.set('search', search);

        const tbody = document.getElementById('archiveTableBody');
        const cacheKey = 'sk_fed_deleted_posts_v1_' + params.toString();
        const cached = window.SkLocalCache?.get(cacheKey);

        const paintRows = function (rows, meta) {
            if (seq !== loadSeq) return;
            archiveRows = rows || [];
            if (!tbody) return;
            if (!archiveRows.length) {
                tbody.innerHTML = '<tr class="ap-empty-row"><td colspan="6"><span class="ap-empty-message">No archived posts found.</span></td></tr>';
                renderPagination(meta || { current_page: 1, last_page: 1 });
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
                        window.ArchiveRowActions.renderMenu(row.id, { view: true, restore: true, deletePermanent: true }) +
                    '</td>' +
                '</tr>';
            }).join('');

            renderPagination(meta || { current_page: currentPage, last_page: 1 });

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
        };

        function renderPagination(meta) {
            const pag = document.getElementById('archivePagination');
            if (!pag) return;
            const pageNum = Number(meta.current_page || 1);
            const last = Number(meta.last_page || 1);
            currentPage = pageNum;
            if (last <= 1) {
                pag.innerHTML = '';
                return;
            }
            pag.innerHTML =
                '<div class="archive-pagination-bar">' +
                    '<button type="button" class="archive-page-btn" data-archive-page="' + Math.max(1, pageNum - 1) + '" ' + (pageNum <= 1 ? 'disabled' : '') + '>Prev</button>' +
                    '<span class="archive-page-label">Page ' + pageNum + ' of ' + last + '</span>' +
                    '<button type="button" class="archive-page-btn" data-archive-page="' + Math.min(last, pageNum + 1) + '" ' + (pageNum >= last ? 'disabled' : '') + '>Next</button>' +
                '</div>';
            pag.querySelectorAll('[data-archive-page]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const next = Number(btn.getAttribute('data-archive-page') || 1);
                    if (next === currentPage) return;
                    loadArchive(next).catch(showError);
                });
            });
        }

        if (cached?.payload) {
            paintRows(cached.payload.data || [], {
                current_page: cached.payload.current_page,
                last_page: cached.payload.last_page,
            });
            if (window.SkLocalCache.isFresh(cached, 5 * 60 * 1000)) {
                api(cfg.dataUrl + '?' + params.toString())
                    .then(function (data) {
                        if (seq !== loadSeq) return;
                        window.SkLocalCache?.set(cacheKey, data);
                        paintRows(data.data || [], {
                            current_page: data.current_page,
                            last_page: data.last_page,
                        });
                    })
                    .catch(function () {});
                return;
            }
        }

        const data = await api(cfg.dataUrl + '?' + params.toString());
        if (seq !== loadSeq) return;
        window.SkLocalCache?.set(cacheKey, data);
        paintRows(data.data || [], {
            current_page: data.current_page,
            last_page: data.last_page,
        });
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

        loadArchive(1).catch(showError);

        document.getElementById('archiveSearch')?.addEventListener('input', function () {
            clearTimeout(window._fedArchiveSearchTimer);
            window._fedArchiveSearchTimer = setTimeout(function () {
                loadArchive(1).catch(showError);
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
