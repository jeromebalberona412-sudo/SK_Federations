document.addEventListener('DOMContentLoaded', () => {
    initializeCalendar();
});

const CONTENT_MAX = 500;
const TITLE_MAX = 50;
const CALENDAR_CELL_TITLE_PREVIEW = 22;

function truncateCalendarPreview(text, maxLength = CALENDAR_CELL_TITLE_PREVIEW) {
    const value = String(text || '').trim();
    if (value.length <= maxLength) {
        return value;
    }

    return value.slice(0, maxLength).trimEnd() + '…';
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function calendarApiFetch(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(options.headers || {}),
        },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || 'Request failed.');
    return json;
}

function showConfirm(options) {
    return new Promise((resolve) => {
        const { title, message, confirmText = 'OK', cancelText = 'Cancel', confirmClass = 'confirm-primary', theme = 'default' } = options;
        const isAlert = cancelText === '';
        let overlay = document.getElementById('calendar-confirm-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'calendar-confirm-overlay';
            overlay.className = 'calendar-confirm-overlay';
            overlay.innerHTML = `
                <div class="calendar-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="calendarConfirmTitle" aria-describedby="calendarConfirmMessage">
                    <h2 class="calendar-confirm-header" id="calendarConfirmTitle"></h2>
                    <p class="calendar-confirm-body" id="calendarConfirmMessage"></p>
                    <div class="calendar-confirm-actions">
                        <button type="button" class="calendar-confirm-cancel"></button>
                        <button type="button" class="calendar-confirm-ok"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        const headerEl = overlay.querySelector('.calendar-confirm-header');
        const bodyEl = overlay.querySelector('.calendar-confirm-body');
        const cancelBtn = overlay.querySelector('.calendar-confirm-cancel');
        const okBtn = overlay.querySelector('.calendar-confirm-ok');

        headerEl.textContent = title;
        bodyEl.textContent = message;
        cancelBtn.textContent = cancelText || 'Cancel';
        cancelBtn.style.display = isAlert ? 'none' : '';
        okBtn.textContent = confirmText;
        okBtn.className = 'calendar-confirm-ok ' + confirmClass;
        overlay.className = 'calendar-confirm-overlay theme-' + theme;

        const cleanup = (result) => {
            overlay.classList.remove('show');
            cancelBtn.onclick = null;
            okBtn.onclick = null;
            overlay.onclick = null;
            document.removeEventListener('keydown', onKeyDown, true);
            resolve(result);
        };

        const onKeyDown = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                cleanup(isAlert ? true : false);
            }
        };

        cancelBtn.onclick = () => cleanup(false);
        okBtn.onclick = () => cleanup(true);
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                cleanup(isAlert ? true : false);
            }
        };
        document.addEventListener('keydown', onKeyDown, true);

        overlay.classList.add('show');
        window.setTimeout(() => {
            (isAlert ? okBtn : cancelBtn)?.focus();
        }, 20);
    });
}

function setButtonLoading(button, loading, label) {
    if (!button) {
        return;
    }

    if (loading) {
        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }
        button.disabled = true;
        button.classList.add('is-loading');
        button.innerHTML = '<span class="calendar-action-spinner" aria-hidden="true"></span><span>' + label + '</span>';
        return;
    }

    button.disabled = false;
    button.classList.remove('is-loading');
    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
        delete button.dataset.originalHtml;
    }
}

function showToast(message, type) {
    const existing = document.querySelector('.app-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'app-toast app-toast-show app-toast-' + (type || 'success');
    const icon = type === 'error' ? '✕' : '✓';
    toast.innerHTML = '<span class="app-toast-icon">' + icon + '</span> ' + message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('app-toast-show');
        toast.classList.add('app-toast-hide');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

const MIN_YEAR = 1991;

function initializeCalendar() {
    const grid = document.getElementById('calendarGrid');
    const monthLabel = document.getElementById('calendarMonthLabel');
    const prevBtn = document.getElementById('calendarPrevBtn');
    const nextBtn = document.getElementById('calendarNextBtn');
    const jumpBtn = document.getElementById('calendarJumpBtn');

    if (!grid || !monthLabel || !prevBtn || !nextBtn || !jumpBtn) return;

    let current = new Date();
    current.setDate(1);
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const notes = {};
    let notesLoadToken = 0;
    const NOTES_CACHE_TTL_MS = 5 * 60 * 1000;
    const NOTES_CACHE_PREFIX = 'sk_fed_calendar_notes_v1_';

    const today = new Date();
    const currentYear = today.getFullYear();
    const todayKey = `${currentYear}-${today.getMonth()}-${today.getDate()}`;

    function dateKeyToIso(year, monthIndex, day) {
        const m = String(monthIndex + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${year}-${m}-${d}`;
    }

    function isoToDateKey(iso) {
        const [y, m, d] = iso.split('-').map(Number);
        return `${y}-${m - 1}-${d}`;
    }

    function isToday(year, monthIndex, day) {
        return year === currentYear && monthIndex === today.getMonth() && day === today.getDate();
    }

    function isPastDate(year, monthIndex, day) {
        const cellDate = new Date(year, monthIndex, day);
        const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        return cellDate < todayStart;
    }

    function canEditDate(year, monthIndex, day) {
        const key = `${year}-${monthIndex}-${day}`;
        const note = notes[key];
        const hasNote = !!(note && (note.title || note.content));

        // Next year+: never editable
        if (year > currentYear) {
            return false;
        }

        // Existing notes (including past dates) can be viewed/edited/deleted
        if (hasNote) {
            return true;
        }

        // New notes: current year, today or future only
        return canModifyNote(year) && !isPastDate(year, monthIndex, day);
    }

    function canModifyNote(year) {
        return year === currentYear;
    }

    function canViewNote(year, monthIndex, day) {
        const key = `${year}-${monthIndex}-${day}`;
        const note = notes[key];
        return !!(note && (note.title || note.content));
    }

    function handleDayClick(dateKey, day, monthName, year, monthIndex) {
        const note = notes[dateKey];
        const hasNote = !!(note && (note.title || note.content));
        const pastDate = isPastDate(year, monthIndex, day);
        const isPastYear = year < currentYear;
        const isNextYear = year > currentYear;

        // Past date with no note: show error like SK Officials (do not open editor)
        if (!hasNote && pastDate) {
            showToast('No past dates to add note.', 'error');
            return;
        }

        if (!hasNote && !canModifyNote(year)) {
            showToast(
                isPastYear ? 'Cannot add notes to past years.' : 'Cannot add notes to next year or beyond.',
                'error'
            );
            return;
        }

        openEditor(dateKey, day, monthName, year, monthIndex);
    }

    function notesCacheKey(year, month) {
        return NOTES_CACHE_PREFIX + year + '_' + month;
    }

    function applyNotesPayload(payload) {
        Object.keys(notes).forEach((k) => delete notes[k]);
        (payload?.data || []).forEach((note) => {
            const key = isoToDateKey(note.note_date);
            notes[key] = {
                id: note.id,
                title: note.title,
                content: note.content,
            };
        });
        render();
    }

    function persistCurrentMonthCache() {
        const year = current.getFullYear();
        const month = current.getMonth() + 1;
        const data = [];
        Object.keys(notes).forEach((key) => {
            const [y, m, d] = key.split('-').map(Number);
            if (y === year && m === month - 1) {
                data.push({
                    id: notes[key].id,
                    note_date: dateKeyToIso(y, m, d),
                    title: notes[key].title,
                    content: notes[key].content,
                });
            }
        });
        window.SkLocalCache?.set(notesCacheKey(year, month), { data });
    }

    function prefetchAdjacentMonths(year, month) {
        const neighbors = month === 1
            ? [{ year: year - 1, month: 12 }, { year, month: 2 }]
            : month === 12
                ? [{ year, month: 11 }, { year: year + 1, month: 1 }]
                : [{ year, month: month - 1 }, { year, month: month + 1 }];

        neighbors.forEach(({ year: y, month: m }) => {
            const cached = window.SkLocalCache?.get(notesCacheKey(y, m));
            if (cached?.payload && window.SkLocalCache.isFresh(cached, NOTES_CACHE_TTL_MS)) {
                return;
            }
            calendarApiFetch(`/api/calendar/notes?year=${y}&month=${m}`)
                .then((json) => window.SkLocalCache?.set(notesCacheKey(y, m), json))
                .catch(() => {});
        });
    }

    async function loadNotes() {
        const token = ++notesLoadToken;
        const year = current.getFullYear();
        const month = current.getMonth() + 1;
        const cacheKey = notesCacheKey(year, month);
        const cached = window.SkLocalCache?.get(cacheKey);

        if (cached?.payload) {
            applyNotesPayload(cached.payload);
            if (window.SkLocalCache.isFresh(cached, NOTES_CACHE_TTL_MS)) {
                prefetchAdjacentMonths(year, month);
                return;
            }
        } else {
            Object.keys(notes).forEach((k) => delete notes[k]);
            render();
        }

        try {
            const json = await calendarApiFetch(`/api/calendar/notes?year=${year}&month=${month}`);
            if (token !== notesLoadToken) return;
            window.SkLocalCache?.set(cacheKey, json);
            applyNotesPayload(json);
            prefetchAdjacentMonths(year, month);
        } catch (err) {
            if (token !== notesLoadToken) return;
            if (!cached?.payload) {
                showToast(err.message || 'Failed to load calendar notes.', 'error');
                render();
            }
        }
    }

    function hydrateBootNotes() {
        const boot = window.__SK_FED_CALENDAR_BOOT__;
        if (!boot || !Array.isArray(boot.notes)) {
            return;
        }
        const year = current.getFullYear();
        const month = current.getMonth() + 1;
        if (Number(boot.year) !== year || Number(boot.month) !== month) {
            return;
        }
        const payload = { data: boot.notes };
        window.SkLocalCache?.set(notesCacheKey(year, month), payload);
        applyNotesPayload(payload);
    }

    prevBtn.addEventListener('click', () => {
        current.setMonth(current.getMonth() - 1);
        loadNotes();
    });
    nextBtn.addEventListener('click', () => {
        current.setMonth(current.getMonth() + 1);
        loadNotes();
    });
    jumpBtn.addEventListener('click', () => openJumpModal());

    function render() {
        grid.innerHTML = '';
        const year = current.getFullYear();
        const monthIndex = current.getMonth();

        monthLabel.textContent = `${monthNames[monthIndex]} ${year}`;

        const firstDay = new Date(year, monthIndex, 1);
        const startWeekday = firstDay.getDay();
        const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

        const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        weekdayNames.forEach((name) => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = name;
            grid.appendChild(header);
        });

        for (let i = 0; i < startWeekday; i++) grid.appendChild(document.createElement('div'));

        const monthName = monthNames[monthIndex];

        for (let day = 1; day <= daysInMonth; day++) {
            const cell = document.createElement('div');
            const dateKey = `${year}-${monthIndex}-${day}`;
            const note = notes[dateKey];
            const hasNote = note && (note.title || note.content);
            const isTodayCell = dateKey === todayKey;
            const isPast = isPastDate(year, monthIndex, day);
            const canEdit = canEditDate(year, monthIndex, day);
            const isPastYear = year < currentYear;
            const isNextYear = year > currentYear;

            cell.className = 'calendar-day';
            if (isTodayCell) cell.classList.add('is-today');
            if (hasNote) cell.classList.add('has-notes');
            if (isPast || isPastYear || isNextYear) cell.classList.add('is-past');
            if (!hasNote && (isPast || isPastYear || isNextYear)) {
                cell.classList.add('is-disabled');
            }
            if (hasNote && !canEdit) {
                cell.classList.add('is-view-only');
            }

            const dayNumber = document.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = day;
            cell.appendChild(dayNumber);

            const preview = document.createElement('div');
            preview.className = 'calendar-day-notes-preview';
            preview.textContent = hasNote ? truncateCalendarPreview(note.title || note.content || '') : '';
            preview.title = hasNote ? (note.title || note.content || '') : '';
            cell.appendChild(preview);

            const addLabel = document.createElement('div');
            addLabel.className = 'calendar-day-add';
            if (hasNote && !canEdit) {
                addLabel.textContent = 'View note';
            } else if (!hasNote && isPast) {
                addLabel.textContent = 'Past date';
            } else if (!hasNote && !canModifyNote(year)) {
                addLabel.textContent = isPastYear ? 'Past year' : 'Next year';
            } else if (hasNote) {
                addLabel.textContent = 'Edit note';
            } else {
                addLabel.textContent = 'Add note';
            }
            cell.appendChild(addLabel);

            cell.setAttribute('role', 'button');
            cell.setAttribute('tabindex', hasNote || (!isPast && canModifyNote(year)) ? '0' : '-1');
            cell.setAttribute(
                'aria-label',
                !hasNote && isPast
                    ? `Past date ${monthName} ${day}, ${year}. Notes cannot be added.`
                    : hasNote && !canEdit
                        ? `View note for ${monthName} ${day}, ${year}`
                        : `${hasNote ? 'Edit' : 'Add'} note for ${monthName} ${day}, ${year}`
            );
            cell.addEventListener('click', () => handleDayClick(dateKey, day, monthName, year, monthIndex));
            cell.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    handleDayClick(dateKey, day, monthName, year, monthIndex);
                }
            });
            grid.appendChild(cell);
        }
    }

    function openJumpModal() {
        let overlay = document.getElementById('calendar-jump-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'calendar-jump-overlay';
            overlay.className = 'calendar-jump-overlay';
            overlay.innerHTML = `
                <div class="calendar-jump-modal">
                    <h3 class="calendar-jump-title">Jump to date</h3>
                    <p class="calendar-jump-display"></p>
                    <div class="calendar-jump-columns">
                        <div class="calendar-jump-col" data-type="month">
                            <div class="calendar-jump-col-inner"></div>
                        </div>
                        <div class="calendar-jump-col" data-type="day">
                            <div class="calendar-jump-col-inner"></div>
                        </div>
                        <div class="calendar-jump-col" data-type="year">
                            <div class="calendar-jump-col-inner"></div>
                        </div>
                    </div>
                    <div class="calendar-jump-actions">
                        <button type="button" class="calendar-jump-cancel">Cancel</button>
                        <button type="button" class="calendar-jump-ok">OK</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        let selMonth = current.getMonth();
        let selDay = Math.min(current.getDate(), 28);
        let selYear = current.getFullYear();

        function clampDay() {
            const maxDay = new Date(selYear, selMonth + 1, 0).getDate();
            selDay = Math.min(selDay, maxDay);
        }

        const displayEl = overlay.querySelector('.calendar-jump-display');
        const monthCol = overlay.querySelector('.calendar-jump-col[data-type="month"] .calendar-jump-col-inner');
        const dayCol = overlay.querySelector('.calendar-jump-col[data-type="day"] .calendar-jump-col-inner');
        const yearCol = overlay.querySelector('.calendar-jump-col[data-type="year"] .calendar-jump-col-inner');

        function updateDisplay() {
            const d = new Date(selYear, selMonth, selDay);
            displayEl.textContent = d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }

        function renderColumns() {
            clampDay();
            monthCol.innerHTML = '';
            for (let i = 0; i < 12; i++) {
                const item = document.createElement('div');
                item.className = 'calendar-jump-item' + (i === selMonth ? ' selected' : '');
                item.textContent = monthNamesShort[i];
                item.dataset.value = i;
                item.addEventListener('click', () => {
                    selMonth = i;
                    clampDay();
                    renderColumns();
                    updateDisplay();
                });
                monthCol.appendChild(item);
            }

            dayCol.innerHTML = '';
            const daysInMonth = new Date(selYear, selMonth + 1, 0).getDate();
            for (let d = 1; d <= daysInMonth; d++) {
                const item = document.createElement('div');
                item.className = 'calendar-jump-item' + (d === selDay ? ' selected' : '');
                item.textContent = String(d).padStart(2, '0');
                item.dataset.value = d;
                item.addEventListener('click', () => {
                    selDay = d;
                    renderColumns();
                    updateDisplay();
                });
                dayCol.appendChild(item);
            }

            yearCol.innerHTML = '';
            for (let y = MIN_YEAR; y <= 2100; y++) {
                const item = document.createElement('div');
                item.className = 'calendar-jump-item' + (y === selYear ? ' selected' : '');
                item.textContent = y;
                item.dataset.value = y;
                item.addEventListener('click', () => {
                    selYear = y;
                    clampDay();
                    renderColumns();
                    updateDisplay();
                });
                yearCol.appendChild(item);
            }

            monthCol.parentElement.scrollTop = selMonth * 36;
            dayCol.parentElement.scrollTop = (selDay - 1) * 36;
            yearCol.parentElement.scrollTop = (selYear - MIN_YEAR) * 36;
        }

        function hide() {
            overlay.classList.remove('show');
            overlay.querySelector('.calendar-jump-cancel').removeEventListener('click', onCancel);
            overlay.querySelector('.calendar-jump-ok').removeEventListener('click', onOk);
            overlay.removeEventListener('click', onBackdrop);
        }

        function onOk() {
            current.setFullYear(selYear);
            current.setMonth(selMonth);
            current.setDate(1);
            hide();
            loadNotes();
        }

        function onCancel() { hide(); }
        function onBackdrop(e) { if (e.target === overlay) hide(); }

        renderColumns();
        updateDisplay();

        overlay.querySelector('.calendar-jump-cancel').addEventListener('click', onCancel);
        overlay.querySelector('.calendar-jump-ok').addEventListener('click', onOk);
        overlay.addEventListener('click', onBackdrop);

        overlay.classList.add('show');
    }

    let backdrop, modal, titleInput, contentArea, charCounter, closeBtn, cancelBtn, saveBtn, editBtn, delBtn, toggleBtn;
    let dateKey, isEditMode, originalNote, activeYear, activeMonthIndex, activeDay;

    function updateCharCounter() {
        if (!charCounter) return;
        const contentLen = contentArea ? contentArea.value.length : 0;
        const titleLen = titleInput ? titleInput.value.length : 0;
        charCounter.textContent = `Title: ${titleLen} / ${TITLE_MAX} · Content: ${contentLen} / ${CONTENT_MAX}`;
        charCounter.classList.toggle('is-over', contentLen > CONTENT_MAX || titleLen > TITLE_MAX);
    }

    function switchToViewMode() {
        isEditMode = false;
        const note = notes[dateKey] || {};
        titleInput.value = note.title || '';
        titleInput.readOnly = true;
        contentArea.value = note.content || '';
        contentArea.readOnly = true;
        if (charCounter) charCounter.style.display = 'none';
        cancelBtn.style.display = 'none';
        saveBtn.style.display = 'none';
        editBtn.style.display = canEditDate(activeYear, activeMonthIndex, activeDay) ? '' : 'none';
        delBtn.style.display = canEditDate(activeYear, activeMonthIndex, activeDay) ? '' : 'none';
    }

    function switchToEditMode() {
        isEditMode = true;
        const note = notes[dateKey] || {};
        originalNote = { title: note.title || '', content: note.content || '' };
        titleInput.readOnly = false;
        contentArea.readOnly = false;
        if (charCounter) charCounter.style.display = '';
        updateCharCounter();
        cancelBtn.style.display = '';
        saveBtn.style.display = '';
        editBtn.style.display = 'none';
        delBtn.style.display = 'none';
    }

    function openEditor(dateKeyParam, day, monthLabelText, year, monthIndex) {
        dateKey = dateKeyParam;
        activeYear = year;
        activeMonthIndex = monthIndex;
        activeDay = day;

        const note = notes[dateKey];
        const hasNote = note && (note.title || note.content);

        let backdropEl = document.querySelector('.calendar-modal-backdrop');
        if (!backdropEl) {
            backdropEl = document.createElement('div');
            backdropEl.className = 'calendar-modal-backdrop';
            backdropEl.innerHTML = `
                <div class="calendar-modal">
                    <div class="calendar-modal-header">
                        <h2 class="calendar-modal-title"></h2>
                        <div class="calendar-modal-window-controls">
                            <button type="button" class="modal-toggle-btn" aria-label="Maximize">□</button>
                            <button type="button" class="calendar-modal-close" aria-label="Close">&times;</button>
                        </div>
                    </div>
                    <div class="calendar-modal-body">
                        <label class="calendar-note-label">Title</label>
                        <input type="text" class="calendar-note-title-input" placeholder="Note title..." maxlength="${TITLE_MAX}" />
                        <label class="calendar-note-label">Content</label>
                        <textarea class="calendar-note-content" placeholder="Write your note..." maxlength="${CONTENT_MAX}"></textarea>
                        <div class="calendar-note-char-counter">Title: 0 / ${TITLE_MAX} · Content: 0 / ${CONTENT_MAX}</div>
                        <div class="calendar-modal-actions">
                            <button type="button" class="btn-secondary calendar-modal-cancel" style="display:none">Cancel</button>
                            <button type="button" class="btn-primary calendar-modal-save" style="display:none">Save</button>
                            <button type="button" class="modal-action-btn edit" style="display:none">Edit</button>
                            <button type="button" class="modal-action-btn delete" style="display:none">Delete</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(backdropEl);
        }

        backdrop = backdropEl;
        modal = backdrop.querySelector('.calendar-modal');
        modal.querySelector('.calendar-modal-title').textContent = `Notes for ${monthLabelText} ${day}`;
        titleInput = modal.querySelector('.calendar-note-title-input');
        contentArea = modal.querySelector('.calendar-note-content');
        charCounter = modal.querySelector('.calendar-note-char-counter');
        closeBtn = modal.querySelector('.calendar-modal-close');
        cancelBtn = modal.querySelector('.calendar-modal-cancel');
        saveBtn = modal.querySelector('.calendar-modal-save');
        editBtn = modal.querySelector('.modal-action-btn.edit');
        delBtn = modal.querySelector('.modal-action-btn.delete');
        toggleBtn = modal.querySelector('.modal-toggle-btn');

        titleInput.value = note ? (note.title || '') : '';
        contentArea.value = note ? (note.content || '') : '';
        originalNote = note ? { title: note.title || '', content: note.content || '' } : { title: '', content: '' };

        if (hasNote) {
            switchToViewMode();
        } else {
            switchToEditMode();
        }

        backdrop.classList.remove('modal-maximized');
        modal.classList.remove('modal-maximized');
        toggleBtn.textContent = '□';

        contentArea.oninput = updateCharCounter;
        titleInput.oninput = updateCharCounter;

        function hide() {
            backdrop.classList.remove('show', 'modal-maximized');
            modal.classList.remove('modal-maximized');
            closeBtn.removeEventListener('click', onClose);
            cancelBtn.removeEventListener('click', onCancel);
            saveBtn.removeEventListener('click', onSave);
            editBtn.removeEventListener('click', onEditClick);
            delBtn.removeEventListener('click', onDelete);
            toggleBtn.removeEventListener('click', onToggle);
            backdrop.removeEventListener('click', onBackdrop);
            contentArea.oninput = null;
            if (titleInput) titleInput.oninput = null;
        }

        function isNoteDirty() {
            if (!isEditMode || !originalNote) {
                return false;
            }
            return titleInput.value.trim() !== originalNote.title
                || contentArea.value.trim() !== originalNote.content;
        }

        function confirmDiscardIfDirty() {
            if (!isNoteDirty()) {
                return Promise.resolve(true);
            }
            return showConfirm({
                title: 'Unsaved Changes',
                message: 'Discard unsaved changes?',
                confirmText: 'Discard',
                cancelText: 'Stay',
                confirmClass: 'confirm-edit',
                theme: 'edit',
            });
        }

        async function onClose() {
            if (!(await confirmDiscardIfDirty())) {
                return;
            }
            hide();
        }

        function onBackdrop(e) {
            if (e.target === backdrop) onClose();
        }

        function onEditClick() {
            if (!canEditDate(activeYear, activeMonthIndex, activeDay)) {
                showToast('This note cannot be edited.', 'error');
                return;
            }
            switchToEditMode();
            titleInput.focus();
        }

        async function onDelete() {
            if (!canEditDate(activeYear, activeMonthIndex, activeDay)) {
                showToast('This note cannot be deleted.', 'error');
                return;
            }

            const ok = await showConfirm({
                title: 'Delete Note',
                message: 'Delete this note?',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                confirmClass: 'confirm-danger',
                theme: 'delete',
            });
            if (!ok) return;

            const noteId = notes[dateKey]?.id;
            if (!noteId) return;

            setButtonLoading(delBtn, true, 'Deleting...');

            try {
                await calendarApiFetch(`/api/calendar/notes/${noteId}`, { method: 'DELETE' });
                delete notes[dateKey];
                persistCurrentMonthCache();
                hide();
                render();
                showToast('Note deleted.');
            } catch (err) {
                showToast(err.message || 'Failed to delete note.', 'error');
            } finally {
                setButtonLoading(delBtn, false);
            }
        }

        async function onSave() {
            const title = titleInput.value.trim();
            const content = contentArea.value.trim();

            if (!title) {
                showToast('Title is required.', 'error');
                return;
            }
            if (title.length > TITLE_MAX) {
                showToast(`Title must be ${TITLE_MAX} characters or less.`, 'error');
                return;
            }
            if (!content) {
                showToast('Content is required.', 'error');
                return;
            }
            if (content.length > CONTENT_MAX) {
                showToast(`Content must be ${CONTENT_MAX} characters or less.`, 'error');
                return;
            }

            if (!canEditDate(activeYear, activeMonthIndex, activeDay) && !notes[dateKey]?.id) {
                showToast('Cannot add a note for this date.', 'error');
                return;
            }

            const hadNote = !!notes[dateKey]?.id;
            if (hadNote) {
                const ok = await showConfirm({
                    title: 'Save Changes',
                    message: 'Are you sure you want to save changes?',
                    confirmText: 'Save',
                    cancelText: 'Cancel',
                    confirmClass: 'confirm-edit',
                    theme: 'edit',
                });
                if (!ok) return;
            }

            setButtonLoading(saveBtn, true, hadNote ? 'Saving...' : 'Adding...');

            const isoDate = dateKeyToIso(activeYear, activeMonthIndex, activeDay);

            try {
                let saved;
                if (hadNote) {
                    const json = await calendarApiFetch(`/api/calendar/notes/${notes[dateKey].id}`, {
                        method: 'PUT',
                        body: JSON.stringify({ title, content }),
                    });
                    saved = json.data;
                } else {
                    const json = await calendarApiFetch('/api/calendar/notes', {
                        method: 'POST',
                        body: JSON.stringify({ note_date: isoDate, title, content }),
                    });
                    saved = json.data;
                }

                notes[dateKey] = {
                    id: saved.id,
                    title: saved.title,
                    content: saved.content,
                };
                persistCurrentMonthCache();
                originalNote = { title, content };
                switchToViewMode();
                render();
                showToast(hadNote ? 'Note updated.' : 'Note saved.');
            } catch (err) {
                showToast(err.message || 'Failed to save note.', 'error');
            } finally {
                setButtonLoading(saveBtn, false);
            }
        }

        async function onCancel() {
            if (!(await confirmDiscardIfDirty())) {
                return;
            }
            titleInput.value = originalNote.title;
            contentArea.value = originalNote.content;
            updateCharCounter();
            if (notes[dateKey]) switchToViewMode();
            else hide();
        }

        function onToggle() {
            const isMax = backdrop.classList.toggle('modal-maximized');
            modal.classList.toggle('modal-maximized', isMax);
            toggleBtn.textContent = isMax ? '⧉' : '□';
        }

        closeBtn.addEventListener('click', onClose);
        cancelBtn.addEventListener('click', onCancel);
        saveBtn.addEventListener('click', onSave);
        editBtn.addEventListener('click', onEditClick);
        delBtn.addEventListener('click', onDelete);
        toggleBtn.addEventListener('click', onToggle);
        backdrop.addEventListener('click', onBackdrop);

        backdrop.classList.add('show');
        updateCharCounter();
        (titleInput.value ? contentArea : titleInput).focus();
    }

    render();
    hydrateBootNotes();
    loadNotes();
}
