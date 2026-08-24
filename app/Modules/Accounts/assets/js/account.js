// =============================================================
// account.js — Single JS file for the Accounts module
// Covers: manage table, add/edit SK Fed, add/edit SK Officials,
//         view modal, pagination, batch upload, bulk actions
// =============================================================

// ── Alpine.js accounts page state (selection + bulk bar only) ───
window.accountsPage = function () {
    return {
        selectedRows: [],
        selectAll: false,

        get selectedCount() {
            return this.selectedRows.length;
        },

        init() {
            this.syncSelectAllState();
        },

        getVisibleRowIds() {
            const tbody = document.getElementById('accountsTableBody');
            if (!tbody) return [];

            return Array.from(tbody.querySelectorAll('tr[data-account-id]'))
                .filter((row) => row.style.display !== 'none')
                .map((row) => parseInt(row.dataset.accountId, 10))
                .filter((id) => !Number.isNaN(id));
        },

        toggleRow(id, checked) {
            if (checked) {
                if (!this.selectedRows.includes(id)) {
                    this.selectedRows.push(id);
                }
            } else {
                this.selectedRows = this.selectedRows.filter((rowId) => rowId !== id);
            }
            this.syncSelectAllState();
        },

        toggleSelectAll(checked) {
            const visibleIds = this.getVisibleRowIds();
            if (checked) {
                visibleIds.forEach((id) => {
                    if (!this.selectedRows.includes(id)) {
                        this.selectedRows.push(id);
                    }
                });
            } else {
                this.selectedRows = this.selectedRows.filter((id) => !visibleIds.includes(id));
            }
            this.selectAll = checked;
        },

        syncSelectAllState() {
            const visibleIds = this.getVisibleRowIds();
            this.selectAll = visibleIds.length > 0 && visibleIds.every((id) => this.selectedRows.includes(id));
        },

        openBulkDelete() {
            if (this.selectedRows.length === 0) return;
            window.AccountsDeleteModal.openBulk([...this.selectedRows]);
        },

        clearSelection() {
            this.selectedRows = [];
            this.selectAll = false;
        },
    };
};

// ── Delete modal (vanilla JS — reliable show/hide) ─────────────
window.AccountsDeleteModal = (function () {
    let mode = 'single';
    let targetId = null;
    let bulkIds = [];

    function els() {
        return {
            modal: document.getElementById('deleteAccountModal'),
            panel: document.getElementById('deleteModalPanel'),
            loading: document.getElementById('deleteModalLoading'),
            title: document.getElementById('deleteModalTitle'),
            message: document.getElementById('deleteModalMessage'),
            input: document.getElementById('deleteConfirmationInput'),
            hintError: document.getElementById('deleteConfirmHintError'),
            hintSuccess: document.getElementById('deleteConfirmHintSuccess'),
            confirmBtn: document.getElementById('deleteModalConfirmBtn'),
            cancelBtn: document.getElementById('deleteModalCancelBtn'),
        };
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    function setModalLoading(isLoading) {
        const { panel, loading, confirmBtn, cancelBtn, input } = els();
        if (panel) panel.hidden = isLoading;
        if (loading) loading.hidden = !isLoading;
        if (confirmBtn) confirmBtn.disabled = isLoading || confirmBtn.classList.contains('is-disabled');
        if (cancelBtn) cancelBtn.disabled = isLoading;
        if (input) input.disabled = isLoading;
    }

    function updateConfirmState() {
        const { input, hintError, hintSuccess, confirmBtn } = els();
        if (!input || !confirmBtn) return;

        const value = input.value;
        const matched = value === 'Confirm';

        if (hintError) hintError.style.display = value.length > 0 && !matched ? 'block' : 'none';
        if (hintSuccess) hintSuccess.style.display = matched ? 'block' : 'none';

        confirmBtn.disabled = !matched;
        confirmBtn.classList.toggle('is-enabled', matched);
        confirmBtn.classList.toggle('is-disabled', !matched);
    }

    function resetForm() {
        const { input, hintError, hintSuccess, confirmBtn } = els();
        setModalLoading(false);
        if (input) input.value = '';
        if (hintError) hintError.style.display = 'none';
        if (hintSuccess) hintSuccess.style.display = 'none';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.classList.remove('is-enabled');
            confirmBtn.classList.add('is-disabled');
        }
    }

    function openModal() {
        const { modal, input } = els();
        if (!modal) return;
        resetForm();
        modal.setAttribute('aria-hidden', 'false');
        toggleModal('deleteAccountModal', true);
        window.setTimeout(() => input?.focus(), 50);
    }

    function closeModal() {
        const { modal } = els();
        if (modal) modal.setAttribute('aria-hidden', 'true');
        toggleModal('deleteAccountModal', false);
        mode = 'single';
        targetId = null;
        bulkIds = [];
        resetForm();
    }

    async function confirmDelete() {
        const { input } = els();
        if (!input || input.value !== 'Confirm') return;

        const token = csrfToken();
        if (!token) {
            alert('Session expired. Please refresh the page and try again.');
            return;
        }

        setModalLoading(true);

        try {
            if (mode === 'bulk') {
                const response = await fetch('/accounts/bulk-deactivate', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ account_ids: bulkIds, _token: token }),
                });
                const data = await response.json().catch(() => ({}));
                setModalLoading(false);

                if (!response.ok || !data.success) {
                    alert(data.message || 'Failed to delete selected accounts.');
                    return;
                }

                bulkIds.forEach((id) => {
                    const row = document.querySelector(`tr[data-account-id="${id}"]`);
                    if (row) row.remove();
                });

                const root = document.getElementById('mainContent');
                if (root?._x_dataStack?.[0]?.clearSelection) {
                    root._x_dataStack[0].clearSelection();
                }

                if (typeof window.refreshAccountsPagination === 'function') {
                    window.refreshAccountsPagination();
                }

                closeModal();
                showAccountToast(data.message || 'Accounts deleted successfully!', 'delete');
                return;
            }

            const response = await fetch(`/accounts/${targetId}/deactivate`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ _token: token }),
            });
            const data = await response.json().catch(() => ({}));
            setModalLoading(false);

            if (!response.ok || !data.success) {
                const message = data.message
                    || (response.status === 419 ? 'Session expired. Please refresh the page and try again.' : 'Failed to delete account.');
                alert(message);
                return;
            }

            const row = document.querySelector(`tr[data-account-id="${targetId}"]`);
            if (row) row.remove();

            const root = document.getElementById('mainContent');
            const alpine = root?._x_dataStack?.[0];
            if (alpine && Array.isArray(alpine.selectedRows)) {
                alpine.selectedRows = alpine.selectedRows.filter((id) => id !== targetId);
                alpine.syncSelectAllState?.();
            }

            if (typeof window.refreshAccountsPagination === 'function') {
                window.refreshAccountsPagination();
            }

            closeModal();
            showAccountToast('Account deleted successfully!', 'delete');
        } catch (error) {
            setModalLoading(false);
            alert('An unexpected error occurred. Please try again.');
        }
    }

    function wireEvents() {
        const { input, confirmBtn, cancelBtn, modal } = els();
        if (!modal || modal.dataset.wired === '1') return;

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        modal.dataset.wired = '1';
        modal.setAttribute('aria-hidden', 'true');

        input?.addEventListener('input', updateConfirmState);
        confirmBtn?.addEventListener('click', confirmDelete);
        cancelBtn?.addEventListener('click', closeModal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display !== 'none' && modal.style.display !== '') {
                closeModal();
            }
        });
    }

    return {
        wireEvents,
        openSingle(id) {
            mode = 'single';
            targetId = id;
            bulkIds = [];
            const { title, message } = els();
            if (title) title.textContent = 'Delete Account';
            if (message) message.textContent = 'Are you sure you want to permanently delete this account?';
            openModal();
        },
        openBulk(ids) {
            mode = 'bulk';
            targetId = null;
            bulkIds = [...ids];
            const { title, message } = els();
            if (title) title.textContent = 'Delete Selected Accounts';
            if (message) message.textContent = 'Are you sure you want to permanently delete the selected account(s)?';
            openModal();
        },
        close: closeModal,
    };
})();

// ── Shared helpers ────────────────────────────────────────────
function getXlsxLib() {
    return window.XLSX || null;
}
function lockBodyScroll() {
    if (document.body.dataset.scrollLocked === '1') return;

    const scrollY = window.scrollY;
    document.body.dataset.scrollLocked = '1';
    document.body.dataset.scrollY = String(scrollY);
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
}

function unlockBodyScroll() {
    if (document.body.dataset.scrollLocked !== '1') {
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('position');
        document.body.style.removeProperty('top');
        document.body.style.removeProperty('width');
        return;
    }

    const scrollY = parseInt(document.body.dataset.scrollY || '0', 10);
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('position');
    document.body.style.removeProperty('top');
    document.body.style.removeProperty('width');
    delete document.body.dataset.scrollLocked;
    delete document.body.dataset.scrollY;
    window.scrollTo(0, scrollY);
}

function isAnyModalOpen() {
    return Array.from(document.querySelectorAll('.modal-overlay')).some((modal) => {
        const display = window.getComputedStyle(modal).display;
        return display !== 'none';
    });
}

function hideAllAccountModals() {
    document.querySelectorAll('.modal-overlay').forEach((modal) => {
        modal.style.display = 'none';
    });
}

function cleanupAccountUiState() {
    hideLoadingOverlay();
    hideAllAccountModals();
    if (!isAnyModalOpen()) {
        unlockBodyScroll();
    }
}

function toggleModal(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.style.display = show ? 'flex' : 'none';

    if (show) {
        lockBodyScroll();
        return;
    }

    if (!isAnyModalOpen()) {
        unlockBodyScroll();
    }
}

// ── Top toast notification ─────────────────────────────────────
let _toastTimer = null;
function showAccountToast(msg, type) {
    // type: 'success' | 'edit' | 'delete' | 'error'
    const idMap = { success: 'accountToast', edit: 'accountToastEdit', delete: 'accountToastDelete', error: 'accountToastError' };
    const msgMap = { success: 'accountToastMsg', edit: 'accountToastEditMsg', delete: 'accountToastDeleteMsg', error: 'accountToastErrorMsg' };
    const toastId = idMap[type] || 'accountToast';
    const msgId = msgMap[type] || 'accountToastMsg';

    // Hide all toasts first
    ['accountToast', 'accountToastEdit', 'accountToastDelete', 'accountToastError'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    });

    const toast = document.getElementById(toastId);
    const msgEl = document.getElementById(msgId);
    if (!toast) return;
    if (msgEl) msgEl.textContent = msg;
    toast.classList.add('show');
    if (_toastTimer) clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
}

function displayFormValidationErrors(form, errors) {
    if (!form || !errors) return '';
    let firstMessage = '';
    Object.keys(errors).forEach((field) => {
        const message = Array.isArray(errors[field]) ? errors[field][0] : String(errors[field]);
        if (!firstMessage) firstMessage = message;
        const input = form.querySelector(`[name="${field}"]`);
        if (input) _showErr(input, message);
    });
    return firstMessage;
}

function getCurrentAccountType() {
    return window.location.pathname.includes('/accounts/officials') ? 'sk_officials' : 'sk_federation';
}

function calculateAge(dateOfBirthValue) {
    return window.AccountDateFieldUtils
        ? window.AccountDateFieldUtils.calculateAge(dateOfBirthValue)
        : '';
}

function attachDobAgeAutoFill(form, dobName, ageName) {
    if (!form) return;
    const dob = form.querySelector(`[name="${dobName}"]`);
    const age = form.querySelector(`[name="${ageName}"]`);
    if (!dob || !age) return;
    const update = () => {
        if (isSkOfficialsForm(form)) {
            return;
        }
        age.value = calculateAge(dob.value);
    };
    dob.addEventListener('change', update);
    dob.addEventListener('input', update);
    if (!isSkOfficialsForm(form)) {
        update();
    }
}

function setFormFieldValue(form, name, value) {
    const field = form.querySelector(`[name="${name}"]`);
    if (field) field.value = value;
}

function showLoadingOverlay(message = 'Processing...', subtext = 'Please wait') {
    if (typeof window.showLoading === 'function') {
        window.showLoading(message, subtext);
    }
}

function hideLoadingOverlay() {
    if (typeof window.hideLoading === 'function') {
        window.hideLoading();
    }

    if (!isAnyModalOpen()) {
        unlockBodyScroll();
    }
}

function showFieldError(form, fieldName, message) {
    const field = form.querySelector(`[name="${fieldName}"]`);
    if (!field) return;
    field.classList.add('error');
    const err = field.parentElement.querySelector('.form-error');
    if (err) { err.textContent = message; err.classList.add('show'); }
}

