// Barangay ABYIP — Federation review module
(function () {
    'use strict';

    const config = window.barangayAbyipConfig || {};
    const itemsPerPage = 10;
    let currentPage = 1;
    let submissions = [];
    let currentSubmissionId = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async function apiFetch(url, options) {
        const opts = Object.assign({
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }, options || {});

        if (opts.body && typeof opts.body === 'string' && !opts.headers['Content-Type']) {
            opts.headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, opts);
        const payload = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(payload.message || 'Request failed.');
        }

        return payload;
    }

    function statusClass(status) {
        const normalized = String(status || 'pending').toLowerCase();
        if (normalized === 'approved') return 'status-approved';
        if (normalized === 'rejected') return 'status-rejected';
        return 'status-pending';
    }

    function statusLabel(status) {
        const normalized = String(status || 'pending').toLowerCase();
        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pdfPreviewSrc(fileUrl) {
        const base = String(fileUrl || '').split('#')[0];
        return base + '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
    }

    function setResizeButtonState(isFullscreen) {
        const btn = document.getElementById('fullscreenBtn');
        if (!btn) return;
        btn.classList.toggle('is-restore', !!isFullscreen);
        btn.title = isFullscreen ? 'Restore Down' : 'Maximize';
        btn.setAttribute('aria-label', btn.title);
    }

    function populateModalFields(item) {
        document.getElementById('modalBarangay').textContent = item.barangay || 'N/A';
        document.getElementById('modalDateSubmitted').textContent = item.date_submitted || 'N/A';
        document.getElementById('modalSubmittedBy').textContent = item.submitted_by || 'N/A';
        document.getElementById('modalTitle').textContent = item.title || 'N/A';
        document.getElementById('modalSubmittedTime').textContent = item.submitted_time || 'N/A';
        document.getElementById('modalFiscalYear').textContent = item.fiscal_year ? String(item.fiscal_year) : 'N/A';
        const roleEl = document.getElementById('modalSubmittedRole');
        if (roleEl) {
            roleEl.textContent = item.submitted_by_role || 'N/A';
        }

        const statusBadge = document.getElementById('modalStatus');
        statusBadge.textContent = statusLabel(item.status);
        statusBadge.className = 'status-badge ' + statusClass(item.status);

        const modalActions = document.getElementById('modalActions');
        modalActions.style.display = String(item.status).toLowerCase() === 'pending' ? 'flex' : 'none';

        const rejectionNotice = document.getElementById('modalRejectionReason');
        if (rejectionNotice) {
            const reason = String(item.rejection_reason || '').trim();
            if (String(item.status).toLowerCase() === 'rejected' && reason) {
                rejectionNotice.textContent = reason;
                rejectionNotice.style.display = 'block';
            } else {
                rejectionNotice.textContent = '';
                rejectionNotice.style.display = 'none';
            }
        }
    }

    function renderSubmissionPreview(item) {
        const preview = document.getElementById('abyipPreviewMount');
        if (!preview) return;

        if (item.document_html) {
            preview.innerHTML = '<div class="abyip-html-preview">' + item.document_html + '</div>';
            return;
        }

        if (item.has_pdf && item.file_url) {
            preview.innerHTML =
                '<div class="abyip-pdf-clip"><iframe src="' + escapeHtml(pdfPreviewSrc(item.file_url)) + '" class="abyip-pdf-frame" title="ABYIP PDF"></iframe></div>';
            return;
        }

        preview.innerHTML = '<p class="preview-empty">No preview available for this submission.</p>';
    }

    function populateBarangayFilter() {
        const select = document.getElementById('barangayFilter');
        if (!select) return;

        const current = select.value;
        const names = [...new Set(submissions.map(function (item) { return item.barangay; }).filter(Boolean))].sort();
        select.innerHTML = '<option value="all">All Barangays</option>' +
            names.map(function (name) {
                return '<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>';
            }).join('');
        select.value = current || 'all';
    }

    function getFilteredRows() {
        const barangayFilter = document.getElementById('barangayFilter')?.value || 'all';
        const dateFilter = document.getElementById('dateFilter')?.value || 'all';
        const searchTerm = (document.getElementById('abyipSearchInput')?.value || '').toLowerCase();

        return submissions.filter(function (item) {
            const barangayMatch = barangayFilter === 'all' || item.barangay === barangayFilter;
            const searchMatch = !searchTerm || JSON.stringify(item).toLowerCase().includes(searchTerm);
            const dateMatch = isDateInRange(item.date_submitted_raw, dateFilter);
            return barangayMatch && searchMatch && dateMatch;
        });
    }

    function renderTable() {
        const tbody = document.getElementById('abyipTableBody');
        if (!tbody) return;

        const filtered = getFilteredRows();
        const totalPages = Math.max(1, Math.ceil(filtered.length / itemsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * itemsPerPage;
        const pageItems = filtered.slice(start, start + itemsPerPage);

        if (!pageItems.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No ABYIP submissions found.</td></tr>';
        } else {
            tbody.innerHTML = pageItems.map(function (item) {
                return '<tr class="abyip-row" data-id="' + item.id + '" data-barangay="' + escapeHtml(item.barangay) + '" data-date="' + escapeHtml(item.date_submitted_raw || '') + '" data-status="' + escapeHtml(item.status) + '">' +
                    '<td>' + escapeHtml(item.title) + '</td>' +
                    '<td>' + escapeHtml(item.barangay) + '</td>' +
                    '<td>' + escapeHtml(item.date_submitted) + '</td>' +
                    '<td>' + escapeHtml(item.submitted_by) + '</td>' +
                    '<td>' + escapeHtml(item.submitted_time) + '</td>' +
                    '<td><span class="status-badge ' + statusClass(item.status) + '">' + escapeHtml(statusLabel(item.status)) + '</span></td>' +
                    '<td><button type="button" class="view-btn-text" onclick="openViewModal(this)">View</button></td>' +
                    '</tr>';
            }).join('');
        }

        document.getElementById('abyipStart').textContent = filtered.length ? String(start + 1) : '0';
        document.getElementById('abyipEnd').textContent = String(Math.min(start + itemsPerPage, filtered.length));
        document.getElementById('abyipTotal').textContent = String(filtered.length);

        document.querySelectorAll('[onclick="prevAbyipPage()"]').forEach(function (btn) {
            btn.disabled = currentPage <= 1;
        });
        document.querySelectorAll('[onclick="nextAbyipPage()"]').forEach(function (btn) {
            btn.disabled = currentPage >= totalPages;
        });
    }

    async function loadSubmissions() {
        const payload = await apiFetch(config.listUrl || '/api/barangay-abyip');
        submissions = Array.isArray(payload.data) ? payload.data : [];
        populateBarangayFilter();
        currentPage = 1;
        renderTable();
    }

    window.filterAbyipSubmissions = function () {
        currentPage = 1;
        renderTable();
    };

    window.performAbyipSearch = function () {
        filterAbyipSubmissions();
    };

    window.nextAbyipPage = function () {
        const filtered = getFilteredRows();
        const totalPages = Math.ceil(filtered.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage += 1;
            renderTable();
        }
    };

    window.prevAbyipPage = function () {
        if (currentPage > 1) {
            currentPage -= 1;
            renderTable();
        }
    };

    function isDateInRange(dateStr, filter) {
        if (filter === 'all' || !dateStr) return true;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const rowDate = new Date(dateStr);
        rowDate.setHours(0, 0, 0, 0);
        const daysDiff = Math.floor((today - rowDate) / (1000 * 60 * 60 * 24));
        if (filter === 'today') return daysDiff === 0;
        if (filter === 'week') return daysDiff >= 0 && daysDiff < 7;
        if (filter === 'month') return daysDiff >= 0 && daysDiff < 30;
        return true;
    }

    window.openViewModal = function (button) {
        const row = button.closest('.abyip-row');
        const id = row?.getAttribute('data-id');
        if (!id) return;

        currentSubmissionId = id;
        const cached = submissions.find(function (item) {
            return String(item.id) === String(id);
        }) || {};

        populateModalFields(cached);
        document.getElementById('abyipPreviewMount').innerHTML = '<p class="preview-loading">Loading document preview...</p>';
        hideRejectForm();
        document.getElementById('viewModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        apiFetch((config.showUrl || '/api/barangay-abyip/__ID__').replace('__ID__', id))
            .then(function (payload) {
                const item = payload.data || {};
                populateModalFields(item);
                renderSubmissionPreview(item);
            })
            .catch(function (error) {
                document.getElementById('abyipPreviewMount').innerHTML =
                    '<p class="preview-empty">' + escapeHtml(error.message || 'Unable to load submission.') + '</p>';
                showToast(error.message || 'Unable to load submission details.', 'error');
            });
    };

    window.showRejectForm = function () {
        document.getElementById('modalActions').style.display = 'none';
        document.getElementById('rejectForm').style.display = 'block';
    };

    window.hideRejectForm = function () {
        document.getElementById('rejectForm').style.display = 'none';
        document.querySelectorAll('input[name="rejectReason"]').forEach(function (cb) {
            cb.checked = false;
        });
        document.getElementById('otherReason').value = '';
        document.getElementById('otherReasonGroup').style.display = 'none';
        document.getElementById('rejectReasonError').textContent = '';
        document.getElementById('otherReasonError').textContent = '';
        const item = submissions.find(function (entry) {
            return String(entry.id) === String(currentSubmissionId);
        });
        document.getElementById('modalActions').style.display =
            item && String(item.status).toLowerCase() === 'pending' ? 'flex' : 'none';
    };

    window.handleRejectReasonChange = function () {
        const otherCheckbox = document.getElementById('otherReasonCheckbox');
        document.getElementById('otherReasonGroup').style.display = otherCheckbox?.checked ? 'block' : 'none';
        if (!otherCheckbox?.checked) {
            document.getElementById('otherReason').value = '';
        }
        document.getElementById('rejectReasonError').textContent = '';
    };

    window.submitApproval = async function () {
        if (!currentSubmissionId) return;

        const approveBtn = document.querySelector('#modalActions .approve-btn');
        if (approveBtn) approveBtn.disabled = true;

        try {
            await apiFetch((config.approveUrl || '/api/barangay-abyip/__ID__/approve').replace('__ID__', currentSubmissionId), {
                method: 'POST',
                body: JSON.stringify({}),
            });
            showToast('Submission successfully approved!', 'success');
            closeViewModal();
            await loadSubmissions();
        } catch (error) {
            showToast(error.message || 'Approval failed.', 'error');
        } finally {
            if (approveBtn) approveBtn.disabled = false;
        }
    };

    window.submitRejection = async function () {
        if (!currentSubmissionId) return;

        const checkboxes = document.querySelectorAll('input[name="rejectReason"]:checked');
        const otherCheckbox = document.getElementById('otherReasonCheckbox');
        const otherReason = document.getElementById('otherReason').value.trim();
        const reasonError = document.getElementById('rejectReasonError');
        const otherError = document.getElementById('otherReasonError');

        reasonError.textContent = '';
        otherError.textContent = '';

        if (!checkboxes.length) {
            reasonError.textContent = 'Please select at least one rejection reason';
            return;
        }

        const reasons = Array.from(checkboxes).map(function (cb) { return cb.value; });
        if (otherCheckbox.checked && !otherReason) {
            otherError.textContent = 'Please specify the reason';
            return;
        }
        if (otherCheckbox.checked) {
            reasons.push(otherReason);
        }

        const rejectBtn = document.querySelector('.reject-submit-btn');
        if (rejectBtn) rejectBtn.disabled = true;

        try {
            await apiFetch((config.rejectUrl || '/api/barangay-abyip/__ID__/reject').replace('__ID__', currentSubmissionId), {
                method: 'POST',
                body: JSON.stringify({ reason: reasons.join('; ') }),
            });
            showToast('Submission successfully rejected!', 'error');
            closeViewModal();
            await loadSubmissions();
        } catch (error) {
            showToast(error.message || 'Rejection failed.', 'error');
        } finally {
            if (rejectBtn) rejectBtn.disabled = false;
        }
    };

    window.closeViewModal = function () {
        document.getElementById('viewModal').classList.remove('active', 'fullscreen');
        setResizeButtonState(false);
        document.body.style.overflow = '';
        hideRejectForm();
        currentSubmissionId = null;
        document.getElementById('abyipPreviewMount').innerHTML = '';
    };

    window.toggleFullscreen = function () {
        const modal = document.getElementById('viewModal');
        modal.classList.toggle('fullscreen');
        setResizeButtonState(modal.classList.contains('fullscreen'));
    };

    window.showToast = function (message, type) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.querySelector('.toast-message').textContent = message;
        toast.className = 'toast show ' + (type || 'success');
        setTimeout(function () { toast.classList.remove('show'); }, 3000);
    };

    document.addEventListener('DOMContentLoaded', function () {
        loadSubmissions().catch(function (error) {
            showToast(error.message || 'Unable to load ABYIP submissions.', 'error');
        });
    });
})();
