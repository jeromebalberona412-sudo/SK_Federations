'use strict';

(function () {
    var cfg = window.BarangayProfileConfig || {};
    var allPosts = cfg.posts || [];
    var currentFilter = 'all';
    var pollTimer = null;

    function setBfpFilter(btn, filter) {
        currentFilter = filter;
        document.querySelectorAll('#bfpFilterBar .feed-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab === btn);
        });
        if (typeof window.mountFeedPostsList === 'function') {
            window.mountFeedPostsList(allPosts, 'bfpFeedPosts', currentFilter);
        }
    }

    function pollBarangayFeed() {
        if (document.hidden || !cfg.barangayId) return;
        if (document.getElementById('commentPreviewShell')?.classList.contains('is-open')) return;

        var url = '/api/community-feed?per_page=100&page=1&barangay_id=' + encodeURIComponent(cfg.barangayId)
            + (currentFilter !== 'all' ? '&filter=' + encodeURIComponent(currentFilter) : '');

        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.data)) return;
                allPosts = data.data;
                if (typeof window.mountFeedPostsList === 'function') {
                    window.mountFeedPostsList(allPosts, 'bfpFeedPosts', currentFilter);
                }
            })
            .catch(function () {});
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollBarangayFeed, Number(cfg.feedPollMs || 30000));
    }

    function initBfpSidebarDrawer() {
        var fab = document.getElementById('bfpSidebarFab');
        var sidebar = document.getElementById('bfpSidebar');
        var backdrop = document.getElementById('bfpSidebarBackdrop');
        var closeBtn = document.getElementById('bfpSidebarClose');
        if (!fab || !sidebar || !backdrop) return;

        function openDrawer() {
            sidebar.classList.add('drawer-open');
            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDrawer() {
            sidebar.classList.remove('drawer-open');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }

        fab.addEventListener('click', openDrawer);
        closeBtn?.addEventListener('click', closeDrawer);
        backdrop.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDrawer();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('bfpFeedPosts')) return;

        initBfpSidebarDrawer();

        if (typeof window.mountFeedPostsList === 'function') {
            window.mountFeedPostsList(allPosts, 'bfpFeedPosts', currentFilter);
        }

        if (typeof ensureFeedReactionAudio === 'function') {
            ensureFeedReactionAudio();
            document.addEventListener('pointerdown', ensureFeedReactionAudio, { once: true, capture: true });
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest || !e.target.closest('.reaction-wrap')) {
                if (typeof closeAllReactionPickers === 'function') closeAllReactionPickers();
            }
        });

        startPolling();
    });

    window.setBfpFilter = setBfpFilter;
})();
