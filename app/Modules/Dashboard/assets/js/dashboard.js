// ── Stat Card Scroll ──
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-card-link').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');
            const target = document.getElementById(sectionId);
            if (target) {
                const offset = 80;
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });
});

// ── CALENDAR ──────────────────────────────────────────────────
(function () {
    const STORAGE_KEY = 'skfed_calendar_events';
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const dayHeaders = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const typeIcons  = { event:'📅', task:'✅', meeting:'🤝', deadline:'⚠️' };

    const today = new Date();
    let calYear  = today.getFullYear();
    let calMonth = today.getMonth();
    let selectedDate = _fmt(today);
    let editingId = null;

    function _fmt(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2,'0') + '-' +
            String(d.getDate()).padStart(2,'0');
    }

    function loadEvents() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch { return []; }
    }

    function saveEvents(events) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(events));
    }

    function clearFieldError(inputId, errId) {
        var inp = document.getElementById(inputId);
        var err = document.getElementById(errId);
        if (inp) inp.classList.remove('form-input-error');
        if (err) { err.textContent = ''; err.style.display = 'none'; }
    }

    // Clear errors on input — setup after DOM ready
    setTimeout(function() {
        ['cal-event-title','cal-event-date','cal-event-start','cal-event-end'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                var errMap = {'cal-event-title':'err-title','cal-event-date':'err-date','cal-event-start':'err-start','cal-event-end':'err-end'};
                el.addEventListener('input', function() { clearFieldError(id, errMap[id]); });
            }
        });
    }, 100);

    function renderCalendar() {
        const label = document.getElementById('cal-month-label');
        const grid  = document.getElementById('cal-grid');
        if (!label || !grid) return;

        label.textContent = monthNames[calMonth] + ' ' + calYear;

        // Auto-mark past events as done
        var allEvents = loadEvents();
        var todayMidnight = new Date(); todayMidnight.setHours(0,0,0,0);
        var changed = false;
        allEvents.forEach(function(ev) {
            var evDate = new Date(ev.date + 'T00:00:00');
            if (evDate < todayMidnight && ev.status === 'upcoming') {
                ev.status = 'done';
                changed = true;
            }
        });
        if (changed) saveEvents(allEvents);

        // Build a map: date -> array of events
        var eventsByDate = {};
        allEvents.forEach(function(ev) {
            if (!eventsByDate[ev.date]) eventsByDate[ev.date] = [];
            eventsByDate[ev.date].push(ev);
        });

        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        const prevDays = new Date(calYear, calMonth, 0).getDate();

        let html = dayHeaders.map(d => '<div class="cal-day-header">' + d + '</div>').join('');

        // prev month filler
        for (let i = firstDay - 1; i >= 0; i--) {
            html += '<div class="cal-day other-month"><span class="cal-day-num">' + (prevDays - i) + '</span></div>';
        }

        // current month
        for (var d = 1; d <= daysInMonth; d++) {
            var dateStr = calYear + '-' + String(calMonth + 1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            var isToday    = dateStr === _fmt(today);
            var isSelected = dateStr === selectedDate;
            var dayEvents  = eventsByDate[dateStr] || [];
            var hasEvents  = dayEvents.length > 0;
            var isPast     = new Date(dateStr + 'T00:00:00') < todayMidnight;
            var cls = ['cal-day',
                isToday    ? 'today'      : '',
                isSelected ? 'selected'   : '',
                hasEvents  ? 'has-events' : '',
                isPast     ? 'past-day'   : ''
            ].filter(Boolean).join(' ');
            var clickHandler = isPast ? '' : 'onclick="calSelectDay(\'' + dateStr + '\')"';

                // Build icon column + hover popup for events on this day
            var iconsHtml = '';
            var popupHtml = '';
            if (hasEvents) {
                iconsHtml = '<div class="cal-day-icons">';
                dayEvents.slice(0, 3).forEach(function(ev) {
                    var icon = typeIcons[ev.type] || '📅';
                    var escapedTitle = ev.title.replace(/'/g, '&#39;').replace(/"/g, '&quot;');
                    iconsHtml += '<span class="cal-day-icon" title="' + escapedTitle + '">' + icon + '</span>';
                });
                if (dayEvents.length > 3) {
                    iconsHtml += '<span class="cal-day-icon-more">+' + (dayEvents.length - 3) + '</span>';
                }
                iconsHtml += '</div>';

                // Build popup content (rendered via JS on hover)
                var popupContent = '';
                dayEvents.forEach(function(ev) {
                    var icon = typeIcons[ev.type] || '📅';
                    var time = ev.start ? ev.start + (ev.end ? '–' + ev.end : '') : '';
                    popupContent += '<div class="cal-popup-item">' +
                        '<span class="cal-popup-icon">' + icon + '</span>' +
                        '<div class="cal-popup-info">' +
                            '<div class="cal-popup-title">' + ev.title + '</div>' +
                            (time ? '<div class="cal-popup-time">' + time + '</div>' : '') +
                        '</div>' +
                        '</div>';
                });

                var escapedPopup = popupContent.replace(/"/g, '&quot;');
                iconsHtml = '<div class="cal-day-icons" ' +
                    'onmouseenter="showCalPopup(event, this)" ' +
                    'onmouseleave="hideCalPopup()" ' +
                    'data-popup="' + escapedPopup + '">' +
                    iconsHtml.replace('<div class="cal-day-icons">', '').replace(/<\/div>$/, '') +
                    '</div>';
            }

            html += '<div class="' + cls + '" ' + clickHandler + '>' +
                '<span class="cal-day-num">' + d + '</span>' +
                iconsHtml +
                '</div>';
        }

        // next month filler
        const total = firstDay + daysInMonth;
        const remaining = total % 7 === 0 ? 0 : 7 - (total % 7);
        for (let d = 1; d <= remaining; d++) {
            html += '<div class="cal-day other-month"><span class="cal-day-num">' + d + '</span></div>';
        }

        grid.innerHTML = html;
    }

    function renderDayEvents() {
        const container = document.getElementById('cal-events-today');
        if (!container) return;
        const events = loadEvents().filter(e => e.date === selectedDate);
        const dateLabel = selectedDate === _fmt(today) ? 'Today' : selectedDate;

        if (!events.length) {
            var todayCheck = new Date(); todayCheck.setHours(0,0,0,0);
            var selDate = new Date(selectedDate + 'T00:00:00');
            var addBtn = selDate > todayCheck
                ? '<button onclick="openAddEventModal(\'' + selectedDate + '\')" style="background:none;border:none;color:#213F99;font-weight:600;cursor:pointer;font-size:13px;">+ Add one</button>'
                : '';
            container.innerHTML = '<div class="cal-no-events">No events. ' + addBtn + '</div>';
            return;
        }

        var html = '';
        events.forEach(function(ev) {
            var time = ev.start ? (ev.start + (ev.end ? ' – ' + ev.end : '')) : '';
            var timeMeta = time ? '<div class="cal-event-meta">' + time + '</div>' : '';
            var notesMeta = ev.notes ? '<div class="cal-event-meta">' + ev.notes + '</div>' : '';
            html += '<div class="cal-event-item" onclick="openEditEventModal(\'' + ev.id + '\')">' +
                '<span class="cal-event-dot ' + ev.type + '"></span>' +
                '<div class="cal-event-info">' +
                    '<div class="cal-event-title-text">' + (typeIcons[ev.type] || '📅') + ' ' + ev.title + '</div>' +
                    timeMeta + notesMeta +
                '</div>' +
                '<button class="cal-event-delete" onclick="event.stopPropagation();deleteCalEvent(\'' + ev.id + '\')" title="Delete"><i class="fas fa-trash-alt"></i></button>' +
                '</div>';
        });
        container.innerHTML = html;
    }

    window.calChangeMonth = function (dir) {
        calMonth += dir;
        if (calMonth < 0)  { calMonth = 11; calYear--; }
        if (calMonth > 11) { calMonth = 0;  calYear++; }
        renderCalendar();
    };

    window.calSelectDay = function (dateStr) {
        selectedDate = dateStr;
        renderCalendar();
    };

    window.openAddEventModal = function (dateStr) {
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        var minDate = tomorrow.getFullYear() + '-' +
            String(tomorrow.getMonth() + 1).padStart(2,'0') + '-' +
            String(tomorrow.getDate()).padStart(2,'0');

        if (dateStr) {
            var picked = new Date(dateStr + 'T00:00:00');
            var todayMidnight = new Date(); todayMidnight.setHours(0,0,0,0);
            if (picked <= todayMidnight) return;
        }

        editingId = null;
        document.getElementById('cal-modal-title').textContent = 'Add Event';
        document.getElementById('cal-event-title').value    = '';
        document.getElementById('cal-event-date').value     = dateStr || '';
        document.getElementById('cal-event-date').min       = minDate;
        document.getElementById('cal-event-start').value    = '';
        document.getElementById('cal-event-end').value      = '';
        document.getElementById('cal-event-type').value     = 'event';
        document.getElementById('cal-event-location').value = '';
        document.getElementById('cal-event-notes').value    = '';
        document.getElementById('cal-status-group').style.display = 'none';
        // field errors cleared above
        clearFieldError('cal-event-title','err-title');
        clearFieldError('cal-event-date','err-date');
        clearFieldError('cal-event-start','err-start');
        clearFieldError('cal-event-end','err-end');
        document.getElementById('calEventModal').classList.add('show');
    };

    window.openEditEventModal = function (id) {
        var ev = loadEvents().find(function(e) { return e.id === id; });
        if (!ev) return;
        editingId = id;
        document.getElementById('cal-modal-title').textContent    = 'Edit Event';
        document.getElementById('cal-event-title').value          = ev.title;
        document.getElementById('cal-event-date').value           = ev.date;
        document.getElementById('cal-event-date').min             = '';
        document.getElementById('cal-event-start').value          = ev.start    || '';
        document.getElementById('cal-event-end').value            = ev.end      || '';
        document.getElementById('cal-event-type').value           = ev.type     || 'event';
        document.getElementById('cal-event-location').value       = ev.location || '';
        document.getElementById('cal-event-notes').value          = ev.notes    || '';
        document.getElementById('cal-event-status').value         = ev.status   || 'upcoming';
        document.getElementById('cal-status-group').style.display = 'block';
        // field errors cleared above
        document.getElementById('calEventModal').classList.add('show');
    };

    window.closeCalEventModal = function () {
        document.getElementById('calEventModal').classList.remove('show');
        editingId = null;
    };

    window.saveCalEvent = function () {
        var title = document.getElementById('cal-event-title').value.trim();
        var date  = document.getElementById('cal-event-date').value;
        var start = document.getElementById('cal-event-start').value;
        var end   = document.getElementById('cal-event-end').value;

        // Clear all field errors
        ['err-title','err-date','err-start','err-end'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) { el.textContent = ''; el.style.display = 'none'; }
        });
        ['cal-event-title','cal-event-date','cal-event-start','cal-event-end'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('form-input-error');
        });

        function fieldErr(inputId, errId, msg) {
            var inp = document.getElementById(inputId);
            var err = document.getElementById(errId);
            if (inp) inp.classList.add('form-input-error');
            if (err) { err.textContent = msg; err.style.display = 'flex'; }
        }

        var valid = true;

        if (!title) { fieldErr('cal-event-title','err-title','Event title is required.'); valid = false; }
        if (!date)  { fieldErr('cal-event-date','err-date','Please select a date.'); valid = false; }

        if (!editingId && date) {
            var picked = new Date(date + 'T00:00:00');
            var todayMidnight = new Date(); todayMidnight.setHours(0,0,0,0);
            if (picked <= todayMidnight) {
                fieldErr('cal-event-date','err-date','Only future dates are allowed.');
                valid = false;
            }
        }

        if (start && (start < '07:00' || start > '22:00')) {
            fieldErr('cal-event-start','err-start','Must be between 7:00 AM – 10:00 PM.');
            valid = false;
        }
        if (end && (end < '07:00' || end > '22:00')) {
            fieldErr('cal-event-end','err-end','Must be between 7:00 AM – 10:00 PM.');
            valid = false;
        }
        if (start && end && end <= start) {
            fieldErr('cal-event-end','err-end','End time must be after start time.');
            valid = false;
        }

        // Enforce minimum 30-minute duration
        if (start && end && end > start) {
            var toMins = function(t) { var p = t.split(':'); return parseInt(p[0]) * 60 + parseInt(p[1]); };
            if (toMins(end) - toMins(start) < 30) {
                fieldErr('cal-event-end','err-end','Minimum event duration is 30 minutes.');
                valid = false;
            }
        }

        if (start && date && valid) {
            var newEnd = end || '22:00';
            var conflicts = loadEvents().filter(function(e) {
                if (e.date !== date || e.id === editingId || !e.start) return false;
                var eEnd = e.end || '22:00';
                return start < eEnd && newEnd > e.start;
            });
            if (conflicts.length) {
                var c = conflicts[0];
                fieldErr('cal-event-start','err-start',
                    'Conflicts with "' + c.title + '" (' + c.start + (c.end ? '–' + c.end : '') + ').');
                valid = false;
            }
        }

        if (!valid) return;

        var events = loadEvents();
        var ev = {
            id:       editingId || Date.now().toString(36) + Math.random().toString(36).slice(2),
            title:    title,
            date:     date,
            start:    document.getElementById('cal-event-start').value,
            end:      document.getElementById('cal-event-end').value,
            type:     document.getElementById('cal-event-type').value,
            location: document.getElementById('cal-event-location').value.trim(),
            notes:    document.getElementById('cal-event-notes').value.trim(),
            status:   editingId ? document.getElementById('cal-event-status').value : 'upcoming',
        };

        if (editingId) {
            var idx = events.findIndex(function(e) { return e.id === editingId; });
            if (idx !== -1) events[idx] = ev;
        } else {
            events.push(ev);
        }

        saveEvents(events);
        selectedDate = date;
        calYear  = parseInt(date.split('-')[0]);
        calMonth = parseInt(date.split('-')[1]) - 1;
        closeCalEventModal();
        renderCalendar();
        if (window.refreshUpcomingList) window.refreshUpcomingList();
    };

    window.deleteCalEvent = function (id) {
        const events = loadEvents().filter(e => e.id !== id);
        saveEvents(events);
        renderCalendar();
        if (window.refreshUpcomingList) window.refreshUpcomingList();
    };

    // Close modal on backdrop click
    document.getElementById('calEventModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeCalEventModal();
    });

    // Global body-level popup for calendar day hover
    var _calPopupEl = null;
    function _ensurePopup() {
        if (!_calPopupEl) {
            _calPopupEl = document.createElement('div');
            _calPopupEl.id = 'cal-body-popup';
            _calPopupEl.className = 'cal-day-popup';
            _calPopupEl.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;display:none;';
            document.body.appendChild(_calPopupEl);
        }
        return _calPopupEl;
    }

    window.showCalPopup = function(e, el) {
        var popup = _ensurePopup();
        popup.innerHTML = el.dataset.popup || '';
        popup.style.display = 'block';
        var rect = el.getBoundingClientRect();
        var pw = 200;
        var left = rect.left + rect.width / 2 - pw / 2;
        var top  = rect.top - 8;
        // Keep within viewport
        if (left + pw > window.innerWidth - 8) left = window.innerWidth - pw - 8;
        if (left < 8) left = 8;
        popup.style.width = pw + 'px';
        popup.style.left  = left + 'px';
        // Position above, measure height after setting content
        popup.style.top = '-9999px';
        var ph = popup.offsetHeight;
        top = rect.top - ph - 8;
        if (top < 8) top = rect.bottom + 8; // flip below if no room above
        popup.style.top = top + 'px';
    };

    window.hideCalPopup = function() {
        var popup = _ensurePopup();
        popup.style.display = 'none';
    };

    // Init
    renderCalendar();
})();
