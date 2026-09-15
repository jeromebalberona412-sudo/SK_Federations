'use strict';

(function () {
    var cfg = window.SkFedProfileConfig || {};
    var allPosts = cfg.posts || [];
    var currentFilter = 'all';
    var pollTimer = null;

    window.setSfpFilter = function (btn, filter) {
        currentFilter = filter;
        document.querySelectorAll('#sfpFilterBar .feed-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab === btn);
        });
        if (typeof window.mountFeedPostsList === 'function') {
            window.mountFeedPostsList(allPosts, 'sfpFeedPosts', currentFilter);
        }
    };

    function pollFederationFeed() {
        if (document.hidden) return;
        if (document.getElementById('commentPreviewShell')?.classList.contains('is-open')) return;

        var url = '/api/community-feed?per_page=100&page=1&federation_only=1'
            + (currentFilter !== 'all' ? '&filter=' + encodeURIComponent(currentFilter) : '');

        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.data)) return;
                allPosts = data.data;
                if (typeof window.mountFeedPostsList === 'function') {
                    window.mountFeedPostsList(allPosts, 'sfpFeedPosts', currentFilter);
                }
            })
            .catch(function () {});
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollFederationFeed, Number(cfg.feedPollMs || 30000));
    }

    function initSfpSidebarDrawer() {
        var fab = document.getElementById('sfpSidebarFab');
        var sidebar = document.getElementById('sfpSidebar');
        var backdrop = document.getElementById('sfpSidebarBackdrop');
        var closeBtn = document.getElementById('sfpSidebarClose');
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
        if (!document.getElementById('sfpFeedPosts')) return;

        initSfpSidebarDrawer();

        if (typeof window.mountFeedPostsList === 'function') {
            window.mountFeedPostsList(allPosts, 'sfpFeedPosts', currentFilter);
        }

        startPolling();
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) pollFederationFeed();
        });
    });
})();
