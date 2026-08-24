document.addEventListener('DOMContentLoaded', function () {
    initNotificationPage();
});

function getNotificationCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postNotificationAction(url) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getNotificationCsrfToken(),
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Notification request failed.');
    }

    return response.json();
}

function initNotificationPage() {
    const list = document.getElementById('notifPageList');
    const markAllBtn = document.getElementById('pageMarkAllBtn');
    const emptyState = document.getElementById('notifPageEmpty');
    const filterBtns = document.querySelectorAll('.notif-filter-btn');
    const totalEl = document.getElementById('totalCount');
    const unreadEl = document.getElementById('unreadCount');
    const readEl = document.getElementById('readCount');

    let currentFilter = 'all';

    function updateStats() {
        if (!list) return;
        const all = list.querySelectorAll('.notif-page-item');
        const unread = list.querySelectorAll('.notif-page-unread');
        const read = all.length - unread.length;

        if (totalEl) totalEl.textContent = all.length;
        if (unreadEl) unreadEl.textContent = unread.length;
        if (readEl) readEl.textContent = read;

        if (typeof updateNotifBadge === 'function') {
            updateNotifBadge(unread.length);
        }
    }

    function applyFilter(filter) {
        if (!list) return;
        const items = list.querySelectorAll('.notif-page-item');
        let visible = 0;

        items.forEach(function (item) {
            const isUnread = item.classList.contains('notif-page-unread');
            let show = false;

            if (filter === 'all') show = true;
            if (filter === 'unread') show = isUnread;
            if (filter === 'read') show = !isUnread;

            item.style.display = show ? 'flex' : 'none';
            if (show) visible++;
        });

        if (emptyState) {
            emptyState.style.display = visible === 0 ? 'flex' : 'none';
        }
    }

    function markAsReadUi(item) {
        if (!item.classList.contains('notif-page-unread')) return;
        item.classList.remove('notif-page-unread');

        const dot = item.querySelector('.notif-page-dot');
        if (dot) dot.remove();

        updateStats();
        applyFilter(currentFilter);
    }

    async function markAsRead(item) {
        const id = item?.dataset?.id;
        if (!id || !item.classList.contains('notif-page-unread')) return;

        try {
            const data = await postNotificationAction(`/api/sk-federations/notifications/${id}/read`);
            markAsReadUi(item);
            if (typeof updateNotifBadge === 'function' && data && typeof data.unread_count === 'number') {
                updateNotifBadge(data.unread_count);
            }
        } catch {
            markAsReadUi(item);
        }
    }

    function navigateToNotification(item) {
        const url = item?.dataset?.actionUrl;
        if (!url) return;
        window.location.href = url;
    }

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter');
            applyFilter(currentFilter);
        });
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', async function () {
            if (!list) return;
            try {
                const data = await postNotificationAction('/api/sk-federations/notifications/read-all');
                if (typeof updateNotifBadge === 'function' && data && typeof data.unread_count === 'number') {
                    updateNotifBadge(data.unread_count);
                }
            } catch {
                if (typeof updateNotifBadge === 'function') {
                    updateNotifBadge(0);
                }
            }
            list.querySelectorAll('.notif-page-unread').forEach(function (item) {
                markAsReadUi(item);
            });
        });
    }

    if (list) {
        list.addEventListener('click', async function (e) {
            const item = e.target.closest('.notif-page-item');
            if (!item) return;
            await markAsRead(item);
            navigateToNotification(item);
        });

        list.addEventListener('keydown', async function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const item = e.target.closest('.notif-page-item');
            if (!item) return;
            e.preventDefault();
            await markAsRead(item);
            navigateToNotification(item);
        });
    }

    updateStats();
    applyFilter('all');
}
