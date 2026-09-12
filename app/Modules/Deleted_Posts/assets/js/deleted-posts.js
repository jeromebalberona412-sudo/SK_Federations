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

    async function loadArchive(page) {
        currentPage = page || 1;
        const search = document.getElementById('archiveSearch')?.value.trim() || '';
        const params = new URLSearchParams({ page: String(currentPage) });
        if (search) params.set('search', search);

        const tbody = document.getElementById('archiveTableBody');
        const cacheKey = 'sk_fed_deleted_posts_v1_' + params.toString();
        const cached = window.SkLocalCache?.get(cacheKey);
        const paintRows = function (rows, meta) {
            archiveRows = rows || [];
            if (!tbody) return;
            if (!archiveRows.length) {
                tbody.innerHTML = '<tr><td colspan="6">No archived posts found.</td></tr>';
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
                    if (!confirm('Permanently delete this archived post? This cannot be undone.')) return;
                    api(cfg.restoreUrl + '/' + id, { method: 'DELETE' })
                        .then(function () {
                            window.SkLocalCache?.clearByPrefix('sk_fed_deleted_posts_v1_');
                            loadArchive(currentPage).catch(showError);
                        })
                        .catch(showError);
                }
            });
        };

        function renderPagination(meta) {
            const pag = document.getElementById('archivePagination');
            if (!pag) return;
            const page = Number(meta.current_page || 1);
            const last = Number(meta.last_page || 1);
            currentPage = page;
            if (last <= 1) {
                pag.innerHTML = '';
                return;
            }
            pag.innerHTML =
                '<div class="archive-pagination-bar">' +
                    '<button type="button" class="archive-page-btn" data-archive-page="' + Math.max(1, page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Prev</button>' +
                    '<span class="archive-page-label">Page ' + page + ' of ' + last + '</span>' +
                    '<button type="button" class="archive-page-btn" data-archive-page="' + Math.min(last, page + 1) + '" ' + (page >= last ? 'disabled' : '') + '>Next</button>' +
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
                        window.SkLocalCache?.set(cacheKey, data);
                        paintRows(data.data || [], {
                            current_page: data.current_page,
                            last_page: data.last_page,
                        });
                    })
                    .catch(function () {});
                return;
            }
        } else if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6">Loading archived posts…</td></tr>';
        }

        const data = await api(cfg.dataUrl + '?' + params.toString());
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
        window.ArchiveRowActions.bindViewModalControls('archive');
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
