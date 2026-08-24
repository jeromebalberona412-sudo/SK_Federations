document.addEventListener('DOMContentLoaded', function () {
    const shell = document.getElementById('dashboardAuditApp');
    if (!shell) {
        return;
    }

    const dataUrl = shell.dataset.auditDataUrl || '';
    const state = {
        page: 1,
        perPage: 10,
        rows: [],
        meta: { total: 0, from: 0, to: 0, last_page: 1, current_page: 1 },
    };

    const els = {
        tableBody: document.getElementById('dashAuditLogsTableBody'),
        tableSubtitle: document.getElementById('dashAuditTableSubtitle'),
        modal: document.getElementById('dashAuditDetailsModal'),
        modalSubtitle: document.getElementById('dashAuditModalSubtitle'),
        detailGrid: document.getElementById('dashAuditDetailGrid'),
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isAdminRole(row) {
        const roleKey = String(row.role_key || '').toLowerCase();
        return roleKey === 'sk_fed' || roleKey === 'admin' || roleKey === 'super_admin'
            || row.role === 'Admin' || row.role === 'SK Federation';
    }

    function renderRows(rows) {
        if (!els.tableBody) {
            return;
        }

        if (!rows.length) {
            els.tableBody.innerHTML = '<tr><td colspan="7" class="dash-audit-empty-row">No audit logs found.</td></tr>';
            return;
        }

        els.tableBody.innerHTML = rows.map(function (row, index) {
            return `
                <tr>
                    <td class="cell-primary dash-audit-col-date">${escapeHtml(row.created_date)}</td>
                    <td class="dash-audit-col-time">${escapeHtml(row.created_time)}</td>
                    <td class="dash-audit-cell-email dash-audit-col-email">${escapeHtml(row.user_email || '-')}</td>
                    <td class="dash-audit-col-role">${escapeHtml(row.role)}</td>
                    <td class="dash-audit-col-event">${escapeHtml(row.event_type)}</td>
                    <td class="dash-audit-col-ip">${escapeHtml(row.ip_address)}</td>
                    <td>
                        <button type="button" class="dash-audit-details-btn" data-row-index="${index}">View</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderTableSubtitle(meta) {
        if (els.tableSubtitle) {
            const shown = state.rows.length;
            els.tableSubtitle.textContent = meta.total
                ? `Showing latest ${shown} of ${meta.total} audit logs`
                : 'No audit logs available';
        }
    }

    async function fetchLogs() {
        if (!dataUrl) {
            return;
        }

        const params = new URLSearchParams({
            page: String(state.page),
            per_page: String(state.perPage),
        });

        try {
            const response = await fetch(`${dataUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load audit logs');
            }

            const payload = await response.json();
            state.rows = (payload.data || []).slice(0, state.perPage);
            state.meta = payload.meta || state.meta;
            renderRows(state.rows);
            renderTableSubtitle(state.meta);
        } catch (error) {
            if (els.tableSubtitle) {
                els.tableSubtitle.textContent = 'Unable to load audit logs. Please try again.';
            }
            if (els.tableBody) {
                els.tableBody.innerHTML = '<tr><td colspan="7" class="dash-audit-empty-row">Unable to load audit logs.</td></tr>';
            }
        }
    }

    function openDetailsModal(row) {
        if (!els.modal || !row) {
            return;
        }

        const detailFields = [
            ['Date', row.created_date],
            ['Time', row.created_time],
            ['Email', row.user_email || '-'],
            ['Role', row.role],
            ['Event Type', row.event_type],
            ['IP Address', row.ip_address],
        ];

        if (!isAdminRole(row) && row.barangay && row.barangay !== '-') {
            detailFields.splice(4, 0, ['Barangay', row.barangay]);
        }

        if (els.modalSubtitle) {
            els.modalSubtitle.textContent = row.summary || 'Activity details';
        }

        if (els.detailGrid) {
            els.detailGrid.innerHTML = detailFields.map(function ([label, value]) {
                return `
                    <div class="dash-audit-detail-item">
                        <span>${escapeHtml(label)}</span>
                        <strong>${escapeHtml(value || '-')}</strong>
                    </div>
                `;
            }).join('');
        }

        els.modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeDetailsModal() {
        if (!els.modal) {
            return;
        }

        els.modal.hidden = true;
        document.body.style.overflow = '';
    }

    els.tableBody?.addEventListener('click', function (event) {
        const button = event.target.closest('.dash-audit-details-btn');
        if (!button) {
            return;
        }

        const index = Number(button.dataset.rowIndex);
        openDetailsModal(state.rows[index]);
    });

    document.querySelectorAll('[data-dash-audit-close]').forEach(function (el) {
        el.addEventListener('click', closeDetailsModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && els.modal && !els.modal.hidden) {
            closeDetailsModal();
        }
    });

    fetchLogs();
});
