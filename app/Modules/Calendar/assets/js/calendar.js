document.addEventListener('DOMContentLoaded', () => {
    initializeCalendar();
});

const CONTENT_MAX = 1000;
const TITLE_MAX = 50;
const CONTENT_CHARS_PER_LINE_FALLBACK = 36;
const CALENDAR_CELL_TITLE_PREVIEW = 22;

function normalizeNoteContent(text) {
    return String(text || '').replace(/\r\n|\r/g, '\n');
}

/** Title: max 50 chars, no spaces or newlines (spaces stripped silently). */
function sanitizeNoteTitle(text) {
    return String(text || '').replace(/[\s\r\n\u00a0]+/g, '').slice(0, TITLE_MAX);
}

function validateNoteTitle(raw) {
    const title = sanitizeNoteTitle(raw);
    if (!title) {
        return { ok: false, title: '', message: 'Title is required.' };
    }
    if (title.length > TITLE_MAX) {
        return { ok: false, title, message: `Title must be ${TITLE_MAX} characters or less.` };
    }
    return { ok: true, title, message: null };
}

/** Same visual-line rule as Community Feed / Communications chat. */
function estimateNoteCharsPerLine(el) {
    if (!el || !el.clientWidth) return CONTENT_CHARS_PER_LINE_FALLBACK;
    const style = window.getComputedStyle(el);
    const fontSize = parseFloat(style.fontSize) || 14;
    const pad = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
    const width = Math.max(0, el.clientWidth - pad);
    return Math.max(16, Math.floor(width / Math.max(fontSize * 0.55, 7)));
}

function measureNoteContentLength(text, charsPerLine) {
    const cpl = Math.max(8, Number(charsPerLine) || CONTENT_CHARS_PER_LINE_FALLBACK);
    const lines = normalizeNoteContent(text).split('\n');
    let total = 0;
    for (let i = 0; i < lines.length; i += 1) {
        const len = Array.from(lines[i]).length;
        if (i < lines.length - 1) {
            total += Math.max(cpl, len);
        } else {
            total += len;
        }
    }
    return total;
}

