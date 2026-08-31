document.addEventListener('DOMContentLoaded', function () {
    const feed = window.__DASHBOARD_FEED__ || {};
    const shell = document.getElementById('dashboardActivityApp');
    const activitiesUrl = shell?.dataset.activitiesUrl || feed.activities_url || '';
    const feedUrl = feed.feed_url || '';
    const REFRESH_MS = 45000;

    const els = {
        activityList: document.getElementById('activityList'),
        eventsList: document.getElementById('eventsList'),
        viewAllBtn: document.getElementById('dashActivityViewAllBtn'),
        modal: document.getElementById('dashActivityModal'),
        modalDialog: document.querySelector('#dashActivityModal .dash-activity-modal-dialog'),
        modalList: document.getElementById('dashActivityModalList'),
        modalPagination: document.getElementById('dashActivityModalPagination'),
        fullscreenBtn: document.getElementById('dashActivityFullscreenBtn'),
    };

    const modalState = {
        page: 1,
        perPage: 20,
        loading: false,
    };

    let feedRefreshTimer = null;
    let feedRefreshInFlight = false;

    renderActivity(feed.recent_activity || [], els.activityList);
    renderEvents(feed.upcoming_events || []);
    renderTodayReminder(feed.today_reminder || null);
    bindActivityClicks(els.activityList);
    bindActivityClicks(els.modalList);

    if (feedUrl) {
        startFeedPolling();
    }

    function startFeedPolling() {
        feedRefreshTimer = window.setInterval(refreshDashboardFeed, REFRESH_MS);
        window.addEventListener('beforeunload', function () {
            if (feedRefreshTimer) {
                window.clearInterval(feedRefreshTimer);
            }
        });
    }

    async function refreshDashboardFeed() {
        if (!feedUrl || feedRefreshInFlight) {
            return;
        }

        if (els.modal && !els.modal.hidden) {
            return;
        }

        feedRefreshInFlight = true;
        const cacheKey = 'sk_fed_dashboard_feed_v1';
        const cached = window.SkLocalCache?.get(cacheKey);
        if (cached?.payload) {
            renderActivity(cached.payload.recent_activity || [], els.activityList);
            renderEvents(cached.payload.upcoming_events || []);
            renderTodayReminder(cached.payload.today_reminder || null);
            updateStats(cached.payload.stats || {});
        }

        try {
            const response = await fetch(feedUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            window.SkLocalCache?.set(cacheKey, payload);
            renderActivity(payload.recent_activity || [], els.activityList);
            renderEvents(payload.upcoming_events || []);
            renderTodayReminder(payload.today_reminder || null);
            updateStats(payload.stats || {});
        } catch (_) {
            // Keep cached paint when refresh fails.
        } finally {
            feedRefreshInFlight = false;
        }
    }

    function updateStats(stats) {
        document.querySelectorAll('[data-stat-key]').forEach(function (card) {
            const key = card.getAttribute('data-stat-key');
            if (!key || stats[key] === undefined) {
                return;
            }

            const valueEl = card.querySelector('.stat-value');
            if (valueEl) {
                valueEl.textContent = Number(stats[key] || 0).toLocaleString();
            }
        });
    }

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

    document.querySelectorAll('[data-dash-activity-detail-close]').forEach(function (button) {
        button.addEventListener('click', closeActivityDetail);
    });

    if (els.fullscreenBtn) {
        els.fullscreenBtn.addEventListener('click', toggleActivityFullscreen);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && els.modal && !els.modal.hidden) {
            closeActivityModal();
        }
        if (event.key === 'Escape') {
            closeActivityDetail();
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

    function bindActivityClicks(target) {
        if (!target || target.dataset.activityBound === '1') {
            return;
        }

        target.dataset.activityBound = '1';
        target.addEventListener('click', function (event) {
            const button = event.target.closest('.activity-item--clickable');
            if (!button) {
                return;
            }

            openActivityDetail({
                text: button.getAttribute('data-activity-text') || '',
                who: button.getAttribute('data-activity-who') || '',
                time: button.getAttribute('data-activity-time') || '',
            });
        });
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
        const whoText = item.position
            ? String(item.who || '') + ' · ' + String(item.position || '')
            : String(item.who || '');

        return '<button type="button" class="activity-item activity-item-no-icon activity-item--clickable"' +
            ' data-activity-view="' + escapeHtml(item.id || '') + '"' +
            ' data-activity-text="' + escapeHtml(item.text) + '"' +
            ' data-activity-who="' + escapeHtml(whoText) + '"' +
            ' data-activity-time="' + escapeHtml(item.time) + '">' +
            '<div class="activity-body">' +
            '<strong>' + escapeHtml(item.text) + '</strong>' +
            '<span>' + escapeHtml(whoText) + '</span>' +
            '</div>' +
            '</button>';
    }

    function renderEvents(events) {
        if (!els.eventsList) {
            return;
        }

        if (!events.length) {
            els.eventsList.innerHTML =
                '<div class="dash-events-empty">' +
                '<i class="fas fa-calendar-alt" aria-hidden="true"></i>' +
                '<p class="dash-empty-msg">No upcoming calendar notes.</p>' +
                '</div>';
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

    function openActivityDetail(item) {
        const modal = document.getElementById('dashActivityDetailModal');
        const textEl = document.getElementById('dashActivityDetailText');
        const metaEl = document.getElementById('dashActivityDetailMeta');
        if (!modal || !textEl || !metaEl) {
            return;
        }

        textEl.textContent = item.text || '—';
        metaEl.textContent = [item.who, item.time].filter(Boolean).join(' · ');
        modal.hidden = false;
        document.body.classList.add('dash-activity-modal-open');
    }

    function closeActivityDetail() {
        const modal = document.getElementById('dashActivityDetailModal');
        if (!modal) {
            return;
        }

        modal.hidden = true;
        if (!els.modal || els.modal.hidden) {
            document.body.classList.remove('dash-activity-modal-open');
        }
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
