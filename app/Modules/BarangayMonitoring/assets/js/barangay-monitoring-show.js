(function () {
    'use strict';

    const config = window.barangayMonitoringShowConfig || {};
    let currentSubmissionId = null;
    let approveSubmissionId = null;
    let rejectSubmissionId = null;
    let revokeSubmissionId = null;
    const submissions = Array.isArray(config.abyipReports) ? config.abyipReports : [];

    function csrfToken() {
        return config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function filterTableRows(tableId, matchers) {
        const table = document.getElementById(tableId);
        if (!table) return;

        table.querySelectorAll('tbody tr').forEach(function (row) {
            if (row.classList.contains('bm-empty-row-tr') || row.querySelector('.bm-empty-row')) return;
            const visible = matchers.every(function (matcher) {
                return matcher(row);
            });
            row.hidden = !visible;
        });
    }

    function initAbyipFilters() {
        const search = document.getElementById('abyipSearchInput');
        const year = document.getElementById('abyipFilterYear');
        const status = document.getElementById('abyipFilterStatus');

        function apply() {
            const term = (search?.value || '').toLowerCase();
            const yearVal = year?.value || 'all';
            const statusVal = status?.value || 'all';

            filterTableRows('abyipTable', [
                function (row) {
                    if (!term) return true;
                    return row.textContent.toLowerCase().includes(term);
                },
                function (row) {
                    return yearVal === 'all' || row.dataset.year === yearVal;
                },
                function (row) {
                    return statusVal === 'all' || row.dataset.status === statusVal;
                },
            ]);
        }

        search?.addEventListener('input', apply);
        year?.addEventListener('change', apply);
        status?.addEventListener('change', apply);
    }

    async function apiFetch(url, options) {
        const response = await fetch(url, {
            method: (options && options.method) || 'GET',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: options && options.body ? JSON.stringify(options.body) : undefined,
        });

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

    function formatDateSubmitted(raw) {
        if (!raw) return 'N/A';
        const date = new Date(raw);
        if (Number.isNaN(date.getTime())) return raw;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatSubmittedTime(raw) {
        if (!raw) return 'N/A';
        const date = new Date(raw);
        if (Number.isNaN(date.getTime())) return 'N/A';
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
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
        const barangayEl = document.getElementById('modalBarangay');
        if (barangayEl) {
            barangayEl.textContent = item.barangay
                || config.barangayName
                || document.getElementById('bmShowApp')?.dataset.barangayName
                || 'N/A';
        }
        document.getElementById('modalFiscalYear').textContent = item.fiscal_year || item.calendar_year
            ? String(item.fiscal_year || item.calendar_year)
            : 'N/A';
        document.getElementById('modalDateSubmitted').textContent = item.date_submitted
            ? formatDateSubmitted(item.date_submitted)
            : (item.date_submitted_raw || 'N/A');
        document.getElementById('modalSubmittedBy').textContent = item.submitted_by || 'N/A';
        const roleEl = document.getElementById('modalSubmittedRole');
        if (roleEl) {
            roleEl.textContent = item.submitted_by_role || 'N/A';
        }
        document.getElementById('modalTitle').textContent = item.title || item.name || 'N/A';
        document.getElementById('modalSubmittedTime').textContent = item.submitted_time
            || formatSubmittedTime(item.date_submitted);

        const statusBadge = document.getElementById('modalStatus');
        statusBadge.textContent = statusLabel(item.status);
        statusBadge.className = 'status-badge ' + statusClass(item.status);

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

        const fileUrl = item.file_url || item.file;
        if ((item.has_pdf || fileUrl) && fileUrl) {
            preview.innerHTML =
                '<div class="abyip-pdf-clip"><iframe src="' + escapeHtml(pdfPreviewSrc(fileUrl)) + '" class="abyip-pdf-frame" title="ABYIP PDF"></iframe></div>';
            return;
        }

        preview.innerHTML = '<p class="preview-empty">No preview available for this submission.</p>';
    }

    function findCachedSubmission(id) {
        return submissions.find(function (item) {
            return String(item.id) === String(id);
        }) || {};
    }

    function resetActionDropdown(menu) {
        const dropdown = menu?.querySelector('.bm-actions-dropdown');
        if (!dropdown) return;

        dropdown.classList.remove('is-floating', 'bm-actions-dropdown-up');
        dropdown.style.position = '';
        dropdown.style.top = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.bottom = '';
        dropdown.style.zIndex = '';
    }

    function positionActionDropdown(menu) {
        const toggle = menu.querySelector('.bm-actions-toggle');
        const dropdown = menu.querySelector('.bm-actions-dropdown');
        if (!toggle || !dropdown) return;

        dropdown.hidden = false;
        dropdown.classList.add('is-floating');
        dropdown.style.position = 'fixed';
        dropdown.style.zIndex = '1400';

        const rect = toggle.getBoundingClientRect();
        const gap = 8;
        const dropdownHeight = dropdown.offsetHeight || 160;
        const dropdownWidth = dropdown.offsetWidth || 168;

        let top = rect.top - dropdownHeight - gap;
        dropdown.classList.add('bm-actions-dropdown-up');

        if (top < 8) {
            top = rect.bottom + gap;
            dropdown.classList.remove('bm-actions-dropdown-up');
        }

        let right = window.innerWidth - rect.right;
        right = Math.max(8, Math.min(right, window.innerWidth - dropdownWidth - 8));

        dropdown.style.top = `${Math.max(8, top)}px`;
        dropdown.style.right = `${right}px`;
        dropdown.style.left = 'auto';
        dropdown.style.bottom = 'auto';
    }

    function closeAllActionMenus(exceptMenu) {
        document.querySelectorAll('.bm-actions-menu').forEach(function (menu) {
            if (exceptMenu && menu === exceptMenu) return;
            const dropdown = menu.querySelector('.bm-actions-dropdown');
            const toggle = menu.querySelector('.bm-actions-toggle');
            if (dropdown) dropdown.hidden = true;
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
            resetActionDropdown(menu);
        });
    }

    function resetRejectForm() {
        const wrongPdf = document.getElementById('rejectReasonWrongPdf');
        const other = document.getElementById('rejectReasonOther');
        const reasonInput = document.getElementById('abyipRejectReason');
        const confirmInput = document.getElementById('abyipRejectConfirm');
        const wrap = document.getElementById('rejectReasonFieldWrap');
        const error = document.getElementById('rejectReasonError');
        const confirmError = document.getElementById('rejectConfirmError');

        if (wrongPdf) wrongPdf.checked = false;
        if (other) other.checked = false;
        if (reasonInput) reasonInput.value = '';
        if (confirmInput) confirmInput.value = '';
        if (wrap) wrap.style.display = 'none';
        if (error) error.textContent = '';
        if (confirmError) confirmError.textContent = '';
        syncRejectSubmitState();
    }

    function syncRejectSubmitState() {
        const confirmInput = document.getElementById('abyipRejectConfirm');
        const rejectBtn = document.querySelector('.reject-submit-btn');
        if (!rejectBtn) return;
        rejectBtn.disabled = (confirmInput?.value || '').trim() !== 'Confirm';
    }

    function syncRevokeSubmitState() {
        const confirmInput = document.getElementById('abyipRevokeConfirm');
        const revokeBtn = document.querySelector('.revoke-submit-btn');
        if (!revokeBtn) return;
        revokeBtn.disabled = (confirmInput?.value || '').trim() !== 'Confirm';
    }

    function resetRevokeForm() {
        document.getElementById('abyipRevokeConfirm').value = '';
        document.getElementById('abyipRevokeReason').value = '';
        document.getElementById('revokeConfirmError').textContent = '';
        document.getElementById('revokeReasonError').textContent = '';
        const accidental = document.getElementById('revokeReasonAccidental');
        const other = document.getElementById('revokeReasonOther');
        if (accidental) accidental.checked = false;
        if (other) other.checked = false;
        const wrap = document.getElementById('revokeReasonFieldWrap');
        if (wrap) wrap.style.display = 'none';
        syncRevokeSubmitState();
    }

    function openViewModal(id) {
        if (!id) return;

        currentSubmissionId = id;
        closeAllActionMenus();
        const cached = findCachedSubmission(id);
        populateModalFields(cached);
        document.getElementById('abyipPreviewMount').innerHTML = '<p class="preview-loading">Loading document preview...</p>';
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
    }

    function openApproveModal(id) {
        approveSubmissionId = id;
        closeAllActionMenus();
        document.getElementById('approveModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    window.closeApproveModal = function () {
        document.getElementById('approveModal').classList.remove('active');
        approveSubmissionId = null;
        if (!document.getElementById('viewModal').classList.contains('active')
            && !document.getElementById('rejectModal').classList.contains('active')
            && !document.getElementById('revokeModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    };

    function openRejectModal(id) {
        rejectSubmissionId = id;
        resetRejectForm();
        closeAllActionMenus();
        document.getElementById('rejectModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    window.closeRejectModal = function () {
        document.getElementById('rejectModal').classList.remove('active');
        rejectSubmissionId = null;
        resetRejectForm();
        if (!document.getElementById('viewModal').classList.contains('active')
            && !document.getElementById('approveModal').classList.contains('active')
            && !document.getElementById('revokeModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    };

    function openRevokeModal(id) {
        if (!id) return;

        revokeSubmissionId = id;
        resetRevokeForm();
        closeAllActionMenus();
        document.getElementById('revokeModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    window.closeRevokeModal = function () {
        document.getElementById('revokeModal').classList.remove('active');
        revokeSubmissionId = null;
        resetRevokeForm();
        if (!document.getElementById('viewModal').classList.contains('active')
            && !document.getElementById('approveModal').classList.contains('active')
            && !document.getElementById('rejectModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    };

    function bindRejectReasonControls() {
        const wrongPdf = document.getElementById('rejectReasonWrongPdf');
        const other = document.getElementById('rejectReasonOther');
        const wrap = document.getElementById('rejectReasonFieldWrap');
        const reasonInput = document.getElementById('abyipRejectReason');
        const confirmInput = document.getElementById('abyipRejectConfirm');

        function syncRejectReasonField() {
            const showField = Boolean(other?.checked);
            if (wrap) wrap.style.display = showField ? 'block' : 'none';
            if (!showField && reasonInput) reasonInput.value = '';
        }

        wrongPdf?.addEventListener('change', function () {
            if (wrongPdf.checked && other) other.checked = false;
            syncRejectReasonField();
        });

        other?.addEventListener('change', function () {
            if (other.checked && wrongPdf) wrongPdf.checked = false;
            syncRejectReasonField();
        });

        confirmInput?.addEventListener('input', syncRejectSubmitState);
    }

    function bindRevokeReasonControls() {
        const accidental = document.getElementById('revokeReasonAccidental');
        const other = document.getElementById('revokeReasonOther');
        const wrap = document.getElementById('revokeReasonFieldWrap');
        const reasonInput = document.getElementById('abyipRevokeReason');
        const confirmInput = document.getElementById('abyipRevokeConfirm');

        function syncRevokeReasonField() {
            const showField = Boolean(other?.checked);
            if (wrap) wrap.style.display = showField ? 'block' : 'none';
            if (!showField && reasonInput) reasonInput.value = '';
        }

        accidental?.addEventListener('change', syncRevokeReasonField);
        other?.addEventListener('change', syncRevokeReasonField);
        confirmInput?.addEventListener('input', syncRevokeSubmitState);
    }

    function showActionLoading(message, subtext) {
        if (typeof window.showLoading === 'function') {
            window.showLoading(message, subtext);
        }
    }

    function hideActionLoading() {
        if (typeof window.hideLoading === 'function') {
            window.hideLoading();
        }
    }

    window.confirmApproval = async function () {
        if (!approveSubmissionId) return;

        const approveBtn = document.querySelector('.approve-submit-btn');
        if (approveBtn) approveBtn.disabled = true;

        let succeeded = false;
        try {
            showActionLoading('Approving Submission', 'Please wait while we approve this ABYIP report...');
            await apiFetch((config.approveUrl || '/api/barangay-abyip/__ID__/approve').replace('__ID__', approveSubmissionId), {
                method: 'POST',
                body: {},
            });
            succeeded = true;
            showToast('Submission successfully approved!', 'success');
            closeApproveModal();
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Approval failed.', 'error');
        } finally {
            if (!succeeded) {
                hideActionLoading();
                if (approveBtn) approveBtn.disabled = false;
            }
        }
    };

    window.submitRevocation = async function () {
        if (!revokeSubmissionId) return;

        const confirmInput = document.getElementById('abyipRevokeConfirm');
        const confirmError = document.getElementById('revokeConfirmError');
        const reasonError = document.getElementById('revokeReasonError');
        const accidental = document.getElementById('revokeReasonAccidental');
        const other = document.getElementById('revokeReasonOther');
        const reasonInput = document.getElementById('abyipRevokeReason');

        confirmError.textContent = '';
        reasonError.textContent = '';

        if ((confirmInput?.value || '').trim() !== 'Confirm') {
            confirmError.textContent = 'Type Confirm to continue.';
            return;
        }

        if (!accidental?.checked && !other?.checked) {
            reasonError.textContent = 'Select a revoke reason.';
            return;
        }

        let reason = '';
        if (accidental?.checked) {
            reason = 'Accidentally approved';
        }
        if (other?.checked) {
            const customReason = (reasonInput?.value || '').trim();
            if (!customReason) {
                reasonError.textContent = 'Please provide a revoke reason.';
                return;
            }
            reason = reason ? reason + ': ' + customReason : customReason;
        }

        reason = reason.slice(0, 100);

        const revokeBtn = document.querySelector('.revoke-submit-btn');
        if (revokeBtn) revokeBtn.disabled = true;

        let succeeded = false;
        try {
            showActionLoading('Revoking Approval', 'Please wait while we revoke this ABYIP approval...');
            await apiFetch((config.revokeUrl || '/api/barangay-abyip/__ID__/revoke').replace('__ID__', revokeSubmissionId), {
                method: 'POST',
                body: { reason: reason },
            });
            succeeded = true;
            showToast('ABYIP approval revoked. Status is now Pending.', 'success');
            closeRevokeModal();
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Revocation failed.', 'error');
        } finally {
            if (!succeeded) {
                hideActionLoading();
                if (revokeBtn) revokeBtn.disabled = false;
            }
        }
    };

    window.submitRejection = async function () {
        if (!rejectSubmissionId) return;

        const wrongPdf = document.getElementById('rejectReasonWrongPdf');
        const other = document.getElementById('rejectReasonOther');
        const reasonInput = document.getElementById('abyipRejectReason');
        const confirmInput = document.getElementById('abyipRejectConfirm');
        const reasonError = document.getElementById('rejectReasonError');
        const confirmError = document.getElementById('rejectConfirmError');
        reasonError.textContent = '';
        confirmError.textContent = '';

        if ((confirmInput?.value || '').trim() !== 'Confirm') {
            confirmError.textContent = 'Type Confirm to continue.';
            return;
        }

        if (!wrongPdf?.checked && !other?.checked) {
            reasonError.textContent = 'Select a rejection reason.';
            return;
        }

        let reason = '';
        if (wrongPdf?.checked) {
            reason = 'Wrong PDF file';
        }
        if (other?.checked) {
            const customReason = (reasonInput?.value || '').trim();
            if (!customReason) {
                reasonError.textContent = 'Please provide a rejection reason.';
                return;
            }
            reason = customReason.slice(0, 100);
        }

        const rejectBtn = document.querySelector('.reject-submit-btn');
        if (rejectBtn) rejectBtn.disabled = true;

        let succeeded = false;
        try {
            showActionLoading('Rejecting Submission', 'Please wait while we reject this ABYIP report...');
            await apiFetch((config.rejectUrl || '/api/barangay-abyip/__ID__/reject').replace('__ID__', rejectSubmissionId), {
                method: 'POST',
                body: { reason: reason },
            });
            succeeded = true;
            showToast('Submission successfully rejected!', 'error');
            closeRejectModal();
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Rejection failed.', 'error');
        } finally {
            if (!succeeded) {
                hideActionLoading();
                if (rejectBtn) rejectBtn.disabled = false;
            }
        }
    };

    window.closeViewModal = function () {
        document.getElementById('viewModal').classList.remove('active', 'fullscreen');
        setResizeButtonState(false);
        if (!document.getElementById('approveModal').classList.contains('active')
            && !document.getElementById('rejectModal').classList.contains('active')
            && !document.getElementById('revokeModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
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
        const icon = toast.querySelector('.toast-icon');
        const normalized = type || 'success';
        toast.querySelector('.toast-message').textContent = message;
        toast.className = 'toast bm-page-toast show ' + normalized;
        if (icon) {
            icon.className = 'toast-icon fas ' + (normalized === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle');
        }
        setTimeout(function () { toast.classList.remove('show'); }, 3200);
    };

    function initAbyipViewButtons() {
        document.querySelectorAll('[data-abyip-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openViewModal(btn.getAttribute('data-abyip-view'));
            });
        });

        document.querySelectorAll('[data-abyip-approve]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openApproveModal(btn.getAttribute('data-abyip-approve'));
            });
        });

        document.querySelectorAll('[data-abyip-reject]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openRejectModal(btn.getAttribute('data-abyip-reject'));
            });
        });

        document.querySelectorAll('[data-abyip-revoke]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openRevokeModal(btn.getAttribute('data-abyip-revoke'));
            });
        });
    }

    function initActionMenus() {
        document.querySelectorAll('.bm-actions-menu').forEach(function (menu) {
            const toggle = menu.querySelector('.bm-actions-toggle');
            const dropdown = menu.querySelector('.bm-actions-dropdown');

            toggle?.addEventListener('click', function (event) {
                event.stopPropagation();
                const isOpen = menu.classList.contains('is-open');
                closeAllActionMenus();
                if (!isOpen) {
                    menu.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                    positionActionDropdown(menu);
                }
            });
        });

        window.addEventListener('resize', function () {
            const openMenu = document.querySelector('.bm-actions-menu.is-open');
            if (openMenu) {
                positionActionDropdown(openMenu);
            }
        });

        document.addEventListener('click', function () {
            closeAllActionMenus();
        });

        document.querySelectorAll('.bm-actions-dropdown').forEach(function (dropdown) {
            dropdown.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAbyipFilters();
        initAbyipViewButtons();
        initActionMenus();
        bindRejectReasonControls();
        bindRevokeReasonControls();
    });
})();
