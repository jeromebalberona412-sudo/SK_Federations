(function () {
    'use strict';

    const config = window.barangayMonitoringScheduleConfig || {};

    function csrfToken() {
        return config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function resetTypeConfirm(inputId, hintId, buttonId) {
        var input = document.getElementById(inputId);
        var hint = document.getElementById(hintId);
        var button = document.getElementById(buttonId);
        if (input) input.value = '';
        if (hint) hint.hidden = true;
        if (button) button.disabled = true;
    }

    function bindTypeConfirm(inputId, hintId, buttonId, expected) {
        var input = document.getElementById(inputId);
        var hint = document.getElementById(hintId);
        var button = document.getElementById(buttonId);
        if (!input || !button) return;

        function sync() {
            var matched = input.value.trim().toLowerCase() === expected;
            button.disabled = !matched;
            if (hint) {
                hint.hidden = matched || input.value.trim() === '';
            }
        }

        input.addEventListener('input', sync);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (!button.disabled) button.click();
            }
        });
        sync();
    }

    function closeModals() {
        document.querySelectorAll('.bm-modal').forEach(function (modal) {
            if (modal.id !== 'cancelScheduleModal' && modal.id !== 'deleteScheduleModal') {
                modal.hidden = true;
            }
        });
    }

    function openScheduleViewModal() {
        const modal = document.getElementById('scheduleViewModal');
        if (modal) modal.hidden = false;
    }

    function closeScheduleViewModal() {
        const modal = document.getElementById('scheduleViewModal');
        if (modal) modal.hidden = true;
    }

    function openScheduleHistoryModal() {
        const modal = document.getElementById('scheduleHistoryModal');
        if (modal) modal.hidden = false;
    }

    function closeScheduleHistoryModal() {
        const modal = document.getElementById('scheduleHistoryModal');
        if (modal) modal.hidden = true;
    }

    function openCancelScheduleModal() {
        resetTypeConfirm('cancelScheduleConfirmInput', 'cancelScheduleConfirmHint', 'confirmCancelScheduleBtn');
        const modal = document.getElementById('cancelScheduleModal');
        if (modal) modal.hidden = false;
        document.getElementById('cancelScheduleConfirmInput')?.focus();
    }

    function closeCancelScheduleModal() {
        const modal = document.getElementById('cancelScheduleModal');
        if (modal) modal.hidden = true;
        resetTypeConfirm('cancelScheduleConfirmInput', 'cancelScheduleConfirmHint', 'confirmCancelScheduleBtn');
    }

    const LOADING_FADE_MS = 450;
    const TOAST_VISIBLE_MS = 2600;

    function showScheduleToast(message) {
        const existing = document.querySelector('.bm-schedule-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'bm-schedule-toast';
        toast.setAttribute('role', 'status');
        toast.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i><span>' + message + '</span>';
        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () {
                toast.remove();
            }, 350);
        }, TOAST_VISIBLE_MS);
    }

    function finishScheduleAction(toastMessage) {
        if (typeof window.hideLoading === 'function') {
            window.hideLoading();
        }

        return new Promise(function (resolve) {
            setTimeout(function () {
                showScheduleToast(toastMessage);
                setTimeout(function () {
                    window.location.reload();
                    resolve();
                }, TOAST_VISIBLE_MS);
            }, LOADING_FADE_MS);
        });
    }

    function formatDateInput(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function currentYearBounds() {
        const now = new Date();
        const year = now.getFullYear();
        return {
            year: year,
            calendarYear: year,
            today: formatDateInput(now),
            yearEnd: `${year}-12-31`,
        };
    }

    function applyScheduleDateConstraints(mode) {
        const bounds = currentYearBounds();
        const isCreate = mode !== 'edit';
        const startInput = document.getElementById('scheduleDateStart');
        const deadlineInput = document.getElementById('scheduleDeadline');

        if (startInput) {
            startInput.min = bounds.today;
            startInput.max = bounds.yearEnd;
            startInput.readOnly = false;
            if (isCreate && !startInput.value) {
                startInput.value = bounds.today;
            }
        }

        if (deadlineInput) {
            const startValue = startInput?.value || bounds.today;
            deadlineInput.min = startValue > bounds.today ? startValue : bounds.today;
            deadlineInput.max = bounds.yearEnd;
        }

        return bounds;
    }

    function clearScheduleFieldErrors() {
        ['scheduleDateStartError', 'scheduleDeadlineError'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = '';
                el.hidden = true;
            }
        });
    }

    function showScheduleFieldError(fieldId, message) {
        const el = document.getElementById(fieldId);
        if (!el) return;
        el.textContent = message;
        el.hidden = false;
    }

    function validateScheduleForm(mode) {
        clearScheduleFieldErrors();
        const bounds = currentYearBounds();
        const title = document.getElementById('scheduleTitle')?.value.trim() || '';
        const dateStart = document.getElementById('scheduleDateStart')?.value || '';
        const deadline = document.getElementById('scheduleDeadline')?.value || '';
        let valid = true;

        if (!title) {
            alert('Title is required.');
            return false;
        }

        if (title.length > 50) {
            alert('Title must not exceed 50 characters.');
            return false;
        }

        if (!dateStart) {
            showScheduleFieldError('scheduleDateStartError', 'Start date is required.');
            valid = false;
        }

        if (!deadline) {
            showScheduleFieldError('scheduleDeadlineError', 'Deadline is required.');
            valid = false;
        }

        if (!valid) {
            return false;
        }

        if (dateStart < bounds.today || dateStart > bounds.yearEnd) {
            showScheduleFieldError(
                'scheduleDateStartError',
                'Start date must be between today and December 31, ' + bounds.year + '.'
            );
            valid = false;
        }

        if (deadline < bounds.today || deadline > bounds.yearEnd) {
            showScheduleFieldError(
                'scheduleDeadlineError',
                'Deadline must be between today and December 31, ' + bounds.year + '.'
            );
            valid = false;
        } else if (deadline < dateStart) {
            showScheduleFieldError('scheduleDeadlineError', 'Deadline must be on or after the start date.');
            valid = false;
        }

        return valid;
    }

    function updateScheduleFieldHints(mode) {
        const bounds = currentYearBounds();
        const startHint = document.getElementById('scheduleDateStartHint');
        const deadlineHint = document.getElementById('scheduleDeadlineHint');

        if (startHint) {
            startHint.textContent = 'Must be between today and December 31, ' + bounds.year + '.';
        }

        if (deadlineHint) {
            deadlineHint.textContent = 'Must be on or after the start date and on or before December 31, ' + bounds.year + '.';
        }
    }

    function defaultCreateDates(bounds) {
        return {
            start: bounds.today,
            end: bounds.yearEnd,
        };
    }

    function extractErrorMessage(payload) {
        if (payload?.message) return payload.message;
        if (payload?.errors) {
            const flat = Object.values(payload.errors).flat().filter(Boolean);
            if (flat.length) return flat.join(' ');
        }
        return 'Request failed.';
    }

    async function apiRequest(url, method, body) {
        const response = await fetch(url, {
            method: method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });

        const payload = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(extractErrorMessage(payload));
        }

        return payload;
    }

    function openScheduleModal(mode) {
        const modal = document.getElementById('scheduleModal');
        const title = document.getElementById('scheduleModalTitle');
        const schedule = config.currentSchedule || {};
        const bounds = applyScheduleDateConstraints(mode);
        const defaults = defaultCreateDates(bounds);
        const calendarYearInput = document.getElementById('scheduleFiscalYear');

        document.getElementById('scheduleId').value = mode === 'edit' ? (schedule.id || '') : '';
        if (calendarYearInput) {
            calendarYearInput.value = mode === 'edit'
                ? (schedule.fiscal_year || bounds.calendarYear)
                : bounds.calendarYear;
            calendarYearInput.readOnly = true;
        }
        document.getElementById('scheduleTitle').value = mode === 'edit'
            ? (schedule.title || 'ABYIP Submission')
            : 'ABYIP Submission';
        document.getElementById('scheduleDateStart').value = mode === 'edit'
            ? (schedule.date_start_raw || bounds.today)
            : defaults.start;
        document.getElementById('scheduleDeadline').value = mode === 'edit'
            ? (schedule.deadline_raw || '')
            : defaults.end;

        const startValue = document.getElementById('scheduleDateStart')?.value || bounds.today;
        const deadlineInput = document.getElementById('scheduleDeadline');
        if (deadlineInput) {
            deadlineInput.min = startValue > bounds.today ? startValue : bounds.today;
            deadlineInput.max = bounds.yearEnd;
        }

        if (title) title.textContent = mode === 'edit' ? 'Edit ABYIP Schedule' : 'Create ABYIP Schedule';
        clearScheduleFieldErrors();
        updateScheduleFieldHints(mode);
        if (modal) modal.hidden = false;
    }

    async function saveSchedule() {
        const saveBtn = document.getElementById('scheduleSaveBtn');
        const id = document.getElementById('scheduleId').value;
        const mode = id ? 'edit' : 'create';

        if (!validateScheduleForm(mode)) {
            return;
        }

        if (mode === 'create' && !config.canCreateSchedule) {
            alert('A schedule for this calendar year already exists. You can only create one schedule per year.');
            return;
        }

        const bounds = currentYearBounds();
        const calendarYear = bounds.calendarYear;
        const title = document.getElementById('scheduleTitle').value.trim();
        const dateStart = document.getElementById('scheduleDateStart').value;
        const deadline = document.getElementById('scheduleDeadline').value;

        const payload = {
            fiscal_year: calendarYear,
            title: title,
            date_start: dateStart,
            deadline: deadline,
            allow_late_extension: false,
        };

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        if (typeof window.showLoading === 'function') {
            window.showLoading('Saving ABYIP Schedule', 'Please wait while we save your schedule...');
        }

        try {
            if (id) {
                await apiRequest(config.updateUrl + '/' + id, 'PUT', payload);
            } else {
                await apiRequest(config.storeUrl, 'POST', payload);
            }

            closeModals();
            await finishScheduleAction('ABYIP schedule saved successfully.');
        } catch (error) {
            if (typeof window.hideLoading === 'function') {
                window.hideLoading();
            }
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Schedule';
            }
            throw error;
        }
    }

    async function cancelSchedule() {
        const schedule = config.currentSchedule || {};
        if (!schedule.id) return;
        if ((document.getElementById('cancelScheduleConfirmInput')?.value || '').trim().toLowerCase() !== 'cancel') {
            return;
        }

        const confirmBtn = document.getElementById('confirmCancelScheduleBtn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Cancelling...';
        }

        if (typeof window.showLoading === 'function') {
            window.showLoading('Cancelling Schedule', 'Please wait...');
        }

        try {
            await apiRequest(config.updateUrl + '/' + schedule.id + '/cancel', 'POST', { reason: null });
            closeCancelScheduleModal();
            closeScheduleViewModal();
            await finishScheduleAction('ABYIP schedule cancelled successfully.');
        } catch (error) {
            if (typeof window.hideLoading === 'function') {
                window.hideLoading();
            }
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Yes, Cancel Schedule';
            }
            throw error;
        }
    }

    function closeDeleteScheduleModal() {
        const modal = document.getElementById('deleteScheduleModal');
        if (modal) modal.hidden = true;
        resetTypeConfirm('deleteScheduleConfirmInput', 'deleteScheduleConfirmHint', 'confirmDeleteScheduleBtn');
    }

    function openDeleteScheduleModal() {
        resetTypeConfirm('deleteScheduleConfirmInput', 'deleteScheduleConfirmHint', 'confirmDeleteScheduleBtn');
        const modal = document.getElementById('deleteScheduleModal');
        if (modal) modal.hidden = false;
        document.getElementById('deleteScheduleConfirmInput')?.focus();
    }

    async function deleteSchedule() {
        const schedule = config.currentSchedule || {};
        if (!schedule.id) return;
        if ((document.getElementById('deleteScheduleConfirmInput')?.value || '').trim().toLowerCase() !== 'delete') {
            return;
        }

        const confirmBtn = document.getElementById('confirmDeleteScheduleBtn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Deleting...';
        }

        if (typeof window.showLoading === 'function') {
            window.showLoading('Deleting Schedule', 'Please wait...');
        }

        try {
            await apiRequest(config.updateUrl + '/' + schedule.id, 'DELETE');
            closeDeleteScheduleModal();
            closeScheduleViewModal();
            await finishScheduleAction('ABYIP schedule deleted successfully.');
        } catch (error) {
            if (typeof window.hideLoading === 'function') {
                window.hideLoading();
            }
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Yes, Delete Schedule';
            }
            throw error;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindTypeConfirm('cancelScheduleConfirmInput', 'cancelScheduleConfirmHint', 'confirmCancelScheduleBtn', 'cancel');
        bindTypeConfirm('deleteScheduleConfirmInput', 'deleteScheduleConfirmHint', 'confirmDeleteScheduleBtn', 'delete');

        document.getElementById('btnViewSchedule')?.addEventListener('click', openScheduleViewModal);

        document.getElementById('btnScheduleHistory')?.addEventListener('click', openScheduleHistoryModal);

        document.getElementById('btnCreateSchedule')?.addEventListener('click', function () {
            closeScheduleViewModal();

            if (config.currentSchedule?.id) {
                openScheduleModal('edit');
                return;
            }

            if (!config.canCreateSchedule) {
                alert('A schedule for this calendar year already exists. You can only create one schedule per year.');
                return;
            }

            openScheduleModal('create');
        });

        document.getElementById('btnEditSchedule')?.addEventListener('click', function () {
            closeScheduleHistoryModal();
            closeScheduleViewModal();
            openScheduleModal('edit');
        });

        document.getElementById('scheduleDateStart')?.addEventListener('change', function () {
            const bounds = currentYearBounds();
            const deadlineInput = document.getElementById('scheduleDeadline');
            if (!deadlineInput) return;
            const startValue = this.value || bounds.today;
            deadlineInput.min = startValue > bounds.today ? startValue : bounds.today;
            deadlineInput.max = bounds.yearEnd;
            clearScheduleFieldErrors();
        });

        document.getElementById('scheduleDeadline')?.addEventListener('change', function () {
            clearScheduleFieldErrors();
        });

        document.getElementById('btnDeleteSchedule')?.addEventListener('click', function () {
            openDeleteScheduleModal();
        });

        document.getElementById('confirmDeleteScheduleBtn')?.addEventListener('click', function () {
            deleteSchedule().catch(function (error) {
                alert(error.message);
            });
        });

        document.querySelectorAll('[data-delete-schedule-close]').forEach(function (btn) {
            btn.addEventListener('click', closeDeleteScheduleModal);
        });

        document.getElementById('btnCancelSchedule')?.addEventListener('click', function () {
            openCancelScheduleModal();
        });

        document.getElementById('confirmCancelScheduleBtn')?.addEventListener('click', function () {
            cancelSchedule().catch(function (error) {
                alert(error.message);
            });
        });

        document.getElementById('scheduleSaveBtn')?.addEventListener('click', function () {
            saveSchedule().catch(function (error) {
                alert(error.message);
            });
        });

        document.querySelectorAll('[data-schedule-close]').forEach(function (btn) {
            btn.addEventListener('click', closeModals);
        });

        document.querySelectorAll('[data-schedule-view-close]').forEach(function (btn) {
            btn.addEventListener('click', closeScheduleViewModal);
        });

        document.querySelectorAll('[data-schedule-history-close]').forEach(function (btn) {
            btn.addEventListener('click', closeScheduleHistoryModal);
        });

        document.querySelectorAll('[data-cancel-schedule-close]').forEach(function (btn) {
            btn.addEventListener('click', closeCancelScheduleModal);
        });
    });
})();
