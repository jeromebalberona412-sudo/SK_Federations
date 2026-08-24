(function () {
    'use strict';

    const cfg = window.FedArchiveConfig || {};
    let currentPage = 1;

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

    async function loadArchive(page) {
        currentPage = page || 1;
        const search = document.getElementById('archiveSearch')?.value.trim() || '';
        const params = new URLSearchParams({ page: String(currentPage) });
        if (search) params.set('search', search);

        const tbody = document.getElementById('archiveTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6">Loading archived posts…</td></tr>';

        const data = await api(cfg.dataUrl + '?' + params.toString());
        const rows = data.data || [];

        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6">No archived posts found.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            return '<tr>' +
                '<td>' + escapeHtml(row.type_label) + '</td>' +
                '<td>' + escapeHtml(row.author_name) + '</td>' +
                '<td>' + escapeHtml(formatDate(row.created_at)) + '</td>' +
                '<td>' + escapeHtml(formatDate(row.archived_at)) + '</td>' +
                '<td><span class="days-badge ' + tierClass(row.days_tier) + '">' + escapeHtml(row.auto_delete_label) + '</span></td>' +
                '<td class="col-actions">' +
                    '<button type="button" class="archive-btn archive-btn-primary" data-restore="' + row.id + '">Restore</button> ' +
                    '<button type="button" class="archive-btn archive-btn-danger" data-delete="' + row.id + '">Delete</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadArchive(1).catch(function (e) { alert(e.message); });

        document.getElementById('archiveSearch')?.addEventListener('input', function () {
            clearTimeout(window._fedArchiveSearchTimer);
            window._fedArchiveSearchTimer = setTimeout(function () { loadArchive(1).catch(function (e) { alert(e.message); }); }, 250);
        });

        document.getElementById('archiveTableBody')?.addEventListener('click', function (e) {
            const restoreId = e.target.closest('[data-restore]')?.getAttribute('data-restore');
            const deleteId = e.target.closest('[data-delete]')?.getAttribute('data-delete');

            if (restoreId) {
                api(cfg.restoreUrl + '/' + restoreId + '/restore', { method: 'POST' })
                    .then(function () { loadArchive(currentPage); })
                    .catch(function (err) { alert(err.message); });
            }

            if (deleteId && confirm('Permanently delete this archived post?')) {
                api(cfg.restoreUrl + '/' + deleteId, { method: 'DELETE' })
                    .then(function () { loadArchive(currentPage); })
                    .catch(function (err) { alert(err.message); });
            }
        });
    });
})();