function clearAllErrors(form) {
    form.querySelectorAll('.form-input-modern, .form-input-light').forEach(f => f.classList.remove('error', 'is-invalid', 'is-valid'));
    form.querySelectorAll('.form-error, .form-error-light').forEach(e => { e.textContent = ''; e.classList.remove('show'); });
    form.querySelectorAll('.validation-error').forEach(e => e.remove());
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Inline validation helpers (light-theme forms) ─────────────
const ACCOUNT_TERM_YEARS = 4;
const SK_OFFICIAL_TERM_YEARS = 3;
const SK_OFFICIAL_TERM_MAX_YEARS = 4;
const SK_OFFICIAL_FIRST_TERM_START = '2023-11-30';
const SK_OFFICIAL_TERM_START_MESSAGE = 'Term start must be November 30 of an SK term year (2023, 2026, 2029, …) under RA 11935. December 1 is not the legal commencement.';
const SK_OFFICIAL_TERM_END_MESSAGE = 'Term end must be between 3 and 4 years after term start.';
const SK_OFFICIAL_NAME_MIN = 3;
const SK_OFFICIAL_NAME_MAX = 50;
const SK_OFFICIAL_FIRST_NAME_REGEX = /^(?!\s)[A-Z.\-]+(?: [A-Z.\-]+)?$/;
const SK_OFFICIAL_SUFFIX_OTHER_MAX = 4;
const SK_OFFICIAL_AGE_MIN = 15;
const SK_OFFICIAL_AGE_MAX = 30;
const SK_OFFICIAL_GMAIL_REGEX = /^[a-z0-9._%+-]{6,30}@gmail\.com$/i;
const SK_OFFICIAL_MAX_MSG = 'Maximum of 50 characters reached';
const BATCH_EMAIL_MAX = 254;
const BATCH_SK_FED_NAME_MAX = 35;
const BATCH_LOCATION_MAX = 100;
const BATCH_POSITION_MAX = 100;
const BATCH_CONTACT_NUMBER_LENGTH = 11;
const MODAL_TITLE_MAXIMIZE = 'Maximize';
const MODAL_TITLE_RESTORE = 'Restore Down';

function formatLocalDate(date) {
    return window.AccountDateFieldUtils
        ? window.AccountDateFieldUtils.formatLocalDate(date)
        : '';
}

function parseLocalDateString(dateStr) {
    return window.AccountDateFieldUtils
        ? window.AccountDateFieldUtils.parseLocalDateString(dateStr)
        : null;
}

function normalizeDateInputValue(value) {
    return window.AccountDateFieldUtils
        ? window.AccountDateFieldUtils.normalizeDateInputValue(value)
        : '';
}

function formatDateForTextInput(value) {
    return window.AccountDateFieldUtils
        ? window.AccountDateFieldUtils.formatDateForTextInput(value)
        : '';
}

function isStrictUsDateFormat(value) {
    return window.AccountDateFieldUtils
        ? window.AccountDateFieldUtils.isStrictUsDateFormat(value)
        : false;
}

function _showErr(input, msg) {
    if (!input) return;
    input.classList.remove('is-valid');
    input.classList.add('is-invalid');
    const span = input.parentNode?.querySelector('.form-error-light');
    if (span) {
        span.textContent = msg;
        return;
    }
    const fallback = document.createElement('span');
    fallback.className = 'validation-error';
    fallback.textContent = msg;
    input.parentNode.appendChild(fallback);
}

function _clearErr(input) {
    if (!input) return;
    input.classList.remove('is-invalid', 'is-valid');
    const span = input.parentNode?.querySelector('.form-error-light');
    if (span) span.textContent = '';
    const ex = input.parentNode?.querySelector('.validation-error:not(.form-error-light)');
    if (ex) ex.remove();
}

function _markValid(input) {
    if (!input) return;
    _clearErr(input);
    input.classList.add('is-valid');
}

function getCurrentYearStartDate() {
    return `${new Date().getFullYear()}-01-01`;
}

function getCurrentYearEndDate() {
    return `${new Date().getFullYear()}-12-31`;
}

function allowedSkOfficialTermStarts(asOf = new Date()) {
    const dates = [];
    const asOfTime = new Date(asOf.getFullYear(), asOf.getMonth(), asOf.getDate()).getTime();
    for (let year = 2023; ; year += SK_OFFICIAL_TERM_YEARS) {
        const start = new Date(year, 10, 30);
        if (start.getTime() > asOfTime) {
            break;
        }
        dates.push(formatLocalDate(start));
    }
    return dates;
}

function currentSkOfficialTermStart(asOf = new Date()) {
    const allowed = allowedSkOfficialTermStarts(asOf);
    return allowed.length ? allowed[allowed.length - 1] : SK_OFFICIAL_FIRST_TERM_START;
}

function snapToSkOfficialTermStart(dateStr) {
    const allowed = allowedSkOfficialTermStarts();
    if (!allowed.length) {
        return SK_OFFICIAL_FIRST_TERM_START;
    }
    if (!dateStr) {
        return allowed[allowed.length - 1];
    }
    let picked = allowed[0];
    for (const date of allowed) {
        if (date <= dateStr) {
            picked = date;
        } else {
            break;
        }
    }
    return picked;
}

function isValidSkOfficialTermStart(dateStr) {
    return allowedSkOfficialTermStarts().includes(dateStr);
}

function skOfficialTermEndForStart(startDateStr) {
    return addYearsToDateString(startDateStr, SK_OFFICIAL_TERM_YEARS);
}

function getTermStartMinDate(form) {
    if (form && isSkOfficialsForm(form)) {
        return SK_OFFICIAL_FIRST_TERM_START;
    }
    return getCurrentYearStartDate();
}

function getTermStartMaxDate(form) {
    if (form && isSkOfficialsForm(form)) {
        return formatLocalDate(new Date());
    }
    return getCurrentYearEndDate();
}

function addYearsToDateString(dateStr, years) {
    const date = parseLocalDateString(dateStr);
    if (!date) {
        return '';
    }
    date.setFullYear(date.getFullYear() + years);
    return formatLocalDate(date);
}

function setModalResizeButton(btn, isMaximized) {
    if (!btn) {
        return;
    }
    const title = isMaximized ? MODAL_TITLE_RESTORE : MODAL_TITLE_MAXIMIZE;
    btn.title = title;
    btn.setAttribute('aria-label', title);
    btn.classList.toggle('is-restore', !!isMaximized);
}

function applyModalResizeState({ overlay, content, btn, isMaximized }) {
    if (!overlay || !content) {
        return;
    }
    if (isMaximized) {
        content.style.cssText = 'width:100vw;max-width:100vw;height:100vh;max-height:100vh;border-radius:0';
        overlay.style.padding = '0';
    } else {
        content.style.cssText = '';
        overlay.style.padding = '';
    }
    setModalResizeButton(btn, isMaximized);
}

function resetModalResizeState({ overlay, content, btn }) {
    if (content) content.style.cssText = '';
    if (overlay) overlay.style.padding = '';
    setModalResizeButton(btn, false);
}


function isSkOfficialsManualForm(form) {
    return form?.id === 'addSkOfficialsForm';
}

function isSkOfficialsForm(form) {
    return form?.id === 'addSkOfficialsForm' || form?.id === 'editSkOfficialsForm';
}

function processSkOfficialFirstNameInput(input) {
    if (!input) return '';
    let value = input.value.replace(/^\s+/, '').replace(/[^a-zA-Z.\-\s]/g, '').toUpperCase();
    value = value.replace(/\s{2,}/g, ' ');
    if (value.length > SK_OFFICIAL_NAME_MAX) {
        value = value.slice(0, SK_OFFICIAL_NAME_MAX);
    }
    input.value = value;
    return value;
}

function processSkOfficialNameInput(input) {
    if (!input) return '';
    let value = input.value.replace(/\s+/g, '').replace(/[^a-zA-Z\-']/g, '').toUpperCase();
    if (value.length > SK_OFFICIAL_NAME_MAX) {
        value = value.slice(0, SK_OFFICIAL_NAME_MAX);
    }
    input.value = value;
    return value;
}

function showSkOfficialMaxMessage(input) {
    if ((input.value || '').length >= SK_OFFICIAL_NAME_MAX) {
        _showErr(input, SK_OFFICIAL_MAX_MSG);
        return true;
    }
    return false;
}

function toggleSkOfficialSuffixOther(form) {
    toggleSuffixOtherField(form);
}

function toggleSuffixOtherField(form) {
    const suffixSelect = form.querySelector('[name="suffix"]');
    const otherGroup = form.querySelector('#official_suffix_other_group')
        || form.querySelector('#edit_sk_officials_suffix_other_group')
        || form.querySelector('#fed_suffix_other_group')
        || form.querySelector('#edit_fed_suffix_other_group');
    const otherInput = form.querySelector('[name="suffix_other"]');
    if (!suffixSelect || !otherGroup) return;
    const show = suffixSelect.value === '__other__';
    otherGroup.style.display = show ? '' : 'none';
    if (otherInput) {
        if (show) {
            otherInput.setAttribute('required', '');
        } else {
            otherInput.removeAttribute('required');
            otherInput.value = '';
            _clearErr(otherInput);
        }
    }
}

const STANDARD_SUFFIX_VALUES = ['', 'NONE', 'None', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];

function setSuffixFieldValue(form, suffixValue) {
    const suffixSelect = form.querySelector('[name="suffix"]');
    const otherInput = form.querySelector('[name="suffix_other"]');
    if (!suffixSelect) return;

    const raw = String(suffixValue ?? '').trim();
    const normalized = raw.toUpperCase() === 'NONE' ? 'NONE' : raw;

    if (!normalized || STANDARD_SUFFIX_VALUES.includes(normalized)) {
        suffixSelect.value = normalized || '';
        if (otherInput) otherInput.value = '';
    } else {
        suffixSelect.value = '__other__';
        if (otherInput) otherInput.value = normalized;
    }

    toggleSuffixOtherField(form);
}

function _validateFedSuffix(form) {
    const suffixSelect = form.querySelector('[name="suffix"]');
    const otherInput = form.querySelector('[name="suffix_other"]');
    if (!suffixSelect) return true;

    if (suffixSelect.value === '__other__') {
        const other = (otherInput?.value || '').trim();
        if (!other) {
            _showErr(otherInput || suffixSelect, 'Other suffix is required');
            return false;
        }
        if (other.length > SK_OFFICIAL_SUFFIX_OTHER_MAX) {
            _showErr(otherInput, `Other suffix must not exceed ${SK_OFFICIAL_SUFFIX_OTHER_MAX} characters`);
            return false;
        }
        if (/\s/.test(other)) {
            _showErr(otherInput, 'Other suffix cannot contain spaces');
            return false;
        }
        _markValid(otherInput);
    }

    _markValid(suffixSelect);
    return true;
}

function _validateSkOfficialFirstName(input) {
    const val = processSkOfficialFirstNameInput(input);
    if (!val) {
        _showErr(input, 'First name is required');
        return false;
    }
    if (val.length < SK_OFFICIAL_NAME_MIN) {
        _showErr(input, 'First name must be at least 3 characters');
        return false;
    }
    if (!SK_OFFICIAL_FIRST_NAME_REGEX.test(val)) {
        _showErr(input, 'Letters only, no leading spaces');
        return false;
    }
    if (showSkOfficialMaxMessage(input)) return false;
    _markValid(input);
    return true;
}

function _validateSkOfficialMiddleName(input) {
    const val = processSkOfficialNameInput(input);
    if (!val) {
        _clearErr(input);
        return true;
    }
    if (val.length < SK_OFFICIAL_NAME_MIN) {
        _showErr(input, 'Middle name must be at least 3 characters');
        return false;
    }
    if (showSkOfficialMaxMessage(input)) return false;
    _markValid(input);
    return true;
}

function _validateSkOfficialLastName(input) {
    const val = processSkOfficialNameInput(input);
    if (!val) {
        _showErr(input, 'Last name is required');
        return false;
    }
    if (val.length < SK_OFFICIAL_NAME_MIN) {
        _showErr(input, 'Last name must be at least 3 characters');
        return false;
    }
    if (showSkOfficialMaxMessage(input)) return false;
    _markValid(input);
    return true;
}

function _validateSkOfficialSuffix(form) {
    const suffixSelect = form.querySelector('[name="suffix"]');
    const otherInput = form.querySelector('[name="suffix_other"]');
    if (!suffixSelect) return true;

    toggleSkOfficialSuffixOther(form);

    if (!suffixSelect.value || suffixSelect.selectedIndex <= 0) {
        _showErr(suffixSelect, 'Suffix is required');
        return false;
    }

    if (suffixSelect.value === '__other__') {
        const other = (otherInput?.value || '').trim().toUpperCase();
        if (otherInput) otherInput.value = other.replace(/[^A-Z\-'.]/g, '');
        if (!other) {
            _showErr(otherInput || suffixSelect, 'Other suffix is required');
            return false;
        }
        if (other.length > SK_OFFICIAL_SUFFIX_OTHER_MAX) {
            _showErr(otherInput, `Other suffix must not exceed ${SK_OFFICIAL_SUFFIX_OTHER_MAX} characters`);
            return false;
        }
        if (/\s/.test(other)) {
            _showErr(otherInput, 'Other suffix cannot contain spaces');
            return false;
        }
        _markValid(otherInput);
    }

    _markValid(suffixSelect);
    return true;
}

function isSkFedManualForm(form) {
    return form?.id === 'addSkFedForm' || form?.id === 'editAccountForm';
}

function getSkFedBirthdateBounds() {
    const today = new Date();
    const maxDob = new Date(today.getFullYear() - SK_OFFICIAL_AGE_MIN, today.getMonth(), today.getDate());
    const minDob = new Date(today.getFullYear() - SK_OFFICIAL_AGE_MAX, today.getMonth(), today.getDate());
    return {
        min: formatLocalDate(minDob),
        max: formatLocalDate(maxDob),
    };
}

function applySkFedDobConstraints(form) {
    if (!isSkFedManualForm(form)) return;
    const dob = form.querySelector('[name="date_of_birth"]');
    if (!dob) return;
    const bounds = getSkFedBirthdateBounds();
    dob.min = bounds.min;
    dob.max = bounds.max;
}

function _validateSkFedBirthdate(form) {
    const dob = form.querySelector('[name="date_of_birth"]');
    const age = form.querySelector('[name="age"]');
    if (!dob) return true;

    const val = dob.value;
    if (!val) {
        _showErr(dob, 'Birthdate is required');
        if (age) age.value = '';
        return false;
    }

    const bounds = getSkFedBirthdateBounds();
    if (val < bounds.min || val > bounds.max) {
        _showErr(dob, `Birthdate must correspond to age ${SK_OFFICIAL_AGE_MIN}–${SK_OFFICIAL_AGE_MAX}`);
        if (age) age.value = '';
        return false;
    }

    const computedAge = calculateAge(val);
    if (age) age.value = computedAge;
    const ageNum = parseInt(computedAge, 10);
    if (Number.isNaN(ageNum) || ageNum < SK_OFFICIAL_AGE_MIN || ageNum > SK_OFFICIAL_AGE_MAX) {
        _showErr(dob, `Age must be between ${SK_OFFICIAL_AGE_MIN} and ${SK_OFFICIAL_AGE_MAX}`);
        return false;
    }

    _markValid(dob);
    if (age) _markValid(age);
    return true;
}

function wireSkFedManualValidation(form) {
    if (!isSkFedManualForm(form)) return;
    if (form.dataset.fedValidationWired === '1') return;
    form.dataset.fedValidationWired = '1';

    applySkFedDobConstraints(form);

    const dob = form.querySelector('[name="date_of_birth"]');
    if (dob) {
        dob.addEventListener('change', () => _validateSkFedBirthdate(form));
        dob.addEventListener('blur', () => _validateSkFedBirthdate(form));
    }

    ['term_start', 'term_end'].forEach((name) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) {
            input.addEventListener('change', () => validateTermRange(form));
            input.addEventListener('blur', () => validateTermRange(form));
        }
    });

    const suffixSelect = form.querySelector('[name="suffix"]');
    if (suffixSelect) {
        suffixSelect.addEventListener('change', () => {
            toggleSuffixOtherField(form);
            _validateFedSuffix(form);
        });
    }

    const suffixOther = form.querySelector('[name="suffix_other"]');
    if (suffixOther) {
        suffixOther.addEventListener('input', () => {
            suffixOther.value = suffixOther.value.replace(/[^a-zA-Z\-'.]/g, '').toUpperCase().slice(0, SK_OFFICIAL_SUFFIX_OTHER_MAX);
            _validateFedSuffix(form);
        });
        suffixOther.addEventListener('blur', () => _validateFedSuffix(form));
    }
}

function validateSkFedManualForm(form) {
    let valid = true;
    form.querySelectorAll('[required]').forEach((el) => {
        if (el.name === 'date_of_birth') {
            if (!_validateSkFedBirthdate(form)) valid = false;
            return;
        }
        if (!_validateField(el)) valid = false;
    });
    if (!_validateFedSuffix(form)) valid = false;
    if (!validateTermRange(form)) valid = false;
    return valid;
}

function getSkOfficialBirthdateBounds() {
    const today = new Date();
    const maxDob = new Date(today.getFullYear() - SK_OFFICIAL_AGE_MIN, today.getMonth(), today.getDate());
    const minDob = new Date(today.getFullYear() - SK_OFFICIAL_AGE_MAX, today.getMonth(), today.getDate());
    return {
        min: formatLocalDate(minDob),
        max: formatLocalDate(maxDob),
    };
}

function applySkOfficialDobConstraints(form) {
    if (!isSkOfficialsForm(form)) return;
    const dob = form.querySelector('[name="date_of_birth"]');
    if (!dob) return;
    dob.removeAttribute('min');
    dob.max = formatLocalDate(new Date());
}

function _validateSkOfficialBirthdate(form) {
    const dob = form.querySelector('[name="date_of_birth"]');
    if (!dob) return true;

    const rawValue = dob.value;
    if (!rawValue) {
        _showErr(dob, 'Birthdate is required');
        return false;
    }

    if (dob.type === 'text' && !isStrictUsDateFormat(rawValue)) {
        _showErr(dob, 'Birthdate must use MM/DD/YYYY format');
        return false;
    }

    const val = normalizeDateInputValue(rawValue);
    if (!val) {
        _showErr(dob, 'Birthdate must be a valid date in MM/DD/YYYY format');
        return false;
    }

    const today = formatLocalDate(new Date());
    if (val >= today) {
        _showErr(dob, 'Birthdate must be before today');
        return false;
    }

    _markValid(dob);
    return true;
}

function _validateSkOfficialAge(input) {
    if (!input) return true;
    const val = String(input.value || '').trim();
    if (!val) {
        _showErr(input, 'Age is required');
        return false;
    }
    if (!/^\d{1,2}$/.test(val)) {
        _showErr(input, 'Age must be a valid number');
        return false;
    }
    const ageNum = parseInt(val, 10);
    if (ageNum < SK_OFFICIAL_AGE_MIN || ageNum > SK_OFFICIAL_AGE_MAX) {
        _showErr(input, `Age must be between ${SK_OFFICIAL_AGE_MIN} and ${SK_OFFICIAL_AGE_MAX}`);
        return false;
    }
    _markValid(input);
    return true;
}

function _validateSkOfficialEmail(input) {
    if (!input) return true;
    let val = (input.value || '').toLowerCase().replace(/\s+/g, '');
    input.value = val;

    if (!val) {
        _showErr(input, 'Email address is required');
        return false;
    }
    if (!SK_OFFICIAL_GMAIL_REGEX.test(val)) {
        _showErr(input, 'Enter a valid @gmail.com address (6–30 characters before @)');
        return false;
    }
    _markValid(input);
    return true;
}

function validateSkOfficialsManualForm(form) {
    let valid = true;
    const first = form.querySelector('[name="first_name"]');
    const middle = form.querySelector('[name="middle_name"]');
    const last = form.querySelector('[name="last_name"]');
    const age = form.querySelector('[name="age"]');
    const email = form.querySelector('[name="email"]');
    const contact = form.querySelector('[name="contact_number"]');

    if (!_validateSkOfficialFirstName(first)) valid = false;
    if (!_validateSkOfficialMiddleName(middle)) valid = false;
    if (!_validateSkOfficialLastName(last)) valid = false;
    if (!_validateSkOfficialSuffix(form)) valid = false;
    if (!_validateSkOfficialBirthdate(form)) valid = false;
    if (age && !_validateSkOfficialAge(age)) valid = false;
    if (!_validateSkOfficialEmail(email)) valid = false;
    if (contact && !_validateContactNumber(contact)) valid = false;
    if (!validateTermRange(form)) valid = false;

    form.querySelectorAll('select[required]').forEach((select) => {
        if (select.name === 'suffix') return;
        if (!select.value || select.selectedIndex <= 0) {
            _showErr(select, 'Please select an option');
            valid = false;
        } else {
            _markValid(select);
        }
    });

    return valid;
}

function wireSkOfficialsManualValidation(form) {
    if (!isSkOfficialsForm(form)) return;
    if (form.dataset.skValidationWired === '1') return;
    form.dataset.skValidationWired = '1';

    applySkOfficialDobConstraints(form);

    const nameMap = {
        first_name: _validateSkOfficialFirstName,
        middle_name: _validateSkOfficialMiddleName,
        last_name: _validateSkOfficialLastName,
    };

    Object.entries(nameMap).forEach(([name, fn]) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) return;
        el.addEventListener('input', () => fn(el));
        el.addEventListener('blur', () => fn(el));
        el.addEventListener('keydown', (e) => {
            if (name === 'first_name') {
                if (e.key === ' ' && (el.value || '').includes(' ')) {
                    e.preventDefault();
                    return;
                }
            } else if (e.key === ' ') {
                e.preventDefault();
                return;
            }
            const len = (el.value || '').length;
            if (len >= SK_OFFICIAL_NAME_MAX && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key) && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                showSkOfficialMaxMessage(el);
            }
        });
    });

    const suffixSelect = form.querySelector('[name="suffix"]');
    if (suffixSelect) {
        suffixSelect.addEventListener('change', () => {
            toggleSkOfficialSuffixOther(form);
            _validateSkOfficialSuffix(form);
        });
    }

    const suffixOther = form.querySelector('[name="suffix_other"]');
    if (suffixOther) {
        suffixOther.addEventListener('input', () => {
            suffixOther.value = suffixOther.value.replace(/[^a-zA-Z\-'.]/g, '').toUpperCase().slice(0, SK_OFFICIAL_SUFFIX_OTHER_MAX);
            _validateSkOfficialSuffix(form);
        });
        suffixOther.addEventListener('blur', () => _validateSkOfficialSuffix(form));
    }

    const email = form.querySelector('[name="email"]');
    if (email) {
        email.addEventListener('input', () => _validateSkOfficialEmail(email));
        email.addEventListener('blur', () => _validateSkOfficialEmail(email));
        email.addEventListener('keydown', (e) => {
            if (e.key === ' ') e.preventDefault();
        });
    }

    const contact = form.querySelector('[name="contact_number"]');
    if (contact) {
        contact.addEventListener('input', () => _validateContactNumber(contact));
        contact.addEventListener('blur', () => _validateContactNumber(contact));
    }

    const age = form.querySelector('[name="age"]');
    if (age) {
        age.addEventListener('input', () => _validateSkOfficialAge(age));
        age.addEventListener('blur', () => _validateSkOfficialAge(age));
    }

    form.querySelectorAll('select[required]').forEach((select) => {
        if (select.name === 'suffix') return;
        select.addEventListener('change', () => {
            if (!select.value || select.selectedIndex <= 0) {
                _showErr(select, 'Please select an option');
            } else {
                _markValid(select);
            }
        });
    });

    const dob = form.querySelector('[name="date_of_birth"]');
    if (dob) {
        dob.addEventListener('change', () => _validateSkOfficialBirthdate(form));
        dob.addEventListener('blur', () => _validateSkOfficialBirthdate(form));
    }

    ['term_start', 'term_end'].forEach((name) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) {
            input.addEventListener('change', () => validateTermRange(form));
            input.addEventListener('blur', () => validateTermRange(form));
        }
    });
}

function isNameField(input) {
    return ['first_name', 'last_name', 'middle_name'].includes(input.name);
}

function isContactField(input) {
    return input.name === 'contact_number';
}

function isTermField(input) {
    return input.name === 'term_start' || input.name === 'term_end';
}

function _validateNameField(input) {
    const val = (input.value || '').trim();
    if (input.hasAttribute('required') && !val) {
        _showErr(input, 'This field is required');
        return false;
    }
    if (val && !/^[A-Z\s\-']+$/.test(val)) {
        _showErr(input, 'Use uppercase letters only');
        return false;
    }
    _clearErr(input);
    if (val) input.classList.add('is-valid');
    return true;
}

function _validateContactNumber(input) {
    const val = (input.value || '').trim();
    if (input.hasAttribute('required') && !val) {
        _showErr(input, 'Contact number is required');
        return false;
    }
    if (val && !/^09\d{9}$/.test(val)) {
        _showErr(input, 'Contact number must be 11 digits starting with 09');
        return false;
    }
    _clearErr(input);
    if (val) input.classList.add('is-valid');
    return true;
}

function validateTermRange(form) {
    const startInput = form.querySelector('[name="term_start"]');
    const endInput = form.querySelector('[name="term_end"]');
    if (!startInput || !endInput) {
        return true;
    }

    const rawStart = startInput.value;
    const rawEnd = endInput.value;
    const start = rawStart ? normalizeDateInputValue(rawStart) : '';
    const end = rawEnd ? normalizeDateInputValue(rawEnd) : '';
    const termStartMin = getTermStartMinDate(form);
    const termStartMax = getTermStartMaxDate(form);
    const isOfficialsForm = isSkOfficialsForm(form);

    let termValid = true;

    if (startInput.hasAttribute('required') && !rawStart) {
        _showErr(startInput, 'Term start date is required');
        termValid = false;
    } else if (startInput.type === 'text' && rawStart && !isStrictUsDateFormat(rawStart)) {
        _showErr(startInput, 'Term start date must use MM/DD/YYYY format');
        termValid = false;
    } else if (rawStart && !start) {
        _showErr(startInput, 'Term start date must be a valid date in MM/DD/YYYY format');
        termValid = false;
    } else {
        _clearErr(startInput);
    }

    if (endInput.hasAttribute('required') && !rawEnd) {
        _showErr(endInput, 'Term end date is required');
        termValid = false;
    } else if (endInput.type === 'text' && rawEnd && !isStrictUsDateFormat(rawEnd)) {
        _showErr(endInput, 'Term end date must use MM/DD/YYYY format');
        termValid = false;
    } else if (rawEnd && !end) {
        _showErr(endInput, 'Term end date must be a valid date in MM/DD/YYYY format');
        termValid = false;
    } else if (termValid) {
        _clearErr(endInput);
    }

    if (!termValid) return false;
    if (start && (start < termStartMin || start > termStartMax)) {
        _showErr(
            startInput,
            isOfficialsForm
                ? SK_OFFICIAL_TERM_START_MESSAGE
                : 'Term start date must be within the current year'
        );
        return false;
    }
    if (isOfficialsForm && start && !isValidSkOfficialTermStart(start)) {
        _showErr(startInput, SK_OFFICIAL_TERM_START_MESSAGE);
        return false;
    }
    if (start && end) {
        if (isOfficialsForm) {
            const minEnd = skOfficialTermEndForStart(start);
            const maxEnd = addYearsToDateString(start, SK_OFFICIAL_TERM_MAX_YEARS);
            if (end < minEnd || end > maxEnd) {
                _showErr(endInput, SK_OFFICIAL_TERM_END_MESSAGE);
                return false;
            }
        } else {
            const requiredEnd = addYearsToDateString(start, ACCOUNT_TERM_YEARS);
            if (end !== requiredEnd) {
                _showErr(endInput, `Term end must be exactly ${ACCOUNT_TERM_YEARS} years after term start`);
                return false;
            }
        }
    }

    _clearErr(startInput);
    _clearErr(endInput);
    if (start) startInput.classList.add('is-valid');
    if (end) endInput.classList.add('is-valid');
    return true;
}

function _validateField(input) {
    const val = input.value.trim();
    if (isNameField(input)) {
        return _validateNameField(input);
    }
    if (isContactField(input)) {
        return _validateContactNumber(input);
    }
    if (isTermField(input)) {
        return validateTermRange(input.closest('form'));
    }
    if (input.hasAttribute('required') && !val) { _showErr(input, 'This field is required'); return false; }
    if (input.type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { _showErr(input, 'Enter a valid email address'); return false; }
    if (input.tagName === 'SELECT' && input.hasAttribute('required') && (!val || val === '')) { _showErr(input, 'Please select an option'); return false; }
    _clearErr(input);
    if (val) input.classList.add('is-valid');
    return true;
}

function applyUppercaseNameInput(input) {
    if (!input) return;
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^a-zA-Z\s\-']/g, '').toUpperCase();
        if (input.classList.contains('is-invalid')) {
            _validateNameField(input);
        }
    });
    input.addEventListener('blur', () => _validateNameField(input));
}

function applyContactNumberInput(input) {
    if (!input) return;
    const prefix = '09';

    input.addEventListener('focus', () => {
        if (!input.value.startsWith(prefix)) {
            input.value = prefix;
        }
    });

    input.addEventListener('input', () => {
        let digits = input.value.replace(/\D/g, '');
        if (!digits.startsWith('09')) {
            digits = `${prefix}${digits.replace(/^0+/, '')}`;
        }
        input.value = digits.slice(0, 11);
        if (input.classList.contains('is-invalid')) {
            _validateContactNumber(input);
        }
    });

    input.addEventListener('blur', () => _validateContactNumber(input));
}

function applyUsDateTextInput(input) {
    if (window.AccountDateFieldUtils) {
        window.AccountDateFieldUtils.applyUsDateTextInput(input);
    }
}

function clampDateInputYear(input) {
    if (!input || input.type !== 'date') {
        return;
    }

    input.addEventListener('input', () => {
        const value = input.value;
        if (!value) {
            return;
        }

        const match = /^(\d+)-(\d{2})-(\d{2})$/.exec(value);
        if (!match) {
            input.value = '';
            return;
        }

        const year = match[1];
        if (year.length !== 4) {
            input.value = '';
        }
    });
}

function applyFutureOnlyDateConstraints(form) {
    const today = new Date().toISOString().slice(0, 10);
    form.querySelectorAll('input[type="date"]').forEach((input) => {
        if (input.name === 'date_of_birth') {
            input.max = today;
            return;
        }

        if (isTermField(input)) {
            return;
        }

        input.min = today;
        clampDateInputYear(input);
    });
}

function applyTermDateConstraints(form) {
    const startInput = form.querySelector('[name="term_start"]');
    const endInput = form.querySelector('[name="term_end"]');
    if (!startInput || !endInput) {
        return;
    }

    const isOfficialsForm = isSkOfficialsForm(form);

    startInput.min = getTermStartMinDate(form);
    startInput.max = getTermStartMaxDate(form);

    if (isOfficialsForm) {
        if (!startInput.value) {
            startInput.value = form.id === 'editSkOfficialsForm'
                ? formatDateForTextInput(currentSkOfficialTermStart())
                : currentSkOfficialTermStart();
        } else {
            const normalizedStart = normalizeDateInputValue(startInput.value);
            if (normalizedStart) {
                const snappedStart = snapToSkOfficialTermStart(normalizedStart);
                startInput.value = form.id === 'editSkOfficialsForm'
                    ? formatDateForTextInput(snappedStart)
                    : snappedStart;
            }
        }
    }

    const alreadyWired = form.dataset.termConstraintsWired === '1';
    const isEditOfficialsTextDates = form.id === 'editSkOfficialsForm';

    const syncEndConstraints = () => {
        if (isOfficialsForm) {
            const normalizedStart = normalizeDateInputValue(startInput.value);
            if (normalizedStart) {
                const snappedStart = snapToSkOfficialTermStart(normalizedStart);
                startInput.value = form.id === 'editSkOfficialsForm'
                    ? formatDateForTextInput(snappedStart)
                    : snappedStart;
            } else if (form.id === 'addSkOfficialsForm') {
                startInput.value = currentSkOfficialTermStart();
            }
        } else if (startInput.value && (startInput.value < startInput.min || startInput.value > startInput.max)) {
            startInput.value = '';
        }
        const effectiveStart = normalizeDateInputValue(startInput.value);
        if (effectiveStart) {
            if (isOfficialsForm) {
                const minEnd = skOfficialTermEndForStart(effectiveStart);
                const maxEnd = addYearsToDateString(effectiveStart, SK_OFFICIAL_TERM_MAX_YEARS);
                endInput.min = minEnd;
                endInput.max = maxEnd;
                const currentEnd = normalizeDateInputValue(endInput.value);
                if (!currentEnd || currentEnd < minEnd || currentEnd > maxEnd) {
                    endInput.value = form.id === 'editSkOfficialsForm'
                        ? formatDateForTextInput(minEnd)
                        : minEnd;
                }
            } else {
                const exactEnd = addYearsToDateString(effectiveStart, ACCOUNT_TERM_YEARS);
                endInput.min = exactEnd;
                endInput.max = exactEnd;
                endInput.value = exactEnd;
            }
        } else if (!isEditOfficialsTextDates) {
            endInput.value = '';
            endInput.removeAttribute('min');
            endInput.removeAttribute('max');
        }
        validateTermRange(form);
    };

    if (!alreadyWired) {
        clampDateInputYear(startInput);
        clampDateInputYear(endInput);
        startInput.addEventListener('change', syncEndConstraints);
        if (!isEditOfficialsTextDates) {
            startInput.addEventListener('input', syncEndConstraints);
        }
        endInput.addEventListener('change', () => {
            if (isOfficialsForm) {
                syncEndConstraints();
                return;
            }
            validateTermRange(form);
        });
        if (!isEditOfficialsTextDates) {
            endInput.addEventListener('input', () => {
                if (isOfficialsForm) {
                    syncEndConstraints();
                }
            });
        }
        endInput.addEventListener('blur', () => validateTermRange(form));
        form.dataset.termConstraintsWired = '1';
    }
    syncEndConstraints();
}

function initCreateAccountFormDefaults(form) {
    if (!form) {
        return;
    }

    const status = form.querySelector('[name="status"]');
    if (status) {
        status.value = 'ACTIVE';
    }

    const contact = form.querySelector('[name="contact_number"]');
    if (contact && (!contact.value || contact.value.length < 2)) {
        contact.value = '09';
    }
}

function wireCreateAccountForm(form) {
    if (!form) {
        return;
    }

    if (isSkOfficialsForm(form)) {
        wireSkOfficialsManualValidation(form);
    } else if (isSkFedManualForm(form)) {
        wireSkFedManualValidation(form);
        form.querySelectorAll('[name="first_name"], [name="last_name"], [name="middle_name"]').forEach(applyUppercaseNameInput);
    } else {
        form.querySelectorAll('[name="first_name"], [name="last_name"], [name="middle_name"]').forEach(applyUppercaseNameInput);
    }

    applyContactNumberInput(form.querySelector('[name="contact_number"]'));
    applyTermDateConstraints(form);
    applyFutureOnlyDateConstraints(form);
    applySkOfficialDobConstraints(form);
    applySkFedDobConstraints(form);
    initCreateAccountFormDefaults(form);

    if (form.id === 'editSkOfficialsForm') {
        ['date_of_birth', 'term_start', 'term_end'].forEach((name) => {
            applyUsDateTextInput(form.querySelector(`[name="${name}"]`));
        });
    }

    if (!isSkOfficialsForm(form) && !isSkFedManualForm(form)) {
        form.querySelectorAll('[required]').forEach((el) => {
            el.addEventListener('blur', () => _validateField(el));
        });
    }
}

// ── Add SK Officials modal ────────────────────────────────────
let addOfficialsIsMaximized = false;

window.toggleAddOfficialsSize = function () {
    const overlay = document.getElementById('addSkOfficialsModal');
    const content = document.getElementById('addSkOfficialsModalContent');
    const btn = document.getElementById('addOfficialsResizeBtn');
    if (!overlay || !content) return;
    addOfficialsIsMaximized = !addOfficialsIsMaximized;
    applyModalResizeState({ overlay, content, btn, isMaximized: addOfficialsIsMaximized });
};

window.openAddSkOfficialsModal = function () {
    switchAddOfficialTab('manual');
    const form = document.getElementById('addSkOfficialsForm');
    if (form) {
        form.querySelectorAll('.is-invalid,.is-valid').forEach(el => el.classList.remove('is-invalid', 'is-valid'));
        form.querySelectorAll('.validation-error').forEach(el => el.remove());
        form.querySelectorAll('.form-error-light').forEach(el => { el.textContent = ''; });
    }
    toggleModal('addSkOfficialsModal', true);
    initCreateAccountFormDefaults(form);
};

window.closeAddSkOfficialsModal = function () {
    addOfficialsIsMaximized = false;
    const overlay = document.getElementById('addSkOfficialsModal');
    const content = document.getElementById('addSkOfficialsModalContent');
    const btn = document.getElementById('addOfficialsResizeBtn');
    resetModalResizeState({ overlay, content, btn });
    const form = document.getElementById('addSkOfficialsForm');
    if (form) {
        form.reset();
        form.querySelectorAll('.is-invalid,.is-valid').forEach(el => el.classList.remove('is-invalid', 'is-valid'));
        form.querySelectorAll('.validation-error').forEach(el => el.remove());
        form.querySelectorAll('.form-error-light').forEach(el => { el.textContent = ''; });
    }
    switchAddOfficialTab('manual');
    resetBatchUploadPanel('official');
    toggleModal('addSkOfficialsModal', false);
};

window.showSkOfficialsSuccessModal = function () { showAccountToast('SK Officials account successfully created!', 'success'); };
window.closeSkOfficialsSuccessModal = function () { };

window.switchAddOfficialTab = function (tab) {
    const manual = document.getElementById('addOfficialManualPane');
    const batch = document.getElementById('addOfficialBatchPane');
    const manualFooter = document.getElementById('addOfficialManualFooter');
    const batchFooter = document.getElementById('addOfficialBatchFooter');
    const tM = document.getElementById('tabManual');
    const tB = document.getElementById('tabBatch');
    const isManual = tab === 'manual';
    if (manual) manual.style.display = isManual ? '' : 'none';
    if (batch) batch.style.display = isManual ? 'none' : '';
    if (manualFooter) manualFooter.style.display = isManual ? '' : 'none';
    if (batchFooter) batchFooter.style.display = isManual ? 'none' : '';
    if (tM) tM.classList.toggle('active', isManual);
    if (tB) tB.classList.toggle('active', !isManual);
    if (!isManual && typeof window.resetBatchUploadPanel === 'function') {
        window.resetBatchUploadPanel('official');
    }
};

window.switchAddFedTab = function (tab) {
    const manual = document.getElementById('addFedManualPane');
    const batch = document.getElementById('addFedBatchPane');
    const manualFooter = document.getElementById('addFedManualFooter');
    const batchFooter = document.getElementById('addFedBatchFooter');
    const tM = document.getElementById('fedTabManual');
    const tB = document.getElementById('fedTabBatch');
    const isManual = tab === 'manual';
    if (manual) manual.style.display = isManual ? '' : 'none';
    if (batch) batch.style.display = isManual ? 'none' : '';
    if (manualFooter) manualFooter.style.display = isManual ? '' : 'none';
    if (batchFooter) batchFooter.style.display = isManual ? 'none' : '';
    if (tM) tM.classList.toggle('active', isManual);
    if (tB) tB.classList.toggle('active', !isManual);
    if (!isManual && typeof window.resetBatchUploadPanel === 'function') {
        window.resetBatchUploadPanel('fed');
    }
};

// ── Edit SK Officials modal ───────────────────────────────────
let editOfficialsIsMaximized = false;

window.openEditSkOfficialsModal = function () { toggleModal('editSkOfficialsModal', true); };

function resetEditSkOfficialsFormState(form) {
    if (!form) return;
    form.reset();
    form.dataset.accountId = '';
    form.querySelectorAll('.is-invalid,.is-valid').forEach((field) => field.classList.remove('is-invalid', 'is-valid'));
    form.querySelectorAll('.validation-error').forEach((error) => error.remove());
    form.querySelectorAll('.form-error-light').forEach((error) => { error.textContent = ''; });
}

window.closeEditSkOfficialsModal = function () {
    editOfficialsIsMaximized = false;
    const overlay = document.getElementById('editSkOfficialsModal');
    const content = overlay ? overlay.querySelector('.modal-content') : null;
    const btn = document.getElementById('editOfficialsResizeBtn');
    resetModalResizeState({ overlay, content, btn });
    const form = document.getElementById('editSkOfficialsForm');
    resetEditSkOfficialsFormState(form);
    toggleModal('editSkOfficialsModal', false);
};

window.showEditSkOfficialsSuccessModal = function () {
    toggleModal('editSkOfficialsModal', false);
    showAccountToast('SK Officials account updated successfully!', 'edit');
};
window.closeEditSkOfficialsSuccessModal = function () { };

window.toggleEditOfficialsSize = function () {
    const overlay = document.getElementById('editSkOfficialsModal');
    const content = overlay ? overlay.querySelector('.modal-content') : null;
    const btn = document.getElementById('editOfficialsResizeBtn');
    if (!overlay || !content) return;
    editOfficialsIsMaximized = !editOfficialsIsMaximized;
    applyModalResizeState({ overlay, content, btn, isMaximized: editOfficialsIsMaximized });
};
// Keep legacy aliases so any remaining references don't break
window.toggleFullscreenEditSkOfficialsModal = window.toggleEditOfficialsSize;
window.toggleRestoreEditSkOfficialsModal = window.toggleEditOfficialsSize;
window.restoreEditSkOfficialsModal = window.toggleEditOfficialsSize;

// ── Add SK Federation modal ───────────────────────────────────
let addFedIsMaximized = false;

window.toggleAddFedSize = function () {
    const overlay = document.getElementById('addAccountModal');
    const content = document.getElementById('addSkFedModalContent');
    const btn = document.getElementById('addFedResizeBtn');
    if (!overlay || !content) return;
    addFedIsMaximized = !addFedIsMaximized;
    applyModalResizeState({ overlay, content, btn, isMaximized: addFedIsMaximized });
};

window.openAddAccountModal = function () {
    const type = getCurrentAccountType();
    const ids = _getModalIds(type);
    const formId = type === 'sk_officials' ? 'addSkOfficialsForm' : 'addSkFedForm';
    const form = document.getElementById(formId);
    if (form) {
        form.querySelectorAll('.is-invalid,.is-valid').forEach(el => el.classList.remove('is-invalid', 'is-valid'));
        form.querySelectorAll('.validation-error').forEach(el => el.remove());
        form.querySelectorAll('.form-error-light').forEach(el => { el.textContent = ''; });
    }
    if (type === 'sk_officials') {
        switchAddOfficialTab('manual');
    } else {
        switchAddFedTab('manual');
    }
    toggleModal(ids.addModalId, true);
    initCreateAccountFormDefaults(form);
};

window.closeAddAccountModal = function () {
    addFedIsMaximized = false;
    const overlay = document.getElementById('addAccountModal');
    const content = document.getElementById('addSkFedModalContent');
    const btn = document.getElementById('addFedResizeBtn');
    resetModalResizeState({ overlay, content, btn });
    const form = document.getElementById('addSkFedForm');
    if (form) {
        form.reset();
        form.querySelectorAll('.is-invalid,.is-valid').forEach(f => f.classList.remove('is-invalid', 'is-valid'));
        form.querySelectorAll('.validation-error').forEach(e => e.remove());
    }
    switchAddFedTab('manual');
    resetBatchUploadPanel('fed');
    const ids = _getModalIds(getCurrentAccountType());
    toggleModal(ids.addModalId, false);
};

window.showAddSuccessModal = function () { showAccountToast('Account successfully created!', 'success'); };
window.closeAddSuccessModal = function () { };

// ── Edit SK Federation modal ──────────────────────────────────
let editFedIsMaximized = false;

window.openEditModal = function () { toggleModal('editAccountModal', true); };

window.closeEditModal = function () {
    editFedIsMaximized = false;
    const overlay = document.getElementById('editAccountModal');
    const content = overlay ? overlay.querySelector('.modal-content') : null;
    const btn = document.getElementById('editFedResizeBtn');
    resetModalResizeState({ overlay, content, btn });
    const form = document.getElementById('editAccountForm');
    if (form) {
        form.reset();
        form.querySelectorAll('.is-invalid,.is-valid').forEach(f => f.classList.remove('is-invalid', 'is-valid'));
        form.querySelectorAll('.validation-error').forEach(e => e.remove());
    }
    toggleModal('editAccountModal', false);
};

window.showEditSuccessModal = function () {
    toggleModal('editAccountModal', false);
    showAccountToast('Account updated successfully!', 'edit');
};
window.closeEditSuccessModal = function () { };

window.toggleEditFedSize = function () {
    const overlay = document.getElementById('editAccountModal');
    const content = overlay ? overlay.querySelector('.modal-content') : null;
    const btn = document.getElementById('editFedResizeBtn');
    if (!overlay || !content) return;
    editFedIsMaximized = !editFedIsMaximized;
    applyModalResizeState({ overlay, content, btn, isMaximized: editFedIsMaximized });
};
// Keep legacy aliases so any remaining references don't break
window.toggleFullscreenEditAccountModal = window.toggleEditFedSize;
window.toggleRestoreEditAccountModal = window.toggleEditFedSize;
window.restoreEditAccountModal = window.toggleEditFedSize;

// ── View modal ────────────────────────────────────────────────
let viewIsMaximized = false;

window.openViewModal = function () {
    toggleModal('viewAccountModal', true);
};
window.closeViewModal = function () {
    viewIsMaximized = false;
    const overlay = document.getElementById('viewAccountModal');
    const content = overlay ? overlay.querySelector('.modal-content') : null;
    const btn = document.getElementById('viewToggleBtn');
    resetModalResizeState({ overlay, content, btn });
    toggleModal('viewAccountModal', false);
};
window.toggleFullscreenViewModal = function () {
    const overlay = document.getElementById('viewAccountModal');
    const content = overlay ? overlay.querySelector('.modal-content') : null;
    const btn = document.getElementById('viewToggleBtn');
    if (!overlay || !content) return;
    viewIsMaximized = !viewIsMaximized;
    applyModalResizeState({ overlay, content, btn, isMaximized: viewIsMaximized });
};
window.toggleRestoreViewModal = window.toggleFullscreenViewModal;
window.restoreViewModal = window.toggleFullscreenViewModal;

// ── Internal helpers ──────────────────────────────────────────
function _getModalIds(type) {
    if (type === 'sk_officials') return { addModalId: 'addSkOfficialsModal', successModalId: 'skOfficialsSuccessModal' };
    return { addModalId: 'addAccountModal', successModalId: 'addSuccessModal' };
}

function _closeEditByType() {
    if (getCurrentAccountType() === 'sk_officials') { closeEditSkOfficialsModal(); return; }
    closeEditModal();
}
function _showEditSuccessByType() {
    if (getCurrentAccountType() === 'sk_officials') { showEditSkOfficialsSuccessModal(); return; }
    showEditSuccessModal();
}

// ── Batch upload panels (embedded in Add modals) ──────────────
const BATCH_HEADER_ALIASES = {
    'first name': 'first_name', first_name: 'first_name',
    'middle name': 'middle_name',
    middle_name: 'middle_name',
    'middle name (optional)': 'middle_name',
    'last name': 'last_name', last_name: 'last_name',
    suffix: 'suffix',
    sex: 'sex', gender: 'sex',
    birthdate: 'date_of_birth', 'birth date': 'date_of_birth', 'date of birth': 'date_of_birth',
    date_of_birth: 'date_of_birth', dob: 'date_of_birth',
    age: 'age',
    'contact number': 'contact_number', contact_number: 'contact_number', contact: 'contact_number',
    position: 'position',
    region: 'region',
    province: 'province',
    municipality: 'municipality',
    barangay: 'barangay', 'barangay name': 'barangay', barangay_name: 'barangay',
    'term start': 'term_start',
    'term start date': 'term_start',
    'term start date (mm/dd/yyyy)': 'term_start',
    term_start: 'term_start',
    'start date': 'term_start',
    'term end': 'term_end',
    'term end date': 'term_end',
    'term end date (mm/dd/yyyy)': 'term_end',
    term_end: 'term_end',
    'end date': 'term_end',
    'email address': 'email',
    email: 'email',
    'middle name (optional)': 'middle_name',
};

const BATCH_TEMPLATE_HEADERS = [
    'First Name',
    'Middle Name (optional)',
    'Last Name',
    'Suffix (None)',
    'Sex',
    'Birthdate',
    'Age',
    'Contact Number',
    'Position',
    'Region',
    'Province',
    'Municipality',
    'Barangay',
    'Term Start Date (MM/DD/YYYY)',
    'Term End Date (MM/DD/YYYY)',
    'Email Address',
];

const BATCH_TEMPLATE_SAMPLE_ROW = [
    '', '', '', '', '', '', '', '', '',
    'IV-A CALABARZON',
    'Laguna',
    'Santa Cruz',
    '', '', '', '',
];

const BATCH_DATE_FIELD_KEYS = new Set(['date_of_birth', 'term_start', 'term_end']);

function batchPreviewColumnClass(fieldKey) {
    if (fieldKey === 'email') return 'batch-col-email';
    if (fieldKey === 'contact_number') return 'batch-col-contact';
    if (fieldKey === 'position') return 'batch-col-position';
    if (BATCH_DATE_FIELD_KEYS.has(fieldKey)) return 'batch-col-date';
    if (['first_name', 'middle_name', 'last_name'].includes(fieldKey)) return 'batch-col-name';
    if (['region', 'province', 'municipality', 'barangay'].includes(fieldKey)) return 'batch-col-location';
    return 'batch-col-default';
}

function normalizeBatchHeaderLabel(header) {
    return String(header || '')
        .trim()
        .replace(/\s*\([^)]*\)\s*/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

function isOptionalBatchHeader(header) {
    const normalized = normalizeBatchHeaderLabel(header);
    return normalized === 'middle name';
}

const BATCH_OPTIONAL_HEADERS = new Set(['middle name', 'middle_name', 'middle name (optional)']);

const BATCH_REQUIRED_HEADERS = BATCH_TEMPLATE_HEADERS.filter(
    (header) => !isOptionalBatchHeader(header)
);

const BATCH_MAX_ACCOUNTS = 260;
const BATCH_OFFICIAL_LAST_NAME_REGEX = /^[A-Z.\-']+$/;
const BATCH_OFFICIAL_MIDDLE_NAME_REGEX = /^[A-Z.\-']*$/;
const BATCH_SK_OFFICIAL_POSITIONS = ['Chairperson', 'Secretary', 'Treasurer', 'Kagawad'];
const BATCH_SK_FED_POSITIONS = ['President', 'Vice President', 'Secretary', 'Treasurer', 'PIO', 'Sergeant at Arms'];

function normalizeBatchLookupKey(value) {
    return String(value || '').trim().toLowerCase();
}

function digitToRomanBatch(digit) {
    return ({ 1: 'I', 2: 'II', 3: 'III', 4: 'IV', 5: 'V' })[digit] || null;
}

function romanToDigitBatch(roman) {
    return ({ I: 1, II: 2, III: 3, IV: 4, V: 5 })[String(roman || '').toUpperCase()] || null;
}

function normalizeRomanOrDigitTokenBatch(token) {
    const trimmed = String(token || '').trim();
    if (/^\d+$/.test(trimmed)) {
        return digitToRomanBatch(parseInt(trimmed, 10)) || trimmed;
    }
    return trimmed.toUpperCase();
}

function normalizeBatchBarangayName(name) {
    const trimmed = String(name || '').trim();
    let match;

    if ((match = /^barangay\s+([0-9ivx]+)\s*\(poblacion\)$/i.exec(trimmed))) {
        return 'Poblacion ' + normalizeRomanOrDigitTokenBatch(match[1]);
    }
    if ((match = /^poblacion\s+([0-9ivx]+)$/i.exec(trimmed))) {
        return 'Poblacion ' + normalizeRomanOrDigitTokenBatch(match[1]);
    }
    if ((match = /^barangay\s+([0-9ivx]+)$/i.exec(trimmed))) {
        return 'Poblacion ' + normalizeRomanOrDigitTokenBatch(match[1]);
    }
    if (trimmed.toLowerCase() === 'santa cruz') {
        return trimmed;
    }

    return trimmed;
}

let batchBarangayLookupCache = null;

function buildBatchBarangayLookup() {
    if (batchBarangayLookupCache) {
        return batchBarangayLookupCache;
    }

    const lookup = new Map();
    const select = document.getElementById('barangayFilter');
    if (!select) {
        batchBarangayLookupCache = lookup;
        return lookup;
    }

    [...select.options].forEach((opt) => {
        if (!opt.value || !opt.text) {
            return;
        }

        const name = opt.text.trim();
        const register = (candidate) => {
            lookup.set(normalizeBatchLookupKey(candidate), name);
        };

        register(name);
        const canonical = normalizeBatchBarangayName(name);
        if (canonical !== name) {
            register(canonical);
        }

        const poblacionMatch = /^poblacion\s+([ivx]+)$/i.exec(canonical);
        if (poblacionMatch) {
            const roman = poblacionMatch[1].toUpperCase();
            const digit = romanToDigitBatch(roman);
            if (digit !== null) {
                register('Poblacion ' + digit);
                register('Poblacion ' + roman);
                register('Barangay ' + digit);
                register('Barangay ' + roman);
                register('Barangay ' + roman + ' (Poblacion)');
            }
        }
    });

    batchBarangayLookupCache = lookup;
    return lookup;
}

function resolveBatchBarangayName(rawBarangay) {
    const barangayName = String(rawBarangay || '').trim();
    if (!barangayName) {
        return null;
    }

    const lookup = buildBatchBarangayLookup();
    const canonical = normalizeBatchBarangayName(barangayName);

    return lookup.get(normalizeBatchLookupKey(canonical))
        || lookup.get(normalizeBatchLookupKey(barangayName))
        || null;
}

function levenshteinDistance(a, b) {
    const left = String(a || '');
    const right = String(b || '');
    const dp = Array.from({ length: left.length + 1 }, () => new Array(right.length + 1).fill(0));
    for (let i = 0; i <= left.length; i++) dp[i][0] = i;
    for (let j = 0; j <= right.length; j++) dp[0][j] = j;
    for (let i = 1; i <= left.length; i++) {
        for (let j = 1; j <= right.length; j++) {
            const cost = left[i - 1] === right[j - 1] ? 0 : 1;
            dp[i][j] = Math.min(dp[i - 1][j] + 1, dp[i][j - 1] + 1, dp[i - 1][j - 1] + cost);
        }
    }
    return dp[left.length][right.length];
}

function nearestSuggestion(value, options) {
    const target = String(value || '').trim().toLowerCase();
    if (!target || !Array.isArray(options) || options.length === 0) return null;
    let best = null;
    let bestScore = Number.POSITIVE_INFINITY;
    options.forEach((option) => {
        const score = levenshteinDistance(target, String(option).toLowerCase());
        if (score < bestScore) {
            bestScore = score;
            best = option;
        }
    });
    return bestScore <= 3 ? best : null;
}

function batchBarangaySuggestion(rawBarangay) {
    const values = Array.from(new Set(Array.from(buildBatchBarangayLookup().values())));
    return nearestSuggestion(rawBarangay, values);
}

function batchEmailSuggestion(rawEmail) {
    const email = String(rawEmail || '').trim().toLowerCase();
    if (!email || !email.includes('@')) return null;
    const [localPart, domain] = email.split('@');
    if (!localPart || !domain || domain === 'gmail.com') return null;
    const suggestionDomain = nearestSuggestion(domain, ['gmail.com']);
    return suggestionDomain ? `${localPart}@${suggestionDomain}` : null;
}

function normalizeBatchPosition(value, role) {
    const normalized = String(value || '').trim().toLowerCase().replace(/^(sk\s+|sangguniang kabataan\s+)/i, '');
    const aliases = {
        chairperson: 'Chairperson',
        chairman: 'Chairperson',
        secretary: 'Secretary',
        treasurer: 'Treasurer',
        kagawad: 'Kagawad',
        'sk chairperson': 'Chairperson',
        'sk secretary': 'Secretary',
        'sk treasurer': 'Treasurer',
        'sk kagawad': 'Kagawad',
        councilor: 'Councilor',
        auditor: 'Auditor',
        pio: 'PIO',
        president: 'President',
        'vice president': 'Vice President',
        'sergeant at arms': 'Sergeant at Arms',
    };

    let position = aliases[normalized] || null;
    const allowed = role === 'sk_official' ? BATCH_SK_OFFICIAL_POSITIONS : BATCH_SK_FED_POSITIONS;

    if (!position && normalized) {
        position = allowed.find((candidate) => candidate.toLowerCase() === normalized) || null;
    }

    if (!position || !allowed.includes(position)) {
        return null;
    }

    return position;
}

function normalizeBatchSuffix(rawSuffix) {
    if (rawSuffix === null || rawSuffix === undefined || String(rawSuffix).trim() === '') {
        return { suffix_input: '', suffix: null };
    }

    const suffixInput = String(rawSuffix).trim();
    const lower = suffixInput.toLowerCase();
    if (['none', 'n/a', 'na', '-'].includes(lower)) {
        return { suffix_input: suffixInput, suffix: null };
    }

    const base = lower.replace(/\.$/, '');
    const mapped = {
        jr: 'Jr.',
        sr: 'Sr.',
        ii: 'II',
        iii: 'III',
        iv: 'IV',
        v: 'V',
    }[base];

    if (mapped) {
        return { suffix_input: suffixInput, suffix: mapped };
    }

    if (suffixInput.length <= SK_OFFICIAL_SUFFIX_OTHER_MAX) {
        return { suffix_input: suffixInput, suffix: suffixInput };
    }

    return { suffix_input: suffixInput, suffix: null };
}

const BATCH_ALL_REQUIRED_FIELDS = [
    { key: 'first_name', label: 'First Name' },
    { key: 'last_name', label: 'Last Name' },
    { key: 'sex', label: 'Sex' },
    { key: 'date_of_birth', label: 'Birthdate' },
    { key: 'age', label: 'Age' },
    { key: 'contact_number', label: 'Contact Number' },
    { key: 'position', label: 'Position' },
    { key: 'region', label: 'Region' },
    { key: 'province', label: 'Province' },
    { key: 'municipality', label: 'Municipality' },
    { key: 'barangay', label: 'Barangay' },
    { key: 'term_start', label: 'Term Start Date' },
    { key: 'term_end', label: 'Term End Date' },
    { key: 'email', label: 'Email Address' },
];

const BATCH_REQUIRED_ROW_FIELDS = BATCH_ALL_REQUIRED_FIELDS;

function batchExcelSerialToDateString(serial) {
    const utcDays = Math.floor(Number(serial) - 25569);
    const date = new Date(utcDays * 86400 * 1000);
    if (Number.isNaN(date.getTime())) return '';
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function jsDateToIsoDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    if (date.getUTCHours() === 0 && date.getUTCMinutes() === 0 && date.getUTCSeconds() === 0) {
        const year = date.getUTCFullYear();
        const month = String(date.getUTCMonth() + 1).padStart(2, '0');
        const day = String(date.getUTCDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    return formatLocalDate(date);
}

function unwrapExcelCellValue(value) {
    if (value === null || value === undefined) return '';
    if (value instanceof Date) return value;
    if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'string') {
        return value;
    }
    if (typeof value === 'object') {
        if (typeof value.w === 'string' && value.w.trim() !== '') return value.w;
        if (value.v !== undefined && value.v !== null && value.v !== '') return value.v;
        if (typeof value.text === 'string') return value.text;
        if (typeof value.hyperlink === 'string') return value.hyperlink;
        if (Array.isArray(value.r)) {
            return value.r.map((part) => part.t || '').join('');
        }
    }
    return value;
}

function isEmptyExcelCell(value) {
    const unwrapped = unwrapExcelCellValue(value);
    if (unwrapped instanceof Date) return Number.isNaN(unwrapped.getTime());
    if (typeof unwrapped === 'number') return !Number.isFinite(unwrapped);
    return String(unwrapped ?? '').trim() === '';
}

function parseBatchUsDateString(value) {
    const unwrapped = unwrapExcelCellValue(value);
    if (unwrapped === null || unwrapped === undefined || unwrapped === '') return '';
    if (unwrapped instanceof Date) return jsDateToIsoDate(unwrapped);
    if (typeof unwrapped === 'number' && Number.isFinite(unwrapped) && unwrapped >= 1 && unwrapped < 1000000) {
        return batchExcelSerialToDateString(unwrapped);
    }

    const raw = String(unwrapped).trim();
    if (!raw || /^m{1,2}\/d{1,2}\/y{2,4}$/i.test(raw)) return '';

    const isoMatch = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
    if (isoMatch) return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;

    const slashMatch = /^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/.exec(raw);
    if (slashMatch) {
        let year = slashMatch[3];
        if (year.length === 2) {
            year = Number(year) >= 70 ? `19${year}` : `20${year}`;
        }
        return `${year}-${slashMatch[1].padStart(2, '0')}-${slashMatch[2].padStart(2, '0')}`;
    }

    const dashMatch = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec(raw);
    if (dashMatch) {
        return `${dashMatch[3]}-${dashMatch[1].padStart(2, '0')}-${dashMatch[2].padStart(2, '0')}`;
    }

    const parsed = new Date(raw);
    if (!Number.isNaN(parsed.getTime())) return jsDateToIsoDate(parsed);

    return '';
}

function formatIsoDateToUs(isoDate) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(isoDate || '').trim());
    if (!match) return '';
    return `${match[2]}/${match[3]}/${match[1]}`;
}

function normalizeBatchCellValue(fieldKey, value) {
    const unwrapped = unwrapExcelCellValue(value);
    if (unwrapped === null || unwrapped === undefined || unwrapped === '') return '';

    if (BATCH_DATE_FIELD_KEYS.has(fieldKey)) {
        return parseBatchUsDateString(unwrapped);
    }

    if (fieldKey === 'age') {
        if (typeof unwrapped === 'number' && Number.isFinite(unwrapped)) {
            return String(Math.round(unwrapped));
        }
        return String(unwrapped).trim();
    }

    if (fieldKey === 'email') {
        return String(unwrapped).trim();
    }

    if (unwrapped instanceof Date) {
        return jsDateToIsoDate(unwrapped);
    }

    if (typeof unwrapped === 'number' && Number.isFinite(unwrapped)) {
        return String(unwrapped);
    }

    return String(unwrapped).trim();
}

function normalizeBatchAccountRow(row, role) {
    const normalized = {};
    Object.keys(row || {}).forEach((key) => {
        normalized[key] = normalizeBatchCellValue(key, row[key]);
    });

    const upperFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'position', 'barangay'];
    upperFields.forEach((field) => {
        if (normalized[field]) {
            normalized[field] = String(normalized[field]).trim().toUpperCase();
        }
    });

    const suffixResult = normalizeBatchSuffix(row.suffix ?? row.suffix_input ?? normalized.suffix ?? '');
    normalized.suffix_input = suffixResult.suffix_input;
    if (suffixResult.suffix) {
        normalized.suffix = suffixResult.suffix.toUpperCase();
    } else {
        delete normalized.suffix;
    }

    if (normalized.email) {
        normalized.email = String(normalized.email).trim().toLowerCase();
    }

    if (role === 'sk_official') {
        if (normalized.first_name) {
            normalized.first_name = normalized.first_name.replace(/^\s+/, '').replace(/\s{2,}/g, ' ');
        }
        if (normalized.last_name) {
            normalized.last_name = normalized.last_name.replace(/\s+/g, '');
        }
        if (normalized.middle_name) {
            normalized.middle_name = normalized.middle_name.replace(/\s+/g, '');
            if (normalized.middle_name === '') {
                delete normalized.middle_name;
            }
        }
    }

    if (normalized.contact_number) {
        let digits = String(normalized.contact_number).replace(/\D+/g, '');
        if (!digits.startsWith('09')) {
            digits = '09' + digits.replace(/^0+/, '');
        }
        normalized.contact_number = digits.slice(0, 11);
    }

    if (normalized.sex) {
        const sex = String(normalized.sex).trim();
        normalized.sex = ['Male', 'Female'].includes(sex) ? sex : sex;
    }

    if (normalized.position) {
        const resolvedPosition = normalizeBatchPosition(normalized.position, role);
        normalized.position = resolvedPosition || normalized.position;
    }

    if (normalized.barangay) {
        const resolvedBarangay = resolveBatchBarangayName(normalized.barangay);
        if (resolvedBarangay) {
            normalized.barangay = resolvedBarangay.toUpperCase();
        }
    }

    return normalized;
}

function getBatchNameMax(role) {
    return role === 'sk_official' ? SK_OFFICIAL_NAME_MAX : BATCH_SK_FED_NAME_MAX;
}

function pushBatchLengthError(errors, rowNumber, field, label, value, rules) {
    const text = String(value ?? '').trim();
    const length = text.length;

    if (rules.optional && length === 0) {
        return;
    }

    if (rules.min !== undefined && length > 0 && length < rules.min) {
        errors.push({
            row: rowNumber,
            field,
            error: `${label} must be at least ${rules.min} character${rules.min === 1 ? '' : 's'}.`,
        });
        return;
    }

    if (rules.max !== undefined && length > rules.max) {
        errors.push({
            row: rowNumber,
            field,
            error: `${label} must not exceed ${rules.max} characters.`,
        });
    }
}

function validateBatchFieldLengths(row, rowNumber, role, errors) {
    const nameMax = getBatchNameMax(role);
    const isOfficial = role === 'sk_official';
    const rawSuffix = row.suffix ?? row.suffix_input ?? '';

    pushBatchLengthError(errors, rowNumber, 'first_name', 'First name', row.first_name, { min: SK_OFFICIAL_NAME_MIN, max: nameMax });
    pushBatchLengthError(errors, rowNumber, 'middle_name', 'Middle name', row.middle_name, { min: SK_OFFICIAL_NAME_MIN, max: nameMax, optional: true });
    pushBatchLengthError(errors, rowNumber, 'last_name', 'Last name', row.last_name, { min: SK_OFFICIAL_NAME_MIN, max: nameMax });

    if (isOfficial) {
        pushBatchLengthError(errors, rowNumber, 'suffix', 'Suffix', rawSuffix, { min: 1, max: SK_OFFICIAL_SUFFIX_OTHER_MAX });
    } else {
        pushBatchLengthError(errors, rowNumber, 'suffix', 'Suffix', rawSuffix, { max: SK_OFFICIAL_SUFFIX_OTHER_MAX, optional: true });
    }

    pushBatchLengthError(errors, rowNumber, 'email', 'Email', row.email, { max: BATCH_EMAIL_MAX });
    pushBatchLengthError(errors, rowNumber, 'position', 'Position', row.position, { min: 1, max: BATCH_POSITION_MAX });
    pushBatchLengthError(errors, rowNumber, 'region', 'Region', row.region, { min: 1, max: BATCH_LOCATION_MAX });
    pushBatchLengthError(errors, rowNumber, 'province', 'Province', row.province, { min: 1, max: BATCH_LOCATION_MAX });
    pushBatchLengthError(errors, rowNumber, 'municipality', 'Municipality', row.municipality, { min: 1, max: BATCH_LOCATION_MAX });
    pushBatchLengthError(errors, rowNumber, 'barangay', 'Barangay', row.barangay, { min: 1, max: BATCH_LOCATION_MAX });

    const rawContact = String(row.contact_number ?? '').replace(/\D+/g, '');
    if (rawContact.length > 0 && rawContact.length !== BATCH_CONTACT_NUMBER_LENGTH) {
        errors.push({
            row: rowNumber,
            field: 'contact_number',
            error: `Contact number must be exactly ${BATCH_CONTACT_NUMBER_LENGTH} digits.`,
        });
    }

    if (row.age !== undefined && row.age !== '') {
        const ageText = String(row.age).trim();
        if (!/^\d{1,3}$/.test(ageText)) {
            errors.push({ row: rowNumber, field: 'age', error: 'Age must be a valid number.' });
        }
    }
}

function batchRowFingerprint(row) {
    const keys = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'date_of_birth', 'age',
        'contact_number', 'email', 'position', 'region', 'province', 'municipality', 'barangay',
        'term_start', 'term_end',
    ];
    return keys.map((key) => String(row[key] ?? '').trim().toLowerCase()).join('|');
}

function validateBatchAccountRow(row, rowNumber, role, seenEmails, seenFingerprints) {
    const errors = [];
    const isOfficial = role === 'sk_official';
    const rawSuffix = row.suffix ?? row.suffix_input ?? '';
    const rawBarangay = row.barangay ?? '';
    const rawPosition = row.position ?? '';
    const nameMax = getBatchNameMax(role);

    validateBatchFieldLengths(row, rowNumber, role, errors);
    const data = normalizeBatchAccountRow(row, role);

    BATCH_ALL_REQUIRED_FIELDS.forEach(({ key, label }) => {
        const value = data[key];
        if (value === null || value === undefined || String(value).trim() === '') {
            errors.push({ row: rowNumber, field: key, error: `${label} is required.` });
        }
    });

    if (isOfficial && String(rawSuffix).trim() === '') {
        errors.push({ row: rowNumber, field: 'suffix', error: 'Suffix is required.' });
    }

    if (isOfficial && String(rawSuffix).trim() !== '') {
        const suffixNorm = normalizeBatchSuffix(rawSuffix);
        const suffixLower = String(rawSuffix).trim().toLowerCase();
        if (
            suffixNorm.suffix === null
            && !['none', 'n/a', 'na', '-'].includes(suffixLower)
            && String(rawSuffix).trim().length > 10
        ) {
            errors.push({ row: rowNumber, field: 'suffix', error: 'Suffix must not exceed 4 characters.' });
        }
    }

    if (String(rawPosition).trim() !== '' && normalizeBatchPosition(rawPosition, role) === null) {
        errors.push({ row: rowNumber, field: 'position', error: 'Position is required or not recognized for this account type.' });
    }

    if (String(rawBarangay).trim() !== '' && resolveBatchBarangayName(rawBarangay) === null) {
        const suggestion = batchBarangaySuggestion(rawBarangay);
        errors.push({
            row: rowNumber,
            field: 'barangay',
            error: suggestion
                ? `Barangay "${String(rawBarangay).trim()}" was not found. Did you mean "${suggestion}"?`
                : `Barangay "${String(rawBarangay).trim()}" was not found.`,
        });
    }

    if (data.first_name) {
        if (data.first_name.length < SK_OFFICIAL_NAME_MIN) {
            errors.push({ row: rowNumber, field: 'first_name', error: 'First name must be at least 3 characters.' });
        } else if (data.first_name.length > nameMax) {
            errors.push({ row: rowNumber, field: 'first_name', error: `First name must not exceed ${nameMax} characters.` });
        } else if (isOfficial && !SK_OFFICIAL_FIRST_NAME_REGEX.test(data.first_name)) {
            errors.push({ row: rowNumber, field: 'first_name', error: 'First name must use uppercase letters only, with at most one space and no leading spaces.' });
        }
    }

    if (data.middle_name) {
        if (data.middle_name.length < SK_OFFICIAL_NAME_MIN) {
            errors.push({ row: rowNumber, field: 'middle_name', error: 'Middle name must be at least 3 characters when provided.' });
        } else if (data.middle_name.length > nameMax) {
            errors.push({ row: rowNumber, field: 'middle_name', error: `Middle name must not exceed ${nameMax} characters.` });
        } else if (isOfficial && !BATCH_OFFICIAL_MIDDLE_NAME_REGEX.test(data.middle_name)) {
            errors.push({ row: rowNumber, field: 'middle_name', error: 'Middle name must use uppercase letters only, with no spaces.' });
        }
    }

    if (data.last_name) {
        if (data.last_name.length < SK_OFFICIAL_NAME_MIN) {
            errors.push({ row: rowNumber, field: 'last_name', error: 'Last name must be at least 3 characters.' });
        } else if (data.last_name.length > nameMax) {
            errors.push({ row: rowNumber, field: 'last_name', error: `Last name must not exceed ${nameMax} characters.` });
        } else if (isOfficial && !BATCH_OFFICIAL_LAST_NAME_REGEX.test(data.last_name)) {
            errors.push({ row: rowNumber, field: 'last_name', error: 'Last name must use uppercase letters only, with no spaces.' });
        }
    }

    if (data.email) {
        if (String(data.email).length > BATCH_EMAIL_MAX) {
            errors.push({ row: rowNumber, field: 'email', error: `Email must not exceed ${BATCH_EMAIL_MAX} characters.` });
        } else if (isOfficial && !SK_OFFICIAL_GMAIL_REGEX.test(String(data.email).trim())) {
            const suggestion = batchEmailSuggestion(data.email);
            errors.push({
                row: rowNumber,
                field: 'email',
                error: suggestion
                    ? `Email must be a @gmail.com address with 6–30 characters before @. Suggested: ${suggestion}`
                    : 'Email must be a @gmail.com address with 6–30 characters before @.',
            });
        } else if (!isOfficial && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(data.email).trim())) {
            errors.push({ row: rowNumber, field: 'email', error: 'Invalid email format.' });
        }
        if (seenEmails.has(data.email)) {
            errors.push({ row: rowNumber, field: 'email', error: 'Duplicate email in upload file.' });
        } else {
            seenEmails.add(data.email);
        }
    }

    const fingerprint = batchRowFingerprint(data);
    if (fingerprint.replace(/\|/g, '') !== '') {
        if (seenFingerprints.has(fingerprint)) {
            errors.push({ row: rowNumber, error: 'Duplicate row: another entry has the same details.' });
        } else {
            seenFingerprints.add(fingerprint);
        }
    }

    if (data.contact_number && !/^09\d{9}$/.test(data.contact_number)) {
        errors.push({ row: rowNumber, field: 'contact_number', error: 'Contact number must be 11 digits starting with 09.' });
    }

    if (data.sex && !['Male', 'Female'].includes(data.sex)) {
        errors.push({ row: rowNumber, field: 'sex', error: 'Sex must be exactly Male or Female. Suggested: Male or Female.' });
    }

    if (String(row.sex ?? '').trim() !== '' && !['Male', 'Female'].includes(String(row.sex).trim())) {
        errors.push({ row: rowNumber, field: 'sex', error: 'Sex must be exactly Male or Female. Suggested: Male or Female.' });
    }

    if (data.date_of_birth) {
        const today = formatLocalDate(new Date());
        if (data.date_of_birth >= today) {
            errors.push({ row: rowNumber, field: 'date_of_birth', error: 'Birthdate must be before today.' });
        }
    }

    if (data.age) {
        const ageNum = parseInt(data.age, 10);
        if (Number.isNaN(ageNum) || ageNum < SK_OFFICIAL_AGE_MIN || ageNum > SK_OFFICIAL_AGE_MAX) {
            errors.push({ row: rowNumber, field: 'age', error: `Age must be between ${SK_OFFICIAL_AGE_MIN} and ${SK_OFFICIAL_AGE_MAX}.` });
        }
    }

    if (String(data.region || '').trim() !== 'IV-A CALABARZON') {
        errors.push({ row: rowNumber, field: 'region', error: 'Region must be IV-A CALABARZON. Suggested: IV-A CALABARZON.' });
    }
    if (String(data.province || '').trim() !== 'Laguna') {
        errors.push({ row: rowNumber, field: 'province', error: 'Province must be Laguna. Suggested: Laguna.' });
    }
    if (String(data.municipality || '').trim() !== 'Santa Cruz') {
        errors.push({ row: rowNumber, field: 'municipality', error: 'Municipality must be Santa Cruz. Suggested: Santa Cruz.' });
    }

    if (data.term_start) {
        const termStartMin = isOfficial ? SK_OFFICIAL_FIRST_TERM_START : getCurrentYearStartDate();
        const termStartMax = isOfficial ? formatLocalDate(new Date()) : getCurrentYearEndDate();
        if (data.term_start < termStartMin || data.term_start > termStartMax || (isOfficial && !isValidSkOfficialTermStart(data.term_start))) {
            errors.push({
                row: rowNumber,
                field: 'term_start',
                error: isOfficial
                    ? SK_OFFICIAL_TERM_START_MESSAGE
                    : 'Term start date must be within the current year.',
            });
        }
    }

    if (data.term_start && data.term_end) {
        if (isOfficial) {
            const minEnd = skOfficialTermEndForStart(data.term_start);
            const maxEnd = addYearsToDateString(data.term_start, SK_OFFICIAL_TERM_MAX_YEARS);
            if (data.term_end < minEnd || data.term_end > maxEnd) {
                errors.push({ row: rowNumber, field: 'term_end', error: SK_OFFICIAL_TERM_END_MESSAGE });
            }
        } else {
            const requiredEnd = addYearsToDateString(data.term_start, ACCOUNT_TERM_YEARS);
            if (data.term_end !== requiredEnd) {
                errors.push({ row: rowNumber, field: 'term_end', error: `Term end must be exactly ${ACCOUNT_TERM_YEARS} years after term start.` });
            }
        }
    }

    return { errors, data };
}

function validateMappedRows(rows, role) {
    const errors = [];
    const seenEmails = new Set();
    const seenFingerprints = new Set();

    rows.forEach((row, index) => {
        const result = validateBatchAccountRow(row, index + 1, role, seenEmails, seenFingerprints);
        errors.push(...result.errors);
        Object.assign(row, result.data);
    });

    return errors;
}

const _batchPanelState = {};

window.resetBatchUploadPanel = function (prefix) {
    const panel = _batchPanelState[prefix];
    if (!panel) return;
    const { els, state, renderBatchErrorReport } = panel;
    state.parsedRows = [];
    state.parsedHeaders = [];
    state.mappedAccounts = [];
    state.validationErrors = [];
    if (els.fileInput) els.fileInput.value = '';
    if (els.fileLabel) els.fileLabel.textContent = 'Supported: .xlsx, .xls';
    if (els.preview) { els.preview.innerHTML = ''; els.preview.style.display = 'none'; }
    renderBatchErrorReport([]);
    if (els.confirmBtn) els.confirmBtn.disabled = true;
    if (els.fileInput) els.fileInput.disabled = false;
    if (els.dropzone) els.dropzone.classList.remove('batch-dropzone-disabled');
    const limitNote = document.getElementById(prefix + '_batchLimitNote');
    if (limitNote) limitNote.hidden = true;
};

function initBatchUploadPanel(prefix, role) {
    const els = {
        fileInput: document.getElementById(`${prefix}_batchFileInput`),
        dropzone: document.getElementById(`${prefix}_batchDropzone`),
        fileLabel: document.getElementById(`${prefix}_batchFileName`),
        preview: document.getElementById(`${prefix}_batchPreview`),
        errorReport: document.getElementById(`${prefix}_batchErrorReport`),
        confirmBtn: document.getElementById(`${prefix}_batchConfirmBtn`),
        templateLink: document.getElementById(`${prefix}_batchTemplateDownloadLink`),
        limitNote: document.getElementById(`${prefix}_batchLimitNote`),
    };

    if (!els.fileInput && !els.dropzone) return;

    const state = {
        parsedRows: [],
        parsedHeaders: [],
        mappedAccounts: [],
        validationErrors: [],
        editDebounceTimer: null,
    };

    const templateType = role === 'sk_fed' ? 'federation' : 'officials';
    const closeAddModal = prefix === 'official' ? closeAddSkOfficialsModal : closeAddAccountModal;

    function getRequiredHeaderGroups() {
        return BATCH_REQUIRED_HEADERS.map((label) => {
            const normalized = normalizeHeaderKey(label);
            const fieldKey = resolveBatchFieldKey(label);

            return {
                label,
                keys: [...new Set([normalized, fieldKey])],
            };
        });
    }

    function normalizeHeaderKey(header) {
        return normalizeBatchHeaderLabel(header);
    }

    function resolveBatchFieldKey(header) {
        const normalized = normalizeHeaderKey(header);
        if (BATCH_HEADER_ALIASES[normalized]) {
            return BATCH_HEADER_ALIASES[normalized];
        }
        if (BATCH_HEADER_ALIASES[header.trim().toLowerCase()]) {
            return BATCH_HEADER_ALIASES[header.trim().toLowerCase()];
        }
        return normalized.replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }

    function isTemplateSampleDataRow(row) {
        const width = state.parsedHeaders.length || BATCH_TEMPLATE_HEADERS.length;
        const padded = Array.from({ length: width }, (_, index) => {
            const fieldKey = resolveBatchFieldKey((state.parsedHeaders.length ? state.parsedHeaders : BATCH_TEMPLATE_HEADERS)[index] || '');
            return normalizeBatchCellValue(fieldKey, row[index]);
        });
        const mapped = mapRowToAccount(state.parsedHeaders.length ? state.parsedHeaders : BATCH_TEMPLATE_HEADERS, padded);
        const hasOfficialData = Boolean(mapped.first_name || mapped.last_name || mapped.email || mapped.contact_number);
        if (hasOfficialData) {
            return false;
        }

        return mapped.region === 'IV-A CALABARZON'
            && mapped.province === 'LAGUNA'
            && mapped.municipality === 'SANTA CRUZ';
    }

    function getBatchRowErrors(errors, rowNumber) {
        return (errors || []).filter((item) => Number(item.row) === rowNumber);
    }

    function getBatchInvalidFields(errors, rowNumber) {
        return new Set(getBatchRowErrors(errors, rowNumber).map((item) => item.field).filter(Boolean));
    }

    function ensureParsedRowWidth(row) {
        const width = state.parsedHeaders.length;
        return Array.from({ length: width }, (_, index) => {
            const fieldKey = resolveBatchFieldKey(state.parsedHeaders[index] || '');
            return normalizeBatchCellValue(fieldKey, row[index]);
        });
    }

    function syncMappedAccountsFromParsedRows() {
        state.mappedAccounts = state.parsedRows.map((row) => mapRowToAccount(state.parsedHeaders, row));
    }

    function revalidateBatchState() {
        syncMappedAccountsFromParsedRows();
        const missingHeaders = getMissingBatchHeaders(state.parsedHeaders);
        const rowErrors = missingHeaders.length === 0 ? validateMappedRows(state.mappedAccounts, role) : [];
        const hasHeaderErrors = missingHeaders.length > 0;
        const hasRowErrors = rowErrors.length > 0;

        let previewMessage = state.parsedRows.length + ' row' + (state.parsedRows.length !== 1 ? 's' : '') + ' ready for review';
        if (missingHeaders.length > 0) {
            previewMessage = 'Missing required columns: ' + missingHeaders.join(', ') + '.';
        } else if (hasRowErrors) {
            previewMessage = rowErrors.length + ' validation error' + (rowErrors.length !== 1 ? 's' : '') + ' found. Edit the highlighted cells below, then import again.';
            renderBatchErrorReport(rowErrors);
            state.validationErrors = rowErrors;
        } else {
            renderBatchErrorReport([]);
            state.validationErrors = [];
        }

        renderBatchEditablePreview(state.parsedHeaders, state.parsedRows, previewMessage, {
            hasErrors: hasHeaderErrors || hasRowErrors,
            errors: rowErrors,
        });

        if (els.confirmBtn) {
            els.confirmBtn.disabled = hasHeaderErrors || state.parsedRows.length === 0;
        }

        return rowErrors;
    }

    function syncBatchAgeFromBirthdate(rowIndex) {
        const birthCol = state.parsedHeaders.findIndex((header) => resolveBatchFieldKey(header) === 'date_of_birth');
        const ageCol = state.parsedHeaders.findIndex((header) => resolveBatchFieldKey(header) === 'age');
        if (birthCol < 0 || ageCol < 0 || !state.parsedRows[rowIndex]) {
            return;
        }

        state.parsedRows[rowIndex] = ensureParsedRowWidth(state.parsedRows[rowIndex]);
        const birthValue = state.parsedRows[rowIndex][birthCol];
        const normalizedBirth = normalizeBatchCellValue('date_of_birth', birthValue);
        const age = normalizedBirth ? calculateAge(normalizedBirth) : '';
        state.parsedRows[rowIndex][ageCol] = age !== null && age !== undefined ? String(age) : '';
    }

    function handleBatchCellEdit(rowIndex, colIndex, value) {
        if (!state.parsedRows[rowIndex]) {
            return;
        }

        state.parsedRows[rowIndex] = ensureParsedRowWidth(state.parsedRows[rowIndex]);
        const fieldKey = resolveBatchFieldKey(state.parsedHeaders[colIndex] || '');
        state.parsedRows[rowIndex][colIndex] = normalizeBatchCellValue(fieldKey, value);
        if (fieldKey === 'date_of_birth') {
            syncBatchAgeFromBirthdate(rowIndex);
        }

        window.clearTimeout(state.editDebounceTimer);
        state.editDebounceTimer = window.setTimeout(() => {
            revalidateBatchState();
        }, 250);
    }

    function updateBatchUploadLimitState(rowCount) {
        const overLimit = rowCount > BATCH_MAX_ACCOUNTS;
        const atLimit = rowCount === BATCH_MAX_ACCOUNTS;
        if (els.fileInput) els.fileInput.disabled = overLimit;
        if (els.dropzone) {
            els.dropzone.classList.toggle('batch-dropzone-disabled', overLimit);
        }
        if (els.limitNote) {
            els.limitNote.hidden = !atLimit && !overLimit;
            if (overLimit) {
                els.limitNote.textContent = `This file has ${rowCount} rows. Maximum upload limit is ${BATCH_MAX_ACCOUNTS} accounts per file.`;
            } else if (atLimit) {
                els.limitNote.textContent = `Maximum upload limit reached (${BATCH_MAX_ACCOUNTS} accounts per file).`;
            }
        }
    }

    function getMissingBatchHeaders(headers) {
        const normalizedHeaders = headers.map(normalizeHeaderKey);
        return getRequiredHeaderGroups()
            .filter((group) => !group.keys.some((key) => normalizedHeaders.includes(key)))
            .map((group) => group.label);
    }

    function mapRowToAccount(headers, row) {
        const mapped = {};
        headers.forEach((header, index) => {
            const key = resolveBatchFieldKey(header);
            if (!key) return;
            mapped[key] = normalizeBatchCellValue(key, row[index]);
        });
        return normalizeBatchAccountRow(mapped, role);
    }

    function formatBatchCell(value) {
        if (value === null || value === undefined || value === '') return '&mdash;';
        if (value instanceof Date) return escapeHtml(value.toLocaleDateString('en-CA'));
        return escapeHtml(String(value).trim());
    }

    function renderBatchErrorReport(errors) {
        if (!els.errorReport) return;
        if (!errors || errors.length === 0) {
            els.errorReport.style.display = 'none';
            els.errorReport.innerHTML = '';
            return;
        }

        const rows = errors.map((item) =>
            '<tr><td>' + escapeHtml(String(item.row)) + '</td><td>' + escapeHtml(item.error || item.message || 'Validation failed') + '</td></tr>'
        ).join('');

        els.errorReport.innerHTML =
            '<p class="batch-row-count batch-row-count-error">Validation errors found. Fix the highlighted cells in the table below, then click Import Accounts.</p>' +
            '<div class="batch-preview-wrap"><table class="batch-preview-table batch-error-table">' +
            '<thead><tr><th>Row</th><th>Error</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
        els.errorReport.style.display = '';
        state.validationErrors = errors;
    }

    function renderBatchEditablePreview(headers, rows, message, options = {}) {
        if (!els.preview) return;

        const hasErrors = Boolean(options.hasErrors);
        const errors = options.errors || [];
        const activeInput = document.activeElement && document.activeElement.classList
            && document.activeElement.classList.contains('batch-cell-input')
            ? {
                rowIndex: Number(document.activeElement.dataset.rowIndex),
                colIndex: Number(document.activeElement.dataset.colIndex),
                start: document.activeElement.selectionStart,
                end: document.activeElement.selectionEnd,
            }
            : null;

        if (rows.length === 0) {
            els.preview.innerHTML = '<p class="batch-row-count" style="color:#94a3b8;">' + escapeHtml(message || 'Upload an Excel file to preview rows.') + '</p>';
            els.preview.style.display = '';
            if (els.confirmBtn) els.confirmBtn.disabled = true;
            return;
        }

        const theadCells = headers.map((header) => {
            const fieldKey = resolveBatchFieldKey(header);
            return '<th class="' + batchPreviewColumnClass(fieldKey) + '">' + escapeHtml(header) + '</th>';
        }).join('') + '<th class="batch-col-status">Status</th>';
        const tbodyRows = rows.map((row, rowIndex) => {
            const rowNumber = rowIndex + 1;
            const rowErrors = getBatchRowErrors(errors, rowNumber);
            const invalidFields = getBatchInvalidFields(errors, rowNumber);
            const rowClass = rowErrors.length > 0 ? ' batch-row-has-error' : '';

            const cells = headers.map((header, colIndex) => {
                const fieldKey = resolveBatchFieldKey(header);
                const rawValue = row[colIndex] ?? '';
                const isoDate = fieldKey && BATCH_DATE_FIELD_KEYS.has(fieldKey)
                    ? parseBatchUsDateString(rawValue)
                    : '';
                const displayValue = isoDate
                    ? formatIsoDateToUs(isoDate)
                    : String(rawValue ?? '').trim();
                const invalidClass = invalidFields.has(fieldKey) ? ' batch-cell-input-invalid' : '';
                const placeholder = fieldKey && BATCH_DATE_FIELD_KEYS.has(fieldKey) ? 'MM/DD/YYYY' : '';
                const colClass = batchPreviewColumnClass(fieldKey);
                const inputSize = fieldKey === 'email' ? Math.max(28, displayValue.length + 2) : Math.max(12, Math.min(displayValue.length + 2, 24));

                return '<td class="' + colClass + '"><input type="text" class="batch-cell-input' + invalidClass + '" data-row-index="' + rowIndex + '" data-col-index="' + colIndex + '" data-field-key="' + escapeHtml(fieldKey || '') + '" value="' + escapeHtml(displayValue) + '" placeholder="' + escapeHtml(placeholder) + '" size="' + inputSize + '" title="' + escapeHtml(displayValue) + '" aria-label="' + escapeHtml(header) + ' row ' + rowNumber + '"></td>';
            }).join('');

            const statusText = rowErrors.length > 0
                ? escapeHtml(rowErrors[0].error || 'Fix this row')
                : '<span class="batch-row-status-ok">OK</span>';

            return '<tr class="batch-editable-row' + rowClass + '" data-row-number="' + rowNumber + '">' +
                cells +
                '<td class="batch-col-status">' + statusText + '</td>' +
                '</tr>';
        }).join('');

        const messageClass = hasErrors ? 'batch-row-count batch-row-count-error' : 'batch-row-count';
        els.preview.innerHTML =
            '<p class="' + messageClass + '">' + escapeHtml(message || '') + '</p>' +
            '<p class="batch-edit-hint">You can edit values directly in the table below. Changes are validated automatically.</p>' +
            '<div class="batch-preview-wrap batch-preview-wrap-editable"><table class="batch-preview-table batch-preview-table-editable"><thead><tr>' + theadCells + '</tr></thead><tbody>' + tbodyRows + '</tbody></table></div>';

        els.preview.style.display = '';

        els.preview.querySelectorAll('.batch-cell-input').forEach((input) => {
            input.addEventListener('input', function () {
                handleBatchCellEdit(Number(input.dataset.rowIndex), Number(input.dataset.colIndex), input.value);
            });
            input.addEventListener('change', function () {
                handleBatchCellEdit(Number(input.dataset.rowIndex), Number(input.dataset.colIndex), input.value);
            });
        });

        if (activeInput && Number.isFinite(activeInput.rowIndex) && Number.isFinite(activeInput.colIndex)) {
            const selector = `.batch-cell-input[data-row-index="${activeInput.rowIndex}"][data-col-index="${activeInput.colIndex}"]`;
            const replacementInput = els.preview.querySelector(selector);
            if (replacementInput) {
                replacementInput.focus();
                const maxLength = replacementInput.value.length;
                const start = Number.isFinite(activeInput.start) ? Math.min(activeInput.start, maxLength) : maxLength;
                const end = Number.isFinite(activeInput.end) ? Math.min(activeInput.end, maxLength) : start;
                if (typeof replacementInput.setSelectionRange === 'function') {
                    replacementInput.setSelectionRange(start, end);
                }
            }
        }
    }

    function renderBatchPreview(headers, rows, message, hasHeaderErrors = false) {
        renderBatchEditablePreview(headers, rows, message, {
            hasErrors: hasHeaderErrors,
            errors: state.validationErrors || [],
        });
    }

    function handleBatchFile(file) {
        if (!file) return;
        const XLSX = getXlsxLib();
        if (!XLSX) {
            alert('Batch upload library is still loading. Please try again in a moment.');
            return;
        }

        state.parsedHeaders = [];
        state.parsedRows = [];
        state.mappedAccounts = [];
        state.validationErrors = [];
        renderBatchErrorReport([]);
        if (els.preview) {
            els.preview.innerHTML = '';
            els.preview.style.display = 'none';
        }
        if (els.confirmBtn) els.confirmBtn.disabled = true;

        if (els.fileLabel) els.fileLabel.textContent = file.name;

        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array', raw: true, cellDates: true });
                const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                const allRows = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: true, defval: '', blankrows: false });
                const nonEmptyRows = allRows.filter((row) => Array.isArray(row) && row.some((cell) => !isEmptyExcelCell(cell)));

                if (nonEmptyRows.length < 2) {
                    state.parsedHeaders = [];
                    state.parsedRows = [];
                    state.mappedAccounts = [];
                    renderBatchPreview([], [], 'No data rows found in the uploaded file.');
                    return;
                }

                state.parsedHeaders = nonEmptyRows[0].map((header) => String(header).trim());
                state.parsedRows = nonEmptyRows.slice(1)
                    .filter((row) => Array.isArray(row) && row.some((cell) => !isEmptyExcelCell(cell)))
                    .map((row) => ensureParsedRowWidth(row))
                    .filter((row) => !isTemplateSampleDataRow(row));
                updateBatchUploadLimitState(state.parsedRows.length);

                if (state.parsedRows.length > BATCH_MAX_ACCOUNTS) {
                    state.mappedAccounts = [];
                    renderBatchErrorReport([{
                        row: 0,
                        error: `Maximum upload limit is ${BATCH_MAX_ACCOUNTS} accounts per file. This file has ${state.parsedRows.length} rows.`,
                    }]);
                    renderBatchEditablePreview(state.parsedHeaders, state.parsedRows.slice(0, 5), `Too many rows (${state.parsedRows.length}). Maximum is ${BATCH_MAX_ACCOUNTS} accounts per upload.`, { hasErrors: true, errors: [] });
                    if (els.confirmBtn) els.confirmBtn.disabled = true;
                    return;
                }

                revalidateBatchState();
                return;
            } catch (err) {
                console.error('Batch upload read error:', err);
                state.parsedHeaders = [];
                state.parsedRows = [];
                state.mappedAccounts = [];
                renderBatchPreview([], [], 'Unable to read the Excel file. Please upload a valid .xlsx or .xls file.');
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function downloadBatchTemplateXlsx(event) {
        if (event) event.preventDefault();
        const XLSX = getXlsxLib();
        if (XLSX) {
            const worksheet = XLSX.utils.aoa_to_sheet([BATCH_TEMPLATE_HEADERS, BATCH_TEMPLATE_SAMPLE_ROW]);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Template');
            const filename = role === 'sk_fed' ? 'sk-federation-batch-template.xlsx' : 'sk-officials-batch-template.xlsx';
            XLSX.writeFile(workbook, filename);
            return;
        }

        window.location.href = '/accounts/batch-template/' + templateType;
    }

    _batchPanelState[prefix] = { els, state, renderBatchErrorReport, revalidateBatchState };

    if (els.confirmBtn) {
        els.confirmBtn.addEventListener('click', async function () {
            if (state.parsedRows.length === 0) return;
            const missingHeaders = getMissingBatchHeaders(state.parsedHeaders);
            if (missingHeaders.length > 0) {
                alert('Your Excel file is missing required columns:\n\n' + missingHeaders.join('\n'));
                return;
            }

            const rowErrors = revalidateBatchState();
            if (rowErrors.length > 0) {
                const preview = rowErrors
                    .slice(0, 3)
                    .map((item) => `Row ${item.row || '?'}: ${item.error || 'Validation error.'}`)
                    .join('\n');
                alert(`Please fix the validation errors in the table before importing.\n\n${preview}`);
                return;
            }

            showLoadingOverlay('Uploading accounts', 'Please wait while we import your file...');
            els.confirmBtn.disabled = true;

            try {
                const response = await fetch('/accounts/batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        role,
                        headers: state.parsedHeaders,
                        accounts: state.mappedAccounts,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.success) {
                    hideLoadingOverlay();
                    const errors = data.validation_errors || data.failed || [];
                    if (errors.length > 0) {
                        state.validationErrors = errors.map((item) => ({
                            row: item.row,
                            error: item.error || item.message,
                        }));
                        renderBatchErrorReport(state.validationErrors);
                        revalidateBatchState();
                    }
                    alert(data.message || 'Batch account creation failed.');
                    els.confirmBtn.disabled = false;
                    return;
                }

                hideLoadingOverlay();
                if (Array.isArray(data.failed) && data.failed.length > 0) {
                    renderBatchErrorReport(data.failed.map((item) => ({
                        row: item.row,
                        error: item.message,
                    })));
                } else {
                    closeAddModal();
                }
                const toastType = Array.isArray(data.failed) && data.failed.length > 0 ? 'edit' : 'success';
                showAccountToast(data.message || 'Accounts created successfully.', toastType);
                window.setTimeout(() => window.location.reload(), 900);
            } catch (error) {
                hideLoadingOverlay();
                els.confirmBtn.disabled = false;
                alert('Unable to create accounts from the uploaded file. Please try again.');
            }
        });
    }

    if (els.templateLink) {
        els.templateLink.addEventListener('click', downloadBatchTemplateXlsx);
    }

    if (els.fileInput) {
        els.fileInput.addEventListener('click', function () {
            this.value = '';
        });
        els.fileInput.addEventListener('change', function () {
            const file = els.fileInput.files[0];
            if (file) handleBatchFile(file);
        });
    }

    if (els.dropzone) {
        els.dropzone.addEventListener('click', function () {
            if (els.fileInput && !els.fileInput.disabled) {
                els.fileInput.click();
            }
        });
        els.dropzone.style.cursor = 'pointer';
        els.dropzone.addEventListener('dragover', function (e) { e.preventDefault(); els.dropzone.classList.add('drag-over'); });
        els.dropzone.addEventListener('dragleave', function () { els.dropzone.classList.remove('drag-over'); });
        els.dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            els.dropzone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            if (els.fileInput) els.fileInput.files = dt.files;
            handleBatchFile(file);
        });
    }
}

