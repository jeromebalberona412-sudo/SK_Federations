document.addEventListener('DOMContentLoaded', function () {
    const feed = window.__DASHBOARD_FEED__ || {};
    const shell = document.getElementById('dashboardActivityApp');
    const activitiesUrl = shell?.dataset.activitiesUrl || feed.activities_url || '';
    const feedUrl = feed.feed_url || '';
    const statsUrl = feed.stats_url || '';
    const REFRESH_MS = 45000;

    const els = {
        activityList: document.getElementById('activityList'),
        eventsList: document.getElementById('eventsList'),
        viewAllBtn: document.getElementById('dashActivityViewAllBtn'),
        modal: document.getElementById('dashActivityModal'),
        modalDialog: document.querySelector('#dashActivityModal .dash-activity-modal-dialog'),
        modalList: document.getElementById('dashActivityModalList'),
        modalPagination: document.getElementById('dashActivityModalPagination'),
    };

    let suppressActivityOpenUntil = 0;

    function isActivityModalOpen(modal) {
        return !!(modal && !modal.hidden && modal.classList.contains('is-open'));
    }

    function showActivityModal(modal) {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('dash-activity-modal-open');
    }

    function hideActivityModal(modal) {
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.hidden = true;
        modal.setAttribute('hidden', '');
    }

    function suppressActivityReopen() {
        suppressActivityOpenUntil = Date.now() + 500;
    }

    window.closeDashActivityModal = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        closeActivityModal();
    };

    window.closeDashActivityDetailModal = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        closeActivityDetail();
    };

    const modalState = {
        page: 1,
        perPage: 20,
        loading: false,
    };

    let feedRefreshTimer = null;
    let feedRefreshInFlight = false;
    let statsAbortController = null;
    let statsRequestSeq = 0;

    renderActivity(feed.recent_activity || [], els.activityList);
    renderEvents(feed.upcoming_events || []);
    renderTodayReminder(feed.today_reminder || null);
    bindActivityClicks(els.activityList);
    bindActivityClicks(els.modalList);
    seedStatsCacheFromInitial();
    applyLiveDashboardPayload(feed);

    if (feedUrl) {
        startFeedPolling();
    }

    function getDashboardDateRangeParams() {
        if (typeof window.getDashboardDateRange === 'function') {
            return window.getDashboardDateRange();
        }

        const fromInput = document.getElementById('dashDateFrom');
        const toInput = document.getElementById('dashDateTo');
        const filter = document.getElementById('dashDateRangeFilter');
        const today = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const todayIso = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
        let dateFrom = fromInput?.value || filter?.dataset?.defaultFrom || `${today.getFullYear()}-01-01`;
        let dateTo = toInput?.value || filter?.dataset?.defaultTo || todayIso;

        if (dateFrom && dateTo && dateFrom > dateTo) {
            const tmp = dateFrom;
            dateFrom = dateTo;
            dateTo = tmp;
        }

        return { date_from: dateFrom, date_to: dateTo };
    }

    function statsCacheKey(range) {
        return 'sk_fed_dashboard_stats_v5_' + (range.date_from || '') + '_' + (range.date_to || '');
    }

    function seedStatsCacheFromInitial() {
        if (!feed.stats || !window.SkLocalCache) {
            return;
        }
        const range = {
            date_from: feed.date_from || getDashboardDateRangeParams().date_from,
            date_to: feed.date_to || getDashboardDateRangeParams().date_to,
        };
        const key = statsCacheKey(range);
        if (!window.SkLocalCache.get(key)?.payload) {
            window.SkLocalCache.set(key, {
                stats: feed.stats,
                sex_distribution: feed.sex_distribution || null,
                sex_distribution_officials: feed.sex_distribution_officials || null,
                sex_distribution_federations: feed.sex_distribution_federations || null,
                employment_status_distribution: feed.employment_status_distribution || null,
                abyip_overview: feed.abyip_overview || null,
                top_barangays: feed.top_barangays || [],
                recent_activity: feed.recent_activity || [],
                upcoming_events: feed.upcoming_events || [],
                date_from: range.date_from,
                date_to: range.date_to,
            });
        }
    }

    function buildFeedRequestUrl() {
        if (!feedUrl) {
            return '';
        }

        const range = getDashboardDateRangeParams();
        const params = new URLSearchParams({
            date_from: range.date_from || '',
            date_to: range.date_to || '',
        });

        return feedUrl + (feedUrl.includes('?') ? '&' : '?') + params.toString();
    }

    function buildStatsRequestUrl() {
        const endpoint = statsUrl || feedUrl;
        if (!endpoint) {
            return '';
        }
        const range = getDashboardDateRangeParams();
        const params = new URLSearchParams({
            date_from: range.date_from || '',
            date_to: range.date_to || '',
        });
        return endpoint + (endpoint.includes('?') ? '&' : '?') + params.toString();
    }

    function startFeedPolling() {
        feedRefreshTimer = window.setInterval(function () {
            refreshDashboardFeed();
        }, REFRESH_MS);
        window.addEventListener('beforeunload', function () {
            if (feedRefreshTimer) {
                window.clearInterval(feedRefreshTimer);
            }
        });
    }

    async function refreshDashboardStats() {
        const endpoint = buildStatsRequestUrl();
        if (!endpoint) {
            return;
        }

        const requestSeq = ++statsRequestSeq;
        const range = getDashboardDateRangeParams();
        const cacheKey = statsCacheKey(range);
        const cached = window.SkLocalCache?.get(cacheKey);

        if (cached?.payload?.stats) {
            applyLiveDashboardPayload(cached.payload);
        }

        if (statsAbortController) {
            statsAbortController.abort();
        }
        statsAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: statsAbortController?.signal,
            });
            if (!response.ok || requestSeq !== statsRequestSeq) {
                return;
            }
            const payload = await response.json();
            if (requestSeq !== statsRequestSeq) {
                return;
            }
            window.SkLocalCache?.set(cacheKey, payload);
            applyLiveDashboardPayload(payload);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
        }
    }

    async function refreshDashboardFeed(options) {
        const opts = options || {};
        if (!feedUrl || (feedRefreshInFlight && !opts.force)) {
            return;
        }

        if (!opts.force && els.modal && !els.modal.hidden) {
            return;
        }

        feedRefreshInFlight = true;
        const range = getDashboardDateRangeParams();
        const cacheKey = 'sk_fed_dashboard_feed_v5_' + (range.date_from || '') + '_' + (range.date_to || '');
        const cached = window.SkLocalCache?.get(cacheKey);
        if (cached?.payload) {
            renderActivity(cached.payload.recent_activity || [], els.activityList);
            renderEvents(cached.payload.upcoming_events || []);
            renderTodayReminder(cached.payload.today_reminder || null);
            applyLiveDashboardPayload(cached.payload);
        }

        try {
            const response = await fetch(buildFeedRequestUrl(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            window.SkLocalCache?.set(cacheKey, payload);
            if (payload.stats) {
                window.SkLocalCache?.set(statsCacheKey(range), payload);
            }
            renderActivity(payload.recent_activity || [], els.activityList);
            renderEvents(payload.upcoming_events || []);
            renderTodayReminder(payload.today_reminder || null);
            applyLiveDashboardPayload(payload);
        } catch (_) {
            // Keep cached paint when refresh fails.
        } finally {
            feedRefreshInFlight = false;
        }
    }

    window.refreshDashboardFeed = refreshDashboardFeed;
    window.refreshDashboardStats = function (immediate) {
        refreshDashboardStats(Boolean(immediate));
    };

    function applyLiveDashboardPayload(payload) {
        if (!payload) {
            return;
        }
        if (payload.stats) {
            updateStats(payload.stats);
        }
        if (typeof window.applyDashboardDemographics === 'function') {
            window.applyDashboardDemographics(payload);
        }
        if (payload.abyip_overview) {
            applyAbyipOverview(payload.abyip_overview);
        }
        if (payload.top_barangays) {
            applyTopBarangays(payload.top_barangays);
        }
        if (payload.recent_activity) {
            renderActivity(payload.recent_activity, els.activityList);
        }
        if (payload.upcoming_events) {
            renderEvents(payload.upcoming_events);
        }
    }

    function applyAbyipOverview(overview) {
        const subtitle = document.getElementById('dashAbyipSubtitle');
        const list = document.getElementById('dashAbyipList');
        const empty = document.getElementById('dashAbyipEmpty');
        const stats = overview.stats || {};
        const yearLabel = stats.fiscal_year_label || ('CY ' + (stats.fiscal_year || ''));
        const rows = overview.recent || [];

        if (subtitle) {
            subtitle.textContent = 'Barangays that submitted ABYIP — ' + yearLabel;
        }

        if (empty) {
            empty.textContent = 'No ABYIP submissions yet for ' + yearLabel + '.';
            empty.hidden = rows.length > 0;
        }

        if (!list) {
            return;
        }

        list.hidden = rows.length === 0;
        list.innerHTML = rows.map(function (row) {
            const status = String(row.status || 'pending');
            const statusClass = status === 'approved' ? 'is-approved' : (status === 'rejected' ? 'is-rejected' : 'is-pending');
            const statusLabel = status === 'approved' ? 'Approved' : (status === 'rejected' ? 'Rejected' : 'Pending');
            const href = row.detail_url || '#';

            return '<li>'
                + '<a href="' + escapeHtml(href) + '" class="dash-abyip-item" data-no-loading>'
                + '<span class="dash-abyip-item-barangay">' + escapeHtml(row.barangay || 'Unknown') + '</span>'
                + '<span class="dash-abyip-item-date">' + escapeHtml(row.date_submitted || '—') + '</span>'
                + '<span class="dash-abyip-status ' + statusClass + '">' + escapeHtml(statusLabel) + '</span>'
                + '</a></li>';
        }).join('');
    }

    function applyTopBarangays(rows) {
        const body = document.getElementById('dashTopBarangaysBody');
        if (!body) {
            return;
        }

        const recordsUrl = body.getAttribute('data-records-url') || '#';
        const items = Array.isArray(rows) ? rows : [];

        if (!items.length) {
            body.innerHTML = '<p class="dash-empty-note">No youth registration data available yet.</p>';
            return;
        }

        const maxCount = Math.max(1, Number(items[0].count || 0));
        body.innerHTML = items.map(function (row) {
            const rank = Number(row.rank || 0);
            const rankClass = (rank === 1 || rank === 2 || rank === 3) ? ' top-barangay-item--rank-' + rank : '';
            const width = Math.round((Number(row.count || 0) / maxCount) * 100);

            return '<a href="' + escapeHtml(recordsUrl) + '" class="top-barangay-item' + rankClass + '" style="text-decoration:none;cursor:pointer;">'
                + '<div class="top-barangay-rank top-barangay-rank--' + rank + '">' + rank + '</div>'
                + '<div class="top-barangay-info">'
                + '<div class="top-barangay-name">' + escapeHtml(row.barangay || '—') + '</div>'
                + '<div class="top-barangay-count">' + Number(row.count || 0).toLocaleString() + ' youth registered</div>'
                + '</div>'
                + '<div class="top-barangay-bar-wrap">'
                + '<div class="top-barangay-bar top-barangay-bar--' + rank + '" style="width: ' + width + '%;"></div>'
                + '</div></a>';
        }).join('');
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

    document.addEventListener('click', function (event) {
        const detailClose = event.target.closest('[data-dash-activity-detail-close]');
        if (detailClose) {
            event.preventDefault();
            event.stopPropagation();
            closeActivityDetail();
            return;
        }

        const listClose = event.target.closest('[data-dash-activity-close]');
        if (listClose) {
            event.preventDefault();
            event.stopPropagation();
            closeActivityModal();
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        const detailModal = document.getElementById('dashActivityDetailModal');
        if (isActivityModalOpen(detailModal)) {
            event.preventDefault();
            closeActivityDetail();
            return;
        }

        if (isActivityModalOpen(els.modal)) {
            event.preventDefault();
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

    function bindActivityClicks(target) {
        if (!target || target.dataset.activityBound === '1') {
            return;
        }

        target.dataset.activityBound = '1';
        target.addEventListener('click', function (event) {
            if (Date.now() < suppressActivityOpenUntil) {
                return;
            }

            const button = event.target.closest('.activity-item--clickable');
            if (!button) {
                return;
            }

            openActivityDetail({
                text: button.getAttribute('data-activity-text') || '',
                who: button.getAttribute('data-activity-who') || '',
                position: button.getAttribute('data-activity-position') || '',
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
        const who = String(item.who || '');
        const position = String(item.position || '');
        const whoLine = position ? who + ' · ' + position : who;

        return '<button type="button" class="activity-item activity-item-no-icon activity-item--clickable"' +
            ' data-activity-view="' + escapeHtml(item.id || '') + '"' +
            ' data-activity-text="' + escapeHtml(item.text) + '"' +
            ' data-activity-who="' + escapeHtml(who) + '"' +
            ' data-activity-position="' + escapeHtml(position) + '"' +
            ' data-activity-time="' + escapeHtml(item.time) + '">' +
            '<div class="activity-body">' +
            '<strong>' + escapeHtml(item.text) + '</strong>' +
            '<span>' + escapeHtml(whoLine) + '</span>' +
            '</div>' +
            '<div class="activity-time">' + escapeHtml(item.time || '') + '</div>' +
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
        showActivityModal(els.modal);
        fetchModalActivities();
    }

    function closeActivityModal() {
        suppressActivityReopen();
        closeActivityDetail();
        hideActivityModal(els.modal);
        if (!isActivityModalOpen(document.getElementById('dashActivityDetailModal'))) {
            document.body.classList.remove('dash-activity-modal-open');
        }
    }

    function openActivityDetail(item) {
        const modal = document.getElementById('dashActivityDetailModal');
        const textEl = document.getElementById('dashActivityDetailText');
        const whoEl = document.getElementById('dashActivityDetailWho');
        const positionEl = document.getElementById('dashActivityDetailPosition');
        const timeEl = document.getElementById('dashActivityDetailTime');
        if (!modal || !textEl || !whoEl || !timeEl) {
            return;
        }

        textEl.textContent = item.text || '—';
        whoEl.textContent = item.who || '—';
        if (positionEl) {
            positionEl.textContent = item.position || '';
            positionEl.hidden = !item.position;
        }
        timeEl.textContent = item.time || '—';
        showActivityModal(modal);
    }

    function closeActivityDetail() {
        const modal = document.getElementById('dashActivityDetailModal');
        suppressActivityReopen();
        hideActivityModal(modal);
        if (!isActivityModalOpen(els.modal)) {
            document.body.classList.remove('dash-activity-modal-open');
        }
    }

    async function fetchModalActivities() {
        if (!activitiesUrl || modalState.loading || !els.modalList) {
            return;
        }

        modalState.loading = true;

        const range = getDashboardDateRangeParams();
        const params = new URLSearchParams({
            page: String(modalState.page),
            per_page: String(modalState.perPage),
            date_from: range.date_from || '',
            date_to: range.date_to || '',
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
