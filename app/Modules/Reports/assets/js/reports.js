(function () {
    'use strict';

    const config = window.reportsConfig || {};
    let reports = [];

    const BARANGAYS = [
        'Alipit', 'Bagumbayan', 'Bubukal', 'Calios', 'Duhat', 'Gatid', 'Jasaan',
        'Labuin', 'Malinao', 'Oogong', 'Pagsawitan', 'Palasan', 'Patimbao',
        'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Poblacion V',
        'San Jose', 'San Juan', 'San Pablo Norte', 'San Pablo Sur',
        'Santisima Cruz', 'Santo Angel Central', 'Santo Angel Norte', 'Santo Angel Sur',
    ];

    document.addEventListener('DOMContentLoaded', function () {
        populateBarangayFilter();
        bindEvents();
        loadReports();
    });

    function populateBarangayFilter() {
        const select = document.getElementById('reportsBarangayFilter');
        if (!select) return;

        BARANGAYS.forEach(function (name) {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            select.appendChild(option);
        });
    }

    function bindEvents() {
        document.getElementById('reportsSearch')?.addEventListener('input', loadReports);
        document.getElementById('reportsBarangayFilter')?.addEventListener('change', loadReports);
        document.getElementById('reportsStatusFilter')?.addEventListener('change', loadReports);
    }

    async function loadReports() {
        const tbody = document.getElementById('reportsTableBody');
        if (!tbody) return;

        const params = new URLSearchParams();
        const search = (document.getElementById('reportsSearch')?.value || '').trim();
        const barangay = document.getElementById('reportsBarangayFilter')?.value || '';
        const status = document.getElementById('reportsStatusFilter')?.value || '';

        if (search) params.set('search', search);
        if (barangay) params.set('barangay', barangay);
        if (status) params.set('status', status);

        try {
            const url = config.listUrl + (params.toString() ? '?' + params.toString() : '');
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load reports');
            }

            const payload = await response.json();
            reports = payload.data || [];
            renderTable();
        } catch (error) {
            console.error(error);
            tbody.innerHTML = '<tr><td colspan="7" class="reports-empty">Unable to load reports.</td></tr>';
        }
    }

    function renderTable() {
        const tbody = document.getElementById('reportsTableBody');
        if (!tbody) return;

        if (reports.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="reports-empty">No reports uploaded yet.</td></tr>';
            return;
        }

        tbody.innerHTML = reports.map(function (report) {
            return (
                '<tr>' +
                '<td>' + escapeHtml(report.barangay || '—') + '</td>' +
                '<td>' + escapeHtml(report.programCode + '. ' + report.programName) + '</td>' +
                '<td>' + escapeHtml(report.activity) + '</td>' +
                '<td>' + escapeHtml(report.fileName) + '</td>' +
                '<td>' + formatDate(report.uploadedAt) + '</td>' +
                '<td>' + renderStatus(report.status) + '</td>' +
                '<td>' +
                    (report.downloadUrl
                        ? '<a class="reports-action-btn" href="' + escapeHtml(report.downloadUrl) + '" target="_blank" rel="noopener">View PDF</a>'
                        : '—') +
                '</td>' +
                '</tr>'
            );
        }).join('');
    }

    function renderStatus(status) {
        const normalized = String(status || 'pending').toLowerCase();
        const label = normalized.charAt(0).toUpperCase() + normalized.slice(1);
        return '<span class="reports-status reports-status-' + normalized + '">' + escapeHtml(label) + '</span>';
    }

    function formatDate(value) {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
})();