// ── DOMContentLoaded: wire up everything ──────────────────────
document.addEventListener('DOMContentLoaded', function () {
    cleanupAccountUiState();
    window.AccountsDeleteModal?.wireEvents?.();

    // ── Pagination ────────────────────────────────────────────
    let recordsPerPage = 10;
    let currentPage = 1;
    let allAccounts = [];
    let filteredAccounts = [];

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const pageInput = document.getElementById('pageInput');
    const totalPagesEl = document.getElementById('totalPages');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    const paginationInfo = document.getElementById('paginationInfo');
    const tableBody = document.getElementById('accountsTableBody') || document.querySelector('.accounts-table tbody');

    function getPageCount() {
        if (filteredAccounts.length === 0) return 1;
        return Math.ceil(filteredAccounts.length / recordsPerPage);
    }

    function initPagination() {
        if (!tableBody) return;
        const rows = Array.from(tableBody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
        allAccounts = rows.map((el, i) => ({ element: el, index: i }));
        filteredAccounts = [...allAccounts];
        currentPage = 1;
        updatePagination();
    }

    function updatePagination() {
        if (!tableBody) return;
        const totalPages = getPageCount();
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * recordsPerPage;
        const end = Math.min(start + recordsPerPage, filteredAccounts.length);

        allAccounts.forEach(a => { a.element.style.display = 'none'; });
        for (let i = start; i < end; i++) {
            if (filteredAccounts[i]) filteredAccounts[i].element.style.display = '';
        }

        if (pageInput) {
            pageInput.value = String(currentPage);
            pageInput.min = '1';
            pageInput.max = String(totalPages);
        }
        if (totalPagesEl) totalPagesEl.textContent = String(totalPages);
        if (paginationInfo) {
            paginationInfo.textContent = `${filteredAccounts.length} record${filteredAccounts.length === 1 ? '' : 's'}`;
        }
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages || filteredAccounts.length === 0;
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage -= 1;
                updatePagination();
                syncPaginationSelection();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const totalPages = getPageCount();
            if (currentPage < totalPages) {
                currentPage += 1;
                updatePagination();
                syncPaginationSelection();
            }
        });
    }

    if (pageInput) {
        pageInput.addEventListener('change', () => {
            const totalPages = getPageCount();
            let page = parseInt(pageInput.value, 10);
            if (Number.isNaN(page) || page < 1) page = 1;
            if (page > totalPages) page = totalPages;
            currentPage = page;
            updatePagination();
            syncPaginationSelection();
        });
    }

    if (rowsPerPageSelect) {
        recordsPerPage = parseInt(rowsPerPageSelect.value, 10) || 10;
        rowsPerPageSelect.addEventListener('change', () => {
            recordsPerPage = parseInt(rowsPerPageSelect.value, 10) || 10;
            currentPage = 1;
            updatePagination();
            syncPaginationSelection();
        });
    }

    if (tableBody) initPagination();

    function syncPaginationSelection() {
        const root = document.getElementById('mainContent');
        if (root && root._x_dataStack && root._x_dataStack[0]) {
            root._x_dataStack[0].syncSelectAllState();
        }
    }

    window.refreshAccountsPagination = function () {
        if (!tableBody) return;
        const rows = Array.from(tableBody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
        allAccounts = rows.map((el, i) => ({ element: el, index: i }));
        applyAccountsTableFilters();
    };

    // ── Table sorting (Fullname only) ─────────────────────────
    const sortMenu = document.getElementById('accountsSortMenu');
    let sortMenuAnchor = null;

    function compareSortValues(a, b, type) {
        const av = a || '';
        const bv = b || '';
        if (type === 'date') {
            if (!av && !bv) return 0;
            if (!av) return 1;
            if (!bv) return -1;
            return av.localeCompare(bv);
        }
        return av.localeCompare(bv, undefined, { sensitivity: 'base' });
    }

    function closeAccountsSortMenu() {
        if (sortMenu) sortMenu.hidden = true;
        sortMenuAnchor = null;
        document.querySelectorAll('.accounts-sort-btn').forEach(btn => btn.setAttribute('aria-expanded', 'false'));
    }

    function updateSortHeaderState(key, dir) {
        document.querySelectorAll('.accounts-th-sortable').forEach(th => {
            th.classList.remove('is-sorted-asc', 'is-sorted-desc');
            th.setAttribute('aria-sort', 'none');
        });
        const th = document.querySelector(`.accounts-th-sortable[data-sort-key="${key}"]`);
        if (th && dir) {
            th.classList.add(dir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
        }
    }

    function applyAccountsSort(key, dir, type) {
        if (!tableBody) return;
        const sortAttrMap = {
            name: 'sortName',
            email: 'sortEmail',
            barangay: 'sortBarangay',
            term: 'sortTerm',
        };
        const dataAttr = sortAttrMap[key] || 'sortName';
        filteredAccounts.sort((rowA, rowB) => {
            const valA = rowA.element.dataset[dataAttr] || '';
            const valB = rowB.element.dataset[dataAttr] || '';
            const cmp = compareSortValues(valA, valB, type);
            return dir === 'asc' ? cmp : -cmp;
        });
        filteredAccounts.forEach(item => tableBody.appendChild(item.element));
        currentPage = 1;
        updatePagination();
        syncPaginationSelection();
        updateSortHeaderState(key, dir);
    }

    function openAccountsSortMenu(anchor, key, type) {
        if (!sortMenu || !anchor) return;
        sortMenuAnchor = anchor;
        const th = anchor.closest('.accounts-th-sortable');
        const currentDir = th?.classList.contains('is-sorted-asc') ? 'asc'
            : th?.classList.contains('is-sorted-desc') ? 'desc' : null;

        sortMenu.innerHTML = '';
        [
            { dir: 'asc', label: 'Sort ascending', hint: 'A → Z', icon: '↑' },
            { dir: 'desc', label: 'Sort descending', hint: 'Z → A', icon: '↓' },
        ].forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'accounts-sort-option' + (currentDir === opt.dir ? ' is-active' : '');
            btn.setAttribute('role', 'menuitem');
            btn.innerHTML = `<span class="accounts-sort-option-icon">${opt.icon}</span><span class="accounts-sort-option-text"><span class="accounts-sort-option-label">${opt.label}</span><span class="accounts-sort-option-hint">${opt.hint}</span></span>`;
            btn.addEventListener('click', () => {
                applyAccountsSort(key, opt.dir, type);
                closeAccountsSortMenu();
            });
            sortMenu.appendChild(btn);
        });

        const rect = anchor.getBoundingClientRect();
        sortMenu.hidden = false;
        sortMenu.style.top = `${rect.bottom + 4}px`;
        sortMenu.style.left = `${rect.left}px`;
        anchor.setAttribute('aria-expanded', 'true');
    }

    document.querySelectorAll('.accounts-th-sortable .accounts-sort-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const th = btn.closest('.accounts-th-sortable');
            if (!th) return;
            const key = th.dataset.sortKey;
            const type = th.dataset.sortType || 'text';
            if (sortMenuAnchor === btn && sortMenu && !sortMenu.hidden) {
                closeAccountsSortMenu();
                return;
            }
            closeAccountsSortMenu();
            openAccountsSortMenu(btn, key, type);
        });
    });

    document.addEventListener('click', (e) => {
        if (sortMenu && !sortMenu.hidden && !sortMenu.contains(e.target) && e.target !== sortMenuAnchor && !sortMenuAnchor?.contains(e.target)) {
            closeAccountsSortMenu();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAccountsSortMenu();
    });

    // ── Client-side filters (no page reload) ─────────────────
    const barangayFilter = document.getElementById('barangayFilter');
    const positionFilter = document.getElementById('positionFilter');
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    const accountsFilterForm = document.getElementById('accountsFilterForm');

    function applyAccountsTableFilters() {
        const barangayId = String(barangayFilter?.value || '').trim();
        const position = String(positionFilter?.value || '').trim();
        const search = (searchInput?.value || '').trim().toLowerCase();

        filteredAccounts = allAccounts.filter(({ element: row }) => {
            const rowBarangayId = String(row.dataset.barangayId || '').trim();
            const rowPosition = String(row.dataset.filterPosition || '').trim();

            if (barangayId && rowBarangayId !== barangayId) {
                return false;
            }
            if (position && rowPosition !== position) {
                return false;
            }
            if (search && !(row.dataset.searchText || '').includes(search)) {
                return false;
            }
            return true;
        });

        currentPage = 1;
        updatePagination();
        syncPaginationSelection();
    }

    function syncFilterUrl() {
        const url = new URL(window.location.href);
        const barangayId = barangayFilter?.value || '';
        const position = positionFilter?.value || '';
        const search = (searchInput?.value || '').trim();

        if (barangayId) url.searchParams.set('barangay_id', barangayId);
        else url.searchParams.delete('barangay_id');

        if (position) url.searchParams.set('position', position);
        else url.searchParams.delete('position');

        if (search) url.searchParams.set('search', search);
        else url.searchParams.delete('search');

        window.history.replaceState({}, '', url);
    }

    function onAccountsFilterChange() {
        applyAccountsTableFilters();
        syncFilterUrl();
    }

    if (barangayFilter) {
        barangayFilter.addEventListener('change', onAccountsFilterChange);
    }

    if (positionFilter) {
        positionFilter.addEventListener('change', onAccountsFilterChange);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', onAccountsFilterChange);
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                onAccountsFilterChange();
            }
        });
    }

    if (accountsFilterForm) {
        accountsFilterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            onAccountsFilterChange();
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (barangayFilter && urlParams.get('barangay_id')) {
        barangayFilter.value = urlParams.get('barangay_id');
    }
    if (positionFilter && urlParams.get('position')) {
        positionFilter.value = urlParams.get('position');
    }
    if (searchInput && urlParams.get('search')) {
        searchInput.value = urlParams.get('search');
    }
    if (urlParams.get('barangay_id') || urlParams.get('position') || urlParams.get('search')) {
        applyAccountsTableFilters();
    }

    // ── Add SK Officials — backend submit ───────────────────
    const officialsForm = document.getElementById('addSkOfficialsForm');
    if (officialsForm) {
        wireCreateAccountForm(officialsForm);

        officialsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateSkOfficialsManualForm(officialsForm)) {
                const first = officialsForm.querySelector('.is-invalid');
                if (first) first.focus();
                return;
            }

            const formData = new FormData(officialsForm);
            const payload = {};
            for (const [k, v] of formData.entries()) { if (k !== '_token') payload[k] = v; }
            payload.term_status = payload.term_status || (payload.status === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE');

            showLoadingOverlay('Creating account', 'Please wait while we set up the account...');
            fetch('/accounts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(async r => {
                    const ct = r.headers.get('content-type') || '';
                    const data = ct.includes('application/json') ? await r.json() : {};
                    return { ok: r.ok, data };
                })
                .then(({ ok, data }) => {
                    if (!ok || !data.success) {
                        cleanupAccountUiState();
                        if (data.errors) {
                            const firstError = displayFormValidationErrors(officialsForm, data.errors)
                                || Object.values(data.errors).flat()[0]
                                || 'Failed to create account. Please try again.';
                            showAccountToast(firstError, 'error');
                            const invalid = officialsForm.querySelector('.is-invalid');
                            if (invalid) invalid.focus();
                        } else {
                            showAccountToast(data.message || 'Failed to create account. Please try again.', 'error');
                        }
                        return;
                    }

                    cleanupAccountUiState();
                    showAccountToast(data.message || 'SK Officials account created. Password setup email sent.', 'success');
                    window.setTimeout(() => window.location.reload(), 900);
                })
                .catch(() => {
                    cleanupAccountUiState();
                    alert('An unexpected error occurred. Please try again.');
                });
        });
    }

    // ── Add SK Federation — backend submit ────────────────────
    const fedForm = document.getElementById('addSkFedForm');
    if (fedForm) {
        wireCreateAccountForm(fedForm);

        fedForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validate all required fields first
            if (!validateSkFedManualForm(fedForm)) {
                const first = fedForm.querySelector('.is-invalid');
                if (first) first.focus();
                return;
            }

            clearAllErrors(fedForm);
            fedForm.querySelectorAll('.validation-error').forEach(err => err.remove());

            const formData = new FormData(fedForm);
            const payload = {};
            for (const [k, v] of formData.entries()) { if (k !== '_token') payload[k] = v; }
            payload.term_status = payload.term_status || (payload.status === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE');

            showLoadingOverlay('Creating account...');
            fetch('/accounts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(async r => { const ct = r.headers.get('content-type') || ''; const data = ct.includes('application/json') ? await r.json() : {}; return { ok: r.ok, data }; })
                .then(({ ok, data }) => {
                    if (!ok || !data.success) {
                        cleanupAccountUiState();
                        if (data.errors) {
                            let handledError = false;
                            Object.keys(data.errors).forEach(f => {
                                const input = fedForm.querySelector(`[name="${f}"]`);
                                if (input) {
                                    _showErr(input, data.errors[f][0]);
                                    handledError = true;
                                }
                            });
                            if (!handledError) {
                                const firstError = Object.values(data.errors).flat()[0] || 'Failed to create account. Please try again.';
                                alert(firstError);
                            }
                        } else {
                            alert('Failed to create account. Please try again.');
                        }
                        return;
                    }
                    cleanupAccountUiState();
                    showAccountToast(data.message || 'SK Federation account created. Password setup email sent.', 'success');
                    window.setTimeout(() => window.location.reload(), 900);
                })
                .catch(() => {
                    cleanupAccountUiState();
                    alert('An unexpected error occurred. Please try again.');
                });
        });
    }

    // ── Edit forms — backend submit ─────────────────────────
    function attachEditSubmit(form) {
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearAllErrors(form);
            const accountId = form.dataset.accountId || '';
            if (!accountId) {
                alert('Missing account id. Please refresh and try again.');
                return;
            }

            if (form.id === 'editSkOfficialsForm' && !validateSkOfficialsManualForm(form)) {
                const first = form.querySelector('.is-invalid');
                if (first) first.focus();
                return;
            }

            const formData = new FormData(form);
            const payload = {};
            for (const [k, v] of formData.entries()) { if (k !== '_token') payload[k] = v; }
            if (form.id === 'editSkOfficialsForm') {
                ['date_of_birth', 'term_start', 'term_end'].forEach((field) => {
                    if (payload[field]) {
                        payload[field] = normalizeDateInputValue(payload[field]) || payload[field];
                    }
                });
            }

            showLoadingOverlay();
            fetch(`/accounts/${accountId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(async r => {
                    const ct = r.headers.get('content-type') || '';
                    const data = ct.includes('application/json') ? await r.json() : {};
                    return { ok: r.ok, data };
                })
                .then(({ ok, data }) => {
                    hideLoadingOverlay();
                    if (!ok || !data.success) {
                        if (data.errors) {
                            const firstError = displayFormValidationErrors(form, data.errors)
                                || Object.values(data.errors).flat()[0]
                                || 'Failed to update account. Please try again.';
                            showAccountToast(firstError, 'error');
                            const invalid = form.querySelector('.is-invalid');
                            if (invalid) invalid.focus();
                        } else {
                            showAccountToast('Failed to update account. Please try again.', 'error');
                        }
                        return;
                    }
                    _closeEditByType();
                    _showEditSuccessByType();
                })
                .catch(() => { hideLoadingOverlay(); alert('An unexpected error occurred. Please try again.'); });
        });
    }

    const fedEditForm = document.getElementById('editAccountForm');
    const officialsEditForm = document.getElementById('editSkOfficialsForm');
    if (officialsEditForm) {
        wireCreateAccountForm(officialsEditForm);
        applyTermDateConstraints(officialsEditForm);
        applySkOfficialDobConstraints(officialsEditForm);
    }
    attachEditSubmit(fedEditForm);
    attachEditSubmit(officialsEditForm);
    if (fedEditForm) {
        wireCreateAccountForm(fedEditForm);
    }

    // DOB → age auto-fill
    attachDobAgeAutoFill(fedForm, 'date_of_birth', 'age');
    attachDobAgeAutoFill(fedEditForm, 'date_of_birth', 'age');
    attachDobAgeAutoFill(officialsForm, 'date_of_birth', 'age');
    attachDobAgeAutoFill(officialsEditForm, 'date_of_birth', 'age');

    // ── Edit button click → populate form ────────────────────
    function populateEditForm(form, data) {
        if (!form) return;
        resetEditSkOfficialsFormState(form);
        form.dataset.accountId = data.accountId || '';
        ['first_name', 'last_name', 'middle_name', 'sex', 'age', 'contact_number', 'email', 'position', 'barangay_id', 'term_status'].forEach(n => setFormFieldValue(form, n, data[_camel(n)] ?? data[n] ?? ''));
        ['date_of_birth', 'term_start', 'term_end'].forEach((name) => {
            const value = data[_camel(name)] ?? data[name] ?? '';
            setFormFieldValue(form, name, form.id === 'editSkOfficialsForm' ? formatDateForTextInput(value) : value);
        });
        setSuffixFieldValue(form, data[_camel('suffix')] ?? data.suffix ?? data.suffix ?? '');
        const statusField = form.querySelector('[name="status"]');
        if (statusField) statusField.value = 'ACTIVE';
        applySkOfficialDobConstraints(form);
        applyTermDateConstraints(form);
        clearAllErrors(form);
    }

    function _camel(s) {
        return s.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
    }

    function openEditWithData(btn) {
        const d = btn.dataset;
        const isOfficials = getCurrentAccountType() === 'sk_officials';
        if (!isOfficials) {
            openAssignFederationPositionWithData(btn);
            return;
        }
        populateEditForm(officialsEditForm, d);
        openEditSkOfficialsModal();
    }

    function getTakenFederationPositions() {
        const root = document.getElementById('mainContent');
        if (!root?.dataset.takenFederationPositions) {
            return [];
        }
        try {
            return JSON.parse(root.dataset.takenFederationPositions) || [];
        } catch {
            return [];
        }
    }

    function refreshFederationPositionOptions(currentPosition = '') {
        const select = document.getElementById('assign_federation_position');
        if (!select) return;

        const taken = getTakenFederationPositions().filter((pos) => pos && pos !== currentPosition);
        Array.from(select.options).forEach((option) => {
            if (!option.value) {
                option.disabled = false;
                return;
            }
            option.disabled = taken.includes(option.value);
        });
    }

    window.openAssignFederationPositionWithData = function (btn) {
        const form = document.getElementById('assignFederationPositionForm');
        const modal = document.getElementById('assignFederationPositionModal');
        if (!form || !modal) return;

        const d = btn.dataset;
        const displayName = [d.firstName, d.middleName, d.lastName].filter(Boolean).join(' ').trim() || 'Member';
        form.dataset.accountId = d.accountId || '';
        document.getElementById('assignFedDisplayName').textContent = displayName;
        document.getElementById('assignFedBarangayName').textContent = d.barangayName || '—';

        const positionSelect = document.getElementById('assign_federation_position');
        const currentPosition = d.federationPosition || '';
        if (positionSelect) {
            positionSelect.value = currentPosition;
            refreshFederationPositionOptions(currentPosition);
        }

        const confirmInput = document.getElementById('assign_position_confirm');
        if (confirmInput) {
            confirmInput.value = '';
            _clearErr(confirmInput);
        }

        const positionError = positionSelect?.parentNode?.querySelector('.form-error-light');
        if (positionError) positionError.textContent = '';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeAssignFederationPositionModal = function () {
        const modal = document.getElementById('assignFederationPositionModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    const assignFederationForm = document.getElementById('assignFederationPositionForm');
    if (assignFederationForm) {
        assignFederationForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const accountId = assignFederationForm.dataset.accountId || '';
            const positionSelect = document.getElementById('assign_federation_position');
            const confirmInput = document.getElementById('assign_position_confirm');
            const federationPosition = positionSelect?.value ?? '';
            const confirmValue = (confirmInput?.value || '').trim().toUpperCase();

            let valid = true;
            if (confirmValue !== 'ASSIGN') {
                _showErr(confirmInput, 'Please type ASSIGN to confirm this position assignment');
                valid = false;
            } else {
                _markValid(confirmInput);
            }

            if (federationPosition) {
                const taken = getTakenFederationPositions();
                const currentRowPosition = positionSelect?.querySelector(`option[value="${federationPosition}"]`)?.disabled;
                if (taken.includes(federationPosition) && currentRowPosition) {
                    _showErr(positionSelect, 'This federation position is already assigned to another member.');
                    valid = false;
                } else {
                    _clearErr(positionSelect);
                }
            }

            if (!valid || !accountId) {
                if (!accountId) alert('Missing account id. Please refresh and try again.');
                return;
            }

            showLoadingOverlay('Assigning position...');
            fetch(`/accounts/${accountId}/federation-position`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ federation_position: federationPosition || null }),
            })
                .then(async (r) => {
                    const data = r.headers.get('content-type')?.includes('application/json') ? await r.json() : {};
                    return { ok: r.ok, data };
                })
                .then(({ ok, data }) => {
                    hideLoadingOverlay();
                    if (!ok || !data.success) {
                        const message = data.errors?.federation_position?.[0]
                            || data.message
                            || Object.values(data.errors || {}).flat()[0]
                            || 'Failed to assign federation position.';
                        if (data.errors?.federation_position && positionSelect) {
                            _showErr(positionSelect, message);
                        } else {
                            alert(message);
                        }
                        return;
                    }

                    closeAssignFederationPositionModal();
                    showAccountToast(data.message || 'Federation position updated successfully.', 'edit');
                    window.setTimeout(() => window.location.reload(), 900);
                })
                .catch(() => {
                    hideLoadingOverlay();
                    alert('An unexpected error occurred. Please try again.');
                });
        });
    }

    function resetAccountActionsDropdownPosition(menu) {
        const dropdown = menu?.querySelector('.account-actions-dropdown');
        if (!dropdown) return;
        dropdown.classList.remove('is-floating', 'account-actions-dropdown-up');
        dropdown.style.position = '';
        dropdown.style.top = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.bottom = '';
        dropdown.style.zIndex = '';
    }

    function positionAccountActionsDropdown(menu) {
        const trigger = menu.querySelector('.account-actions-trigger');
        const dropdown = menu.querySelector('.account-actions-dropdown');
        if (!trigger || !dropdown) return;

        dropdown.classList.add('is-floating');
        dropdown.style.position = 'fixed';
        dropdown.style.zIndex = '1300';

        const rect = trigger.getBoundingClientRect();
        const gap = 6;
        const dropdownHeight = dropdown.offsetHeight || 150;
        const dropdownWidth = dropdown.offsetWidth || 188;

        let top = rect.bottom + gap;
        dropdown.classList.remove('account-actions-dropdown-up');

        if (top + dropdownHeight > window.innerHeight - 8 && rect.top - dropdownHeight - gap > 8) {
            top = rect.top - dropdownHeight - gap;
            dropdown.classList.add('account-actions-dropdown-up');
        }

        let right = window.innerWidth - rect.right;
        right = Math.max(8, Math.min(right, window.innerWidth - dropdownWidth - 8));

        dropdown.style.top = `${Math.max(8, top)}px`;
        dropdown.style.right = `${right}px`;
        dropdown.style.left = 'auto';
        dropdown.style.bottom = 'auto';
    }

    function closeAllAccountActionMenus(exceptMenu = null) {
        document.querySelectorAll('.account-actions-menu').forEach((menu) => {
            if (menu === exceptMenu && menu.classList.contains('is-open')) return;
            menu.classList.remove('is-open');
            const trigger = menu.querySelector('.account-actions-trigger');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
            resetAccountActionsDropdownPosition(menu);
        });
    }

    // ── Delegated click handlers (covers server-rendered + JS-injected rows) ──
    if (tableBody) {
        tableBody.addEventListener('click', function (e) {
            const trigger = e.target.closest('.account-actions-trigger');
            if (trigger) {
                e.stopPropagation();
                const menu = trigger.closest('.account-actions-menu');
                if (!menu) return;
                const willOpen = !menu.classList.contains('is-open');
                closeAllAccountActionMenus();
                if (willOpen) {
                    menu.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                    requestAnimationFrame(() => positionAccountActionsDropdown(menu));
                }
                return;
            }

            const viewBtn = e.target.closest('.btn-view-account');
            const editBtn = e.target.closest('.btn-edit-account');
            const deleteBtn = e.target.closest('.btn-delete-account');
            if (viewBtn) {
                closeAllAccountActionMenus();
                openViewWithData(viewBtn);
            }
            if (editBtn) {
                closeAllAccountActionMenus();
                openEditWithData(editBtn);
            }
            if (deleteBtn) {
                closeAllAccountActionMenus();
                const id = parseInt(deleteBtn.dataset.accountId, 10);
                if (!Number.isNaN(id)) {
                    window.AccountsDeleteModal.openSingle(id);
                }
            }
        });
    }

    document.addEventListener('click', function () {
        closeAllAccountActionMenus();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllAccountActionMenus();
    });

    window.addEventListener('resize', function () {
        const openMenu = document.querySelector('.account-actions-menu.is-open');
        if (openMenu) positionAccountActionsDropdown(openMenu);
    });

    window.addEventListener('scroll', function () {
        closeAllAccountActionMenus();
    }, true);

    // ── View button click → populate view modal ───────────────
    function openViewWithData(btn) {
        const d = btn.dataset;
        const isOfficials = getCurrentAccountType() === 'sk_officials';
        const fullName = [d.firstName, d.middleName, d.lastName]
            .filter(v => v && v.trim())
            .map(v => v.trim().toUpperCase())
            .concat(d.suffix && d.suffix.trim() ? [d.suffix.trim()] : [])
            .join(' ');

        const sections = [];

        sections.push(viewProfileGroup('fa-user', 'Personal Information', [
            ['Full Name', fullName],
            ['Sex', d.sex],
            ['Date of Birth', d.dateOfBirth ? formatDate(d.dateOfBirth) : ''],
            ['Age', d.age],
            ['Contact Number', d.contactNumber],
        ]));

        sections.push(viewProfileGroup('fa-briefcase', 'Position & Account', [
            ['Position', d.position],
            ['Email Address', d.email],
            ['Email Verification', d.emailVerifiedAt || 'Not Verified'],
        ]));

        sections.push(viewProfileGroup('fa-location-dot', 'Address', [
            ['Region', isOfficials ? (d.region || 'IV-A CALABARZON') : (d.region || 'IV-A CALABARZON')],
            ['Province', d.province || 'Laguna'],
            ['Municipality', d.municipality || 'Santa Cruz'],
            ['Barangay', d.barangayName || '-'],
        ]));

        sections.push(viewProfileGroup('fa-calendar-check', 'Term Information', [
            ['Term Start', d.termStart ? formatDate(d.termStart) : ''],
            ['Term End', d.termEnd ? formatDate(d.termEnd) : ''],
        ]));

        document.getElementById('viewAccountBody').innerHTML = `
            <div class="account-modal-card">
                ${sections.join('')}
            </div>
        `;
        openViewModal();
    }

    function viewProfileGroup(iconClass, title, fields) {
        const cells = fields.map(([label, value]) => `
            <div class="account-profile-field">
                <label>${escapeHtml(label)}</label>
                <p>${escapeHtml(value || '-')}</p>
            </div>
        `).join('');

        return `
            <div class="account-profile-group">
                <div class="account-profile-group-label">
                    <i class="fa-solid ${iconClass}"></i> ${escapeHtml(title)}
                </div>
                <div class="account-profile-row">${cells}</div>
            </div>
        `;
    }

    // legacy helper kept for compatibility
    function viewSection(title, fields) {
        return viewProfileGroup('fa-circle-info', title, fields);
    }

    // static view bindings handled by delegated listener above

    initBatchUploadPanel('official', 'sk_official');
    initBatchUploadPanel('fed', 'sk_fed');
});