function countedNoteLength(text, el) {
    return measureNoteContentLength(text, estimateNoteCharsPerLine(el));
}

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
            if (hasNote) {
                cell.classList.add('has-notes');
                if (isTodayCell) cell.classList.add('notes-today');
                else if (isPast || isPastYear) cell.classList.add('notes-past');
                else cell.classList.add('notes-future');
            }
            if ((isPast || isPastYear || isNextYear) && !hasNote) cell.classList.add('is-past');
            if (isPast && !hasNote) cell.classList.add('is-disabled');
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

        let selMonth = today.getMonth();
        let selDay = today.getDate();
        let selYear = today.getFullYear();

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
            const isTodaySelected = (
                selYear === today.getFullYear()
                && selMonth === today.getMonth()
                && selDay === today.getDate()
            );
            const label = d.toLocaleDateString(undefined, {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            });
            displayEl.textContent = isTodaySelected ? `Today · ${label}` : label;
        }

        function renderColumns() {
            clampDay();
            monthCol.innerHTML = '';
            for (let i = 0; i < 12; i++) {
                const item = document.createElement('div');
                const isTodayMonth = i === today.getMonth() && selYear === today.getFullYear();
                item.className = 'calendar-jump-item'
                    + (i === selMonth ? ' selected' : '')
                    + (isTodayMonth ? ' is-today' : '');
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
                const isTodayDay = (
                    d === today.getDate()
                    && selMonth === today.getMonth()
                    && selYear === today.getFullYear()
                );
                item.className = 'calendar-jump-item'
                    + (d === selDay ? ' selected' : '')
                    + (isTodayDay ? ' is-today' : '');
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
                item.className = 'calendar-jump-item'
                    + (y === selYear ? ' selected' : '')
                    + (y === today.getFullYear() ? ' is-today' : '');
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

            monthCol.parentElement.scrollTop = 0;
            dayCol.parentElement.scrollTop = 0;
            yearCol.parentElement.scrollTop = 0;

            requestAnimationFrame(() => {
                [monthCol, dayCol, yearCol].forEach((colInner) => {
                    const selected = colInner.querySelector('.calendar-jump-item.selected');
                    if (selected) {
                        selected.scrollIntoView({ block: 'center', inline: 'nearest' });
                    }
                });
            });
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
        requestAnimationFrame(() => {
            [monthCol, dayCol, yearCol].forEach((colInner) => {
                const selected = colInner.querySelector('.calendar-jump-item.selected');
                if (selected) selected.scrollIntoView({ block: 'center', inline: 'nearest' });
            });
        });
    }

    let backdrop, modal, titleInput, contentArea, charCounter, titleCounter, closeBtn, cancelBtn, saveBtn, editBtn, delBtn, toggleBtn;
    let dateKey, isEditMode, originalNote, activeYear, activeMonthIndex, activeDay;

    function updateTitleCounter() {
        if (!titleCounter || !titleInput) return;
        const len = sanitizeNoteTitle(titleInput.value).length;
        titleCounter.textContent = `${len} / ${TITLE_MAX}`;
        titleCounter.classList.toggle('is-over', len > TITLE_MAX);
    }

    function updateCharCounter() {
        if (!charCounter || !contentArea) return;
        const len = countedNoteLength(contentArea.value, contentArea);
        charCounter.textContent = `${len} / ${CONTENT_MAX} characters`;
        charCounter.classList.toggle('is-over', len > CONTENT_MAX);
    }

    function bindTitleInputLimit(input) {
        if (!input || input.dataset.titleLimitBound === '1') return;
        input.dataset.titleLimitBound = '1';

        input.addEventListener('beforeinput', (event) => {
            if (event.isComposing || input.readOnly) return;
            const type = event.inputType || '';
            if (type.startsWith('delete') || type === 'historyUndo' || type === 'historyRedo') {
                return;
            }
            if (type === 'insertLineBreak' || type === 'insertParagraph') {
                event.preventDefault();
                return;
            }
            if (typeof event.data === 'string' && /[\s\r\n\u00a0]/.test(event.data)) {
                event.preventDefault();
                return;
            }
            if (typeof event.data === 'string') {
                const value = String(input.value || '');
                const start = input.selectionStart ?? value.length;
                const end = input.selectionEnd ?? start;
                const projected = sanitizeNoteTitle(value.slice(0, start) + event.data + value.slice(end));
                if (projected.length > TITLE_MAX) {
                    event.preventDefault();
                    showToast(`Title must be ${TITLE_MAX} characters or less.`, 'error');
                }
            }
        });

        input.addEventListener('paste', (event) => {
            if (input.readOnly) return;
            event.preventDefault();
            const rawPaste = event.clipboardData?.getData('text') ?? '';
            const pastedClean = sanitizeNoteTitle(rawPaste);
            const value = String(input.value || '');
            const start = input.selectionStart ?? value.length;
            const end = input.selectionEnd ?? start;
            const before = sanitizeNoteTitle(value.slice(0, start));
            const after = sanitizeNoteTitle(value.slice(end));
            const room = Math.max(0, TITLE_MAX - before.length - after.length);
            const insert = pastedClean.slice(0, room);
            const next = sanitizeNoteTitle(before + insert + after);
            input.value = next;
            const caret = Math.min(before.length + insert.length, next.length);
            input.setSelectionRange(caret, caret);
            updateTitleCounter();
            if (pastedClean.length > room) {
                showToast(`Title must be ${TITLE_MAX} characters or less.`, 'error');
            }
        });

        input.addEventListener('input', () => {
            const cleaned = sanitizeNoteTitle(input.value);
            if (input.value !== cleaned) {
                const start = input.selectionStart ?? cleaned.length;
                input.value = cleaned;
                const caret = Math.min(start, cleaned.length);
                input.setSelectionRange(caret, caret);
            }
            updateTitleCounter();
        });
    }

    function wouldExceedNoteLimit(textarea, insertText) {
        const value = String(textarea.value || '');
        const start = textarea.selectionStart ?? value.length;
        const end = textarea.selectionEnd ?? start;
        const nextValue = value.slice(0, start) + insertText + value.slice(end);
        return countedNoteLength(nextValue, textarea) > CONTENT_MAX;
    }

    function bindNoteContentLimit(textarea) {
        if (!textarea || textarea.dataset.lineLimitBound === '1') return;
        textarea.dataset.lineLimitBound = '1';

        textarea.addEventListener('beforeinput', (event) => {
            if (event.isComposing || textarea.readOnly) return;
            const type = event.inputType || '';
            if (type.startsWith('delete') || type === 'historyUndo' || type === 'historyRedo') {
                return;
            }

            let insertText = '';
            if (typeof event.data === 'string') {
                insertText = event.data;
            } else if (type === 'insertLineBreak' || type === 'insertParagraph') {
                insertText = '\n';
            } else if (type === 'insertFromPaste') {
                return;
            } else {
                return;
            }

            if (wouldExceedNoteLimit(textarea, insertText)) {
                event.preventDefault();
                showToast(
                    `Content must be ${CONTENT_MAX} characters or less (new lines count as a full line).`,
                    'error'
                );
                updateCharCounter();
            }
        });

        textarea.addEventListener('paste', (event) => {
            if (textarea.readOnly) return;
            const pasted = event.clipboardData?.getData('text') ?? '';
            if (pasted === '') return;

            event.preventDefault();
            const value = String(textarea.value || '');
            const start = textarea.selectionStart ?? value.length;
            const end = textarea.selectionEnd ?? start;
            const nextValue = value.slice(0, start) + pasted + value.slice(end);

            if (countedNoteLength(nextValue, textarea) > CONTENT_MAX) {
                showToast(
                    `Content must be ${CONTENT_MAX} characters or less (new lines count as a full line).`,
                    'error'
                );
                updateCharCounter();
                return;
            }

            textarea.setRangeText(pasted, start, end, 'end');
            updateCharCounter();
        });
    }

    function switchToViewMode() {
        isEditMode = false;
        const note = notes[dateKey] || {};
        titleInput.value = sanitizeNoteTitle(note.title || '');
        titleInput.readOnly = true;
        contentArea.value = note.content || '';
        contentArea.readOnly = true;
        if (charCounter) charCounter.style.display = 'none';
        if (titleCounter) titleCounter.style.display = 'none';
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
        if (titleCounter) titleCounter.style.display = '';
        updateTitleCounter();
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
                        <input type="text" class="calendar-note-title-input" placeholder="Note title..." maxlength="${TITLE_MAX}" autocomplete="off" />
                        <div class="calendar-note-title-counter">0 / ${TITLE_MAX}</div>
                        <label class="calendar-note-label">Content</label>
                        <textarea class="calendar-note-content" placeholder="Write your note..."></textarea>
                        <div class="calendar-note-char-counter">0 / ${CONTENT_MAX} characters</div>
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
        titleCounter = modal.querySelector('.calendar-note-title-counter');
        charCounter = modal.querySelector('.calendar-note-char-counter');
        closeBtn = modal.querySelector('.calendar-modal-close');
        cancelBtn = modal.querySelector('.calendar-modal-cancel');
        saveBtn = modal.querySelector('.calendar-modal-save');
        editBtn = modal.querySelector('.modal-action-btn.edit');
        delBtn = modal.querySelector('.modal-action-btn.delete');
        toggleBtn = modal.querySelector('.modal-toggle-btn');

        titleInput.value = note ? sanitizeNoteTitle(note.title || '') : '';
        contentArea.value = note ? (note.content || '') : '';
        originalNote = note
            ? { title: sanitizeNoteTitle(note.title || ''), content: note.content || '' }
            : { title: '', content: '' };

        if (hasNote) {
            switchToViewMode();
        } else {
            switchToEditMode();
        }

        backdrop.classList.remove('modal-maximized');
        modal.classList.remove('modal-maximized');
        toggleBtn.textContent = '□';

        contentArea.oninput = updateCharCounter;
        bindNoteContentLimit(contentArea);
        bindTitleInputLimit(titleInput);
        updateTitleCounter();

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
        }

        function isNoteDirty() {
            if (!isEditMode || !originalNote) {
                return false;
            }
            const t = sanitizeNoteTitle(titleInput.value);
            const c = normalizeNoteContent(contentArea.value);
            const origContent = normalizeNoteContent(originalNote.content);
            return t !== sanitizeNoteTitle(originalNote.title) || c !== origContent;
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
            const titleCheck = validateNoteTitle(titleInput.value);
            const content = normalizeNoteContent(contentArea.value);

            if (!titleCheck.ok) {
                showToast(titleCheck.message || 'Title is required.', 'error');
                return;
            }
            const title = titleCheck.title;
            if (content === '' || !/\S/u.test(content)) {
                showToast('Content is required.', 'error');
                return;
            }
            if (countedNoteLength(content, contentArea) > CONTENT_MAX) {
                showToast(
                    `Content must be ${CONTENT_MAX} characters or less (new lines count as a full line).`,
                    'error'
                );
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
            titleInput.value = sanitizeNoteTitle(originalNote.title);
            contentArea.value = originalNote.content;
            updateTitleCounter();
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
