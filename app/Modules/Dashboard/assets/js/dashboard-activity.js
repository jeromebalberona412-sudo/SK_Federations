document.addEventListener('DOMContentLoaded', function () {
    const feed = window.__DASHBOARD_FEED__ || {};
    const shell = document.getElementById('dashboardActivityApp');
    const activitiesUrl = shell?.dataset.activitiesUrl || feed.activities_url || '';

    const els = {
        activityList: document.getElementById('activityList'),
        eventsList: document.getElementById('eventsList'),
        viewAllBtn: document.getElementById('dashActivityViewAllBtn'),
        modal: document.getElementById('dashActivityModal'),
        modalDialog: document.querySelector('#dashActivityModal .dash-activity-modal-dialog'),
        modalList: document.getElementById('dashActivityModalList'),
        modalSubtitle: document.getElementById('dashActivityModalSubtitle'),
        modalPagination: document.getElementById('dashActivityModalPagination'),
        fullscreenBtn: document.getElementById('dashActivityFullscreenBtn'),
    };

    const modalState = {
        page: 1,
        perPage: 20,
        loading: false,
    };

    renderActivity(feed.recent_activity || [], els.activityList);
    renderEvents(feed.upcoming_events || []);
    renderTodayReminder(feed.today_reminder || null);

    function renderTodayReminder(reminder) {
        const banner = document.getElementById('calendarReminderBanner');
        const textEl = document.getElementById('reminderText');
        if (!banner || !textEl) {
            return;
        }

        if (!reminder || !reminder.title) {
            banner.hidden = true;
            return;
        }

        const dateLabel = reminder.date_label ? reminder.date_label + ' — ' : '';
        textEl.textContent = dateLabel + reminder.title;
        banner.hidden = false;
    }

    if (els.viewAllBtn) {
        els.viewAllBtn.addEventListener('click', openActivityModal);
    }

    document.querySelectorAll('[data-dash-activity-close]').forEach(function (button) {
        button.addEventListener('click', closeActivityModal);
    });

    if (els.fullscreenBtn) {
        els.fullscreenBtn.addEventListener('click', toggleActivityFullscreen);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && els.modal && !els.modal.hidden) {
            closeActivityModal();
        }
    });

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderActivity(items, target) {
        if (!target) {
            return;
        }

        if (!items.length) {
            target.innerHTML = '<p class="dash-empty-msg">No recent activity recorded.</p>';
            return;
        }

        target.innerHTML = items.map(function (item) {
            return buildActivityItem(item);
        }).join('');
    }

    function buildActivityItem(item) {
        const whoLine = item.position
            ? escapeHtml(item.who) + ' &middot; ' + escapeHtml(item.position)
            : escapeHtml(item.who);

        return '<div class="activity-item activity-item-no-icon">' +
            '<div class="activity-body">' +
            '<strong>' + escapeHtml(item.text) + '</strong>' +
            '<span>' + whoLine + '</span>' +
            '</div>' +
            '<div class="activity-time">' + escapeHtml(item.time) + '</div>' +
            '</div>';
    }

    function renderEvents(events) {
        if (!els.eventsList) {
            return;
        }

        if (!events.length) {
            els.eventsList.innerHTML = '<p class="dash-empty-msg">No upcoming calendar notes.</p>';
            return;
        }

        els.eventsList.innerHTML = events.map(function (event) {
            const title = String(event.title || '');
            const displayTitle = title.length > 40 ? title.slice(0, 40).trimEnd() + '…' : title;

            return '<div class="event-item">' +
                '<div class="event-date-box">' +
                '<span class="event-date-day">' + escapeHtml(event.day) + '</span>' +
                '<span class="event-date-mon">' + escapeHtml(event.month_label) + '</span>' +
                '</div>' +
                '<div class="event-body">' +
                '<div class="event-title" title="' + escapeHtml(title) + '">' + escapeHtml(displayTitle) + '</div>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    function openActivityModal() {
        if (!els.modal) {
            return;
        }

        modalState.page = 1;
        setActivityFullscreen(false);
        els.modal.hidden = false;
        document.body.classList.add('dash-activity-modal-open');
        fetchModalActivities();
    }

    function closeActivityModal() {
        if (!els.modal) {
            return;
        }

        setActivityFullscreen(false);
        els.modal.hidden = true;
        document.body.classList.remove('dash-activity-modal-open');
    }

    function setActivityFullscreen(enabled) {
        if (!els.modalDialog || !els.modal) {
            return;
        }

        els.modalDialog.classList.toggle('dash-activity-modal-dialog--fullscreen', enabled);
        els.modal.classList.toggle('dash-activity-modal--fullscreen', enabled);

        if (els.fullscreenBtn) {
            const icon = els.fullscreenBtn.querySelector('.dash-fs-icon');
            if (icon) {
                icon.textContent = enabled ? '⧉' : '□';
            }
            els.fullscreenBtn.setAttribute('aria-label', enabled ? 'Restore down' : 'Enter fullscreen');
        }
    }

    function toggleActivityFullscreen() {
        if (!els.modalDialog) {
            return;
        }

        const isFullscreen = els.modalDialog.classList.contains('dash-activity-modal-dialog--fullscreen');
        setActivityFullscreen(!isFullscreen);
    }

    async function fetchModalActivities() {
        if (!activitiesUrl || modalState.loading || !els.modalList) {
            return;
        }

        modalState.loading = true;
        els.modalList.innerHTML = '<p class="dash-empty-msg">Loading activities...</p>';

        const params = new URLSearchParams({
            page: String(modalState.page),
            per_page: String(modalState.perPage),
        });

        try {
            const response = await fetch(activitiesUrl + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load activities');
            }

            const payload = await response.json();
            const items = payload.data || [];
            const meta = payload.meta || {};

            if (els.modalSubtitle) {
                els.modalSubtitle.textContent = meta.total
                    ? 'Showing ' + meta.from + '–' + meta.to + ' of ' + meta.total + ' activities'
                    : 'No federation activities recorded yet';
            }

            renderActivity(items, els.modalList);
            renderModalPagination(meta);
        } catch (_) {
            els.modalList.innerHTML = '<p class="dash-empty-msg">Unable to load activities. Please try again.</p>';
            if (els.modalPagination) {
                els.modalPagination.innerHTML = '';
            }
        } finally {
            modalState.loading = false;
        }
    }

    function renderModalPagination(meta) {
        if (!els.modalPagination) {
            return;
        }

        const lastPage = Number(meta.last_page || 1);
        const currentPage = Number(meta.current_page || 1);

        if (lastPage <= 1) {
            els.modalPagination.innerHTML = '';
            return;
        }

        els.modalPagination.innerHTML =
            '<button type="button" class="dash-activity-page-btn" data-page="prev"' +
            (currentPage <= 1 ? ' disabled' : '') + '>Previous</button>' +
            '<span class="dash-activity-page-info">Page ' + currentPage + ' of ' + lastPage + '</span>' +
            '<button type="button" class="dash-activity-page-btn" data-page="next"' +
            (currentPage >= lastPage ? ' disabled' : '') + '>Next</button>';

        els.modalPagination.querySelectorAll('[data-page]').forEach(function (button) {
            button.addEventListener('click', function () {
                const direction = button.getAttribute('data-page');
                if (direction === 'prev' && currentPage > 1) {
                    modalState.page = currentPage - 1;
                    fetchModalActivities();
                } else if (direction === 'next' && currentPage < lastPage) {
                    modalState.page = currentPage + 1;
                    fetchModalActivities();
                }
            });
        });
    }
});
