// ── Sidebar dropdown submenus ───────────────────────────────
function isSidebarCollapsed() {
    return document.body.classList.contains('sidebar-collapsed');
}

function isMobileSidebar() {
    return window.matchMedia('(max-width: 1024px)').matches;
}

function getSubmenuToggleButton(submenuId) {
    return document.querySelector('[data-submenu-toggle="' + submenuId + '"]');
}

function setSubmenuOpen(submenuId, open) {
    var submenu = document.getElementById(submenuId);
    var toggle = getSubmenuToggleButton(submenuId);
    var chevron = toggle ? toggle.querySelector('.menu-dropdown-chevron') : null;

    if (!submenu) {
        return;
    }

    submenu.classList.toggle('is-open', open);
    if (toggle) {
        toggle.classList.toggle('active', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (chevron) {
        chevron.classList.toggle('is-open', open);
    }
}

function isSubmenuOpen(submenuId) {
    var submenu = document.getElementById(submenuId);
    return submenu ? submenu.classList.contains('is-open') : false;
}

function collapseSidebarExtras() {
    document.querySelectorAll('.sidebar-submenu.is-open').forEach(function (submenu) {
        setSubmenuOpen(submenu.id, false);
    });
}

function toggleSubmenu(submenuId) {
    if (!isMobileSidebar() && isSidebarCollapsed()) {
        document.body.classList.remove('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');

        window.setTimeout(function () {
            setSubmenuOpen(submenuId, true);
        }, 260);

        return;
    }

    var willOpen = !isSubmenuOpen(submenuId);
    setSubmenuOpen(submenuId, willOpen);

    if (willOpen && !isMobileSidebar()) {
        document.body.classList.remove('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');
    }
}

window.toggleSubmenuDropdown = function (btn, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    var submenuId = btn.getAttribute('data-submenu-toggle');
    if (submenuId) {
        toggleSubmenu(submenuId);
    }
};

window.toggleAccountsMenu = function (event) {
    if (event) {
        event.preventDefault();
    }
    toggleSubmenu('accountsSubmenu');
};

window.toggleArchiveManagementMenu = function (event) {
    if (event) {
        event.preventDefault();
    }
    toggleSubmenu('archiveManagementSubmenu');
};

window.toggleArchiveMenu = function (event) {
    if (event) {
        event.preventDefault();
    }
    toggleSubmenu('archiveSubmenu');
};

function initSidebarNavigation() {
    var overlay = document.querySelector('.sidebar-overlay');
    if (overlay && !overlay.dataset.bound) {
        overlay.dataset.bound = '1';
        overlay.addEventListener('click', function () {
            document.body.classList.remove('sidebar-open');
        });
    }

    syncSidebarForViewport();
}

function syncSidebarForViewport() {
    var mobile = isMobileSidebar();
    var narrowDesktop = window.innerWidth <= 1280;

    if (mobile) {
        document.body.classList.remove('sidebar-collapsed');
        document.body.classList.remove('sidebar-open');
        return;
    }

    document.body.classList.remove('sidebar-open');

    if (narrowDesktop) {
        if (localStorage.getItem('sidebarCollapsed') !== 'false') {
            document.body.classList.add('sidebar-collapsed');
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }

        return;
    }

    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    } else {
        document.body.classList.remove('sidebar-collapsed');
    }
}

var sidebarResizeTimer = null;
window.addEventListener('resize', function () {
    window.clearTimeout(sidebarResizeTimer);
    sidebarResizeTimer = window.setTimeout(syncSidebarForViewport, 120);
});

window.toggleSidebar = function () {
    var isMobile = isMobileSidebar();
    if (isMobile) {
        document.body.classList.toggle('sidebar-open');
        if (document.body.classList.contains('sidebar-open')) {
            document.body.classList.remove('sidebar-collapsed');
        }
    } else {
        document.body.classList.toggle('sidebar-collapsed');
        var collapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false');
        if (collapsed) {
            collapseSidebarExtras();
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarNavigation);
} else {
    initSidebarNavigation();
}

// ── Notification Popover ──
function isNotifPopoverOpen() {
    var pop = document.getElementById('notifPopover');
    return !!(pop && pop.classList.contains('show'));
}

function resetNotifListScroll() {
    var notifList = document.getElementById('notifList');
    if (!notifList) {
        return;
    }

    notifList.scrollTop = 0;
    requestAnimationFrame(function () {
        notifList.scrollTop = 0;
        requestAnimationFrame(function () {
            notifList.scrollTop = 0;
        });
    });
}

function closeNotifPopover() {
    var pop = document.getElementById('notifPopover');
    var btn = document.getElementById('notifBtn');
    if (pop) {
        pop.classList.remove('show');
    }
    if (btn) {
        btn.setAttribute('aria-expanded', 'false');
    }
}

function formatNotifCount(count) {
    var n = Math.max(0, parseInt(count, 10) || 0);
    return n > 99 ? '99+' : String(n);
}

function getUnreadTotal() {
    var notifBadge = document.getElementById('notifBadge');
    if (!notifBadge) return 0;

    var stored = notifBadge.getAttribute('data-unread-total');
    if (stored !== null && stored !== '') {
        return Math.max(0, parseInt(stored, 10) || 0);
    }

    return Math.max(0, parseInt(notifBadge.textContent, 10) || 0);
}

function updateNotifBadge(count) {
    var notifList = document.getElementById('notifList');
    var notifBadges = document.querySelectorAll('#notifBadge');
    var notifBadge = notifBadges.length ? notifBadges[0] : null;
    var notifEmpty = document.getElementById('notifEmpty');
    var unread = typeof count === 'number' && !isNaN(count) ? Math.max(0, count) : getUnreadTotal();

    // Keep a single header badge if the DOM somehow has duplicates.
    if (notifBadges.length > 1) {
        for (var i = 1; i < notifBadges.length; i += 1) {
            notifBadges[i].remove();
        }
    }

    if (notifBadge) {
        notifBadge.setAttribute('data-unread-total', String(unread));
        notifBadge.textContent = unread > 0 ? formatNotifCount(unread) : '';
        notifBadge.style.display = unread > 0 ? 'inline-flex' : 'none';
    }
    if (notifEmpty && notifList) {
        var hasItems = notifList.querySelectorAll('.notif-item').length > 0;
        notifEmpty.style.display = hasItems ? 'none' : 'flex';
        if (!isNotifPopoverOpen()) {
            notifList.style.display = hasItems ? '' : 'none';
        }
    }
}

function escapeNotifHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
}

function renderNotifPopoverItems(items) {
    var notifList = document.getElementById('notifList');
    var notifEmpty = document.getElementById('notifEmpty');
    if (!notifList) return;

    var rows = Array.isArray(items) ? items : [];
    if (rows.length === 0) {
        notifList.innerHTML = '';
        notifList.style.display = 'none';
        if (notifEmpty) notifEmpty.style.display = 'flex';
        return;
    }

    notifList.innerHTML = rows.map(function (n) {
        var unread = !!n.unread;
        return ''
            + '<div class="notif-item' + (unread ? ' notif-unread' : '') + '"'
            + ' data-id="' + escapeNotifHtml(n.id) + '"'
            + ' data-action-url="' + escapeNotifHtml(n.action_url || '') + '"'
            + ' role="button" tabindex="-1">'
            + '<div class="notif-content">'
            + '<div class="notif-item-title">' + escapeNotifHtml(n.title || '') + '</div>'
            + '<div class="notif-item-text">' + escapeNotifHtml(n.text || '') + '</div>'
            + '<div class="notif-item-time">' + escapeNotifHtml(n.time || '') + '</div>'
            + '</div>'
            + (unread ? '<span class="notif-unread-dot"></span>' : '')
            + '</div>';
    }).join('');

    notifList.style.display = '';
    if (notifEmpty) notifEmpty.style.display = 'none';
}

var notifRefreshInFlight = false;
var lastNotifRefreshAt = 0;

function refreshFederationsNotifications(force) {
    var now = Date.now();
    if (notifRefreshInFlight) return;
    if (!force && now - lastNotifRefreshAt < 4000) return;

    notifRefreshInFlight = true;
    lastNotifRefreshAt = now;

    fetch('/api/sk-federations/notifications?limit=5', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    }).then(function (response) {
        return response.ok ? response.json() : null;
    }).then(function (data) {
        if (!data) return;
        if (typeof data.unread_count === 'number') {
            updateNotifBadge(data.unread_count);
        }
        if (Array.isArray(data.data)) {
            renderNotifPopoverItems(data.data);
        }
    }).catch(function () {
        // Keep the current badge/list on network errors.
    }).finally(function () {
        notifRefreshInFlight = false;
    });
}

window.updateNotifBadge = updateNotifBadge;
window.refreshFederationsNotifications = refreshFederationsNotifications;

window.toggleNotifPopover = function (e) {
    e.stopPropagation();
    var pop = document.getElementById('notifPopover');
    var btn = document.getElementById('notifBtn');
    var profileDd = document.getElementById('profileDropdown');
    if (profileDd) {
        profileDd.classList.remove('show');
    }
    var profileBtn = document.querySelector('.profile-btn');
    if (profileBtn) {
        profileBtn.classList.remove('open');
    }
    if (typeof window.closeMessagesPopover === 'function') {
        window.closeMessagesPopover();
    }

    var isOpen = pop && pop.classList.contains('show');
    if (isOpen) {
        closeNotifPopover();
    } else if (pop) {
        pop.classList.add('show');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
            if (typeof btn.focus === 'function') {
                btn.focus({ preventScroll: true });
            }
        }
        resetNotifListScroll();
        window.setTimeout(resetNotifListScroll, 240);
    }
};

// ── Messages Popover (Facebook-style) ──
function closeMessagesPopover() {
    var pop = document.getElementById('commsMsgPopover');
    var btn = document.getElementById('commsMsgBtn');
    if (pop) {
        pop.classList.remove('show');
    }
    if (btn) {
        btn.setAttribute('aria-expanded', 'false');
    }
}

window.closeMessagesPopover = closeMessagesPopover;

function escapeCommsHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
}

function formatCommsRelativeTime(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    var diffSec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (diffSec < 60) return diffSec + 's';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h';
    return Math.floor(diffSec / 86400) + 'd';
}

function defaultCommsAvatar(name) {
    var label = encodeURIComponent(String(name || 'U').slice(0, 40));
    return 'https://ui-avatars.com/api/?name=' + label + '&background=213F99&color=fff';
}

function renderMessagesPopoverList(conversations) {
    var list = document.getElementById('commsMsgList');
    var empty = document.getElementById('commsMsgEmpty');
    if (!list || !empty) return;

    var items = Array.isArray(conversations) ? conversations : [];
    window.__COMMS_HEADER_CONVERSATIONS__ = items;
    if (!items.length) {
        list.innerHTML = '';
        list.style.display = 'none';
        empty.style.display = 'flex';
        var emptyTitle = empty.querySelector('p');
        if (emptyTitle) emptyTitle.textContent = 'No SK Officials found';
        applyMessagesPopoverFilters();
        return;
    }

    empty.style.display = 'none';
    list.style.display = '';
    list.innerHTML = items.map(function (c) {
        var peer = c.other_user || {};
        var name = peer.name || 'User';
        var preview = (c.last_message && c.last_message.body) || 'No messages yet';
        var unread = Number(c.unread_count || 0);
        var avatar = peer.profile_image_url || defaultCommsAvatar(name);
        var convId = Number(c.id || 0);
        var userId = Number(peer.id || 0);
        var searchBlob = String(name + ' ' + preview + ' ' + (peer.barangay_name || '') + ' ' + (peer.position || '')).toLowerCase();
        var group = peer.directory_group
            || (peer.user_type === 'sk_fed' ? 'federation' : '')
            || (peer.user_type === 'sk_official' ? 'officials' : '')
            || '';
        var isFed = peer.is_federation_member ? '1' : (group === 'federation' ? '1' : '0');
        return '<button type="button" class="comms-msg-item' + (unread > 0 ? ' comms-msg-unread' : '') + '"' +
            ' data-id="' + (convId > 0 ? convId : '') + '"' +
            ' data-user-id="' + (userId > 0 ? userId : '') + '"' +
            ' data-name="' + escapeCommsHtml(name) + '"' +
            ' data-avatar="' + escapeCommsHtml(avatar) + '"' +
            ' data-unread="' + (unread > 0 ? '1' : '0') + '"' +
            ' data-group="' + escapeCommsHtml(group) + '"' +
            ' data-federation="' + isFed + '"' +
            ' data-search="' + escapeCommsHtml(searchBlob) + '"' +
            ' role="menuitem">' +
            '<img class="comms-msg-avatar" src="' + escapeCommsHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(name)) + '\'">' +
            '<div class="comms-msg-content">' +
            '<div class="comms-msg-item-top">' +
            '<span class="comms-msg-item-name">' + escapeCommsHtml(name) + '</span>' +
            '<span class="comms-msg-item-time">' + escapeCommsHtml(formatCommsRelativeTime(c.updated_at)) + '</span>' +
            '</div>' +
            '<div class="comms-msg-item-preview">' + escapeCommsHtml(preview) + '</div>' +
            '</div>' +
            (unread > 0 ? '<span class="comms-msg-unread-dot" aria-hidden="true"></span>' : '') +
            '</button>';
    }).join('');

    applyMessagesPopoverFilters();
}

function applyMessagesPopoverFilters() {
    var list = document.getElementById('commsMsgList');
    var empty = document.getElementById('commsMsgEmpty');
    var searchInput = document.getElementById('commsMsgSearch');
    var activeChip = document.querySelector('.comms-msg-filter-chip.is-active');
    if (!list) return;

    var query = String(searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
    var filter = activeChip ? String(activeChip.getAttribute('data-filter') || 'all') : 'all';
    var items = list.querySelectorAll('.comms-msg-item');
    var visibleCount = 0;

    items.forEach(function (item) {
        var unread = item.getAttribute('data-unread') === '1';
        var haystack = String(item.getAttribute('data-search') || '').toLowerCase();
        var group = String(item.getAttribute('data-group') || '').toLowerCase();
        var isFederation = item.getAttribute('data-federation') === '1' || group === 'federation' || group === 'sk_fed';
        var isOfficial = group === 'officials' || group === 'sk_official';
        var matchesFilter = filter === 'all'
            || (filter === 'unread' && unread)
            || (filter === 'officials' && isOfficial)
            || (filter === 'federation' && isFederation);
        var matchesSearch = !query || haystack.indexOf(query) !== -1;
        var show = matchesFilter && matchesSearch;
        item.classList.toggle('is-filtered-out', !show);
        if (show) {
            visibleCount += 1;
        }
    });

    var hasSourceItems = items.length > 0;
    if (empty) {
        if (!hasSourceItems) {
            empty.style.display = 'flex';
            empty.querySelector('p') && (empty.querySelector('p').textContent = 'No SK Officials found');
            list.style.display = 'none';
        } else if (visibleCount === 0) {
            empty.style.display = 'flex';
            var main = empty.querySelector('p');
            if (main) {
                if (filter === 'unread') main.textContent = 'No unread messages';
                else if (filter === 'officials') main.textContent = 'No SK Officials found';
                else if (filter === 'federation') main.textContent = 'No SK Federation members found';
                else main.textContent = 'No matching chats';
            }
            list.style.display = 'none';
        } else {
            empty.style.display = 'none';
            list.style.display = '';
        }
    }
}

function wireMessagesPopoverControls() {
    var searchInput = document.getElementById('commsMsgSearch');
    var chips = document.querySelectorAll('.comms-msg-filter-chip');
    if (searchInput && searchInput.dataset.wired !== 'true') {
        searchInput.addEventListener('input', applyMessagesPopoverFilters);
        searchInput.dataset.wired = 'true';
    }
    chips.forEach(function (chip) {
        if (chip.dataset.wired === 'true') return;
        chip.addEventListener('click', function () {
            chips.forEach(function (other) {
                other.classList.toggle('is-active', other === chip);
                other.setAttribute('aria-selected', other === chip ? 'true' : 'false');
            });
            applyMessagesPopoverFilters();
        });
        chip.dataset.wired = 'true';
    });
}

function refreshMessagesPopover() {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    fetch('/api/communications/conversations?include_directory=1', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        },
        credentials: 'same-origin'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data) return;
        var conversations = data.conversations || [];
        renderMessagesPopoverList(conversations);
        if (headerChatState.fullscreen || document.getElementById('commsChatFsSidebar')) {
            renderFullscreenChatList(conversations);
        }
    }).catch(function () { /* ignore */ });
}

function startHeaderChatWithUser(userId, name, avatarUrl) {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var peerId = Number(userId || 0);
    if (!peerId) return;

    return fetch('/api/communications/conversations', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ user_id: peerId })
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        var conversation = data && data.conversation ? data.conversation : null;
        if (!conversation || !conversation.id) return;
        openHeaderChatModal(
            conversation.id,
            (conversation.other_user && conversation.other_user.name) || name || 'Chat',
            (conversation.other_user && conversation.other_user.profile_image_url) || avatarUrl || ''
        );
        refreshMessagesPopover();
    }).catch(function () { /* ignore */ });
}

window.refreshMessagesPopover = refreshMessagesPopover;
window.syncFederationsMessagesPageHeaderBtn = syncFederationsMessagesPageHeaderBtn;

function isFederationsMessagesPage() {
    return !!document.body.classList.contains('comms-messages-layout')
        || /^\/communications\/?$/.test(window.location.pathname || '');
}

function storeFederationsMessagesReturnUrl() {
    try {
        var path = String(window.location.pathname || '') + String(window.location.search || '');
        if (!/^\/communications(\/|$)/.test(window.location.pathname || '')) {
            sessionStorage.setItem('comms_messages_return_url', path || '/dashboard');
        }
    } catch (e) { /* ignore */ }
}

function captureFederationsActiveChatForMinimize() {
    var meta = null;
    if (window.CommsChat && typeof window.CommsChat.getActiveConversationMeta === 'function') {
        meta = window.CommsChat.getActiveConversationMeta();
    }
    if (!meta || !meta.id) {
        var id = Number(window.__COMMS_PAGE_ACTIVE_ID__ || 0);
        if (!id) return;
        var nameEl = document.getElementById('commsPeerName');
        var avatarEl = document.getElementById('commsPeerAvatar');
        meta = {
            id: id,
            name: nameEl ? String(nameEl.textContent || '').trim() : 'Chat',
            avatar: avatarEl ? (avatarEl.getAttribute('src') || '') : ''
        };
    }
    if (!meta || !meta.id) return;
    try {
        sessionStorage.setItem('comms_pending_header_chat', JSON.stringify({
            id: Number(meta.id),
            name: meta.name || 'Chat',
            avatar: meta.avatar || ''
        }));
    } catch (e) { /* ignore */ }
}

function leaveFederationsMessagesPage() {
    // Minimize open See All conversation into the floating header chat modal.
    captureFederationsActiveChatForMinimize();
    var url = '/dashboard';
    try {
        var stored = sessionStorage.getItem('comms_messages_return_url');
        if (stored && !/^\/communications(\/|$)/.test(stored)) {
            url = stored;
        }
        sessionStorage.removeItem('comms_messages_return_url');
    } catch (e) { /* ignore */ }
    window.location.assign(url);
}

function openPendingFederationsHeaderChat() {
    if (isFederationsMessagesPage()) return;
    var raw = null;
    try {
        raw = sessionStorage.getItem('comms_pending_header_chat');
        sessionStorage.removeItem('comms_pending_header_chat');
    } catch (e) {
        return;
    }
    if (!raw) return;
    var pending = null;
    try {
        pending = JSON.parse(raw);
    } catch (e) {
        return;
    }
    if (!pending || !pending.id) return;
    window.setTimeout(function () {
        openHeaderChatModal(pending.id, pending.name || 'Chat', pending.avatar || '');
    }, 80);
}

function syncFederationsMessagesPageHeaderBtn() {
    var btn = document.getElementById('commsMsgBtn');
    if (!btn || !isFederationsMessagesPage()) return;
    btn.classList.add('is-active');
    btn.setAttribute('aria-pressed', 'true');
    btn.setAttribute('aria-expanded', 'false');
    var hasActive = !!(window.__COMMS_PAGE_ACTIVE_ID__
        || (window.CommsChat && window.CommsChat.getActiveId && window.CommsChat.getActiveId()));
    var label = hasActive ? 'Minimize to chat' : 'Leave Messages';
    btn.title = label;
    btn.setAttribute('aria-label', label);
}

function wireFederationsSeeAllMessagesLinks() {
    document.querySelectorAll('.comms-msg-see-all-link').forEach(function (link) {
        if (link.dataset.returnWired === '1') return;
        link.addEventListener('click', function () {
            storeFederationsMessagesReturnUrl();
        });
        link.dataset.returnWired = '1';
    });
}

window.toggleMessagesPopover = function (e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    // On Messages page: keep Messages selected; click again returns to prior page.
    if (isFederationsMessagesPage()) {
        leaveFederationsMessagesPage();
        return;
    }
    var pop = document.getElementById('commsMsgPopover');
    var btn = document.getElementById('commsMsgBtn');
    closeNotifPopover();
    var profileDd = document.getElementById('profileDropdown');
    if (profileDd) {
        profileDd.classList.remove('show');
    }
    var profileBtn = document.querySelector('.profile-btn');
    if (profileBtn) {
        profileBtn.classList.remove('open');
    }

    var isOpen = pop && pop.classList.contains('show');
    if (isOpen) {
        closeMessagesPopover();
        return;
    }
    if (pop) {
        pop.classList.add('show');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        wireMessagesPopoverControls();
        wireFederationsSeeAllMessagesLinks();
        refreshMessagesPopover();
    }
};

// ── Header chat modal (open conversation without leaving page) ──
var headerChatState = {
    conversationId: null,
    loading: false,
    sending: false,
    fullscreen: false,
    messages: [],
    reactionEmojis: ['👍', '❤️', '😆', '😮', '😢', '🙏'],
    openReactionPickerId: null,
    editingId: null,
    msgChangeSkipToast: {},
    reactInFlight: {},
    reactSkipRealtime: {},
    reactSeq: {},
    pendingAttachments: [],
    pendingPreviewUrls: []
};

function stopHeaderLocalTyping(conversationId) {
    var cid = conversationId != null ? conversationId : headerChatState.conversationId;
    if (!cid || !window.CommsRealtime) return;
    if (typeof window.CommsRealtime.stopTyping === 'function') {
        window.CommsRealtime.stopTyping(cid);
    } else if (typeof window.CommsRealtime.broadcastTyping === 'function') {
        window.CommsRealtime.broadcastTyping(cid, { stop: true });
    }
}

function notifyHeaderComposerTyping(rawValue) {
    var cid = headerChatState.conversationId;
    if (!cid || !window.CommsRealtime || typeof window.CommsRealtime.broadcastTyping !== 'function') {
        return;
    }
    var hasText = !!String(rawValue == null ? '' : rawValue).replace(/\s/g, '');
    if (!hasText) {
        stopHeaderLocalTyping(cid);
        return;
    }
    window.CommsRealtime.broadcastTyping(cid);
}

function getCommsCsrfToken() {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    return csrf ? csrf.getAttribute('content') : '';
}

function clearHeaderChatAttachments() {
    headerChatState.pendingPreviewUrls.forEach(function (u) { if (u) URL.revokeObjectURL(u); });
    headerChatState.pendingAttachments = [];
    headerChatState.pendingPreviewUrls = [];
    var preview = document.getElementById('commsChatAttachPreview');
    if (preview) preview.hidden = true;
    var inner = document.getElementById('commsChatAttachPreviewInner');
    if (inner) inner.innerHTML = '';
}

function isHeaderImageFile(f) {
    return /^image\/(jpeg|png|webp|gif)$/i.test(f.type || '');
}

function renderHeaderAttachPreview() {
    var inner = document.getElementById('commsChatAttachPreviewInner');
    var wrap = document.getElementById('commsChatAttachPreview');
    if (!inner || !wrap) return;
    if (!headerChatState.pendingAttachments.length) { wrap.hidden = true; inner.innerHTML = ''; return; }
    wrap.hidden = false;
    inner.innerHTML = headerChatState.pendingAttachments.map(function (f, i) {
        if (isHeaderImageFile(f) && headerChatState.pendingPreviewUrls[i]) {
            return '<div class="comms-chat-attach-preview-item"><img src="' + headerChatState.pendingPreviewUrls[i] + '" alt="preview"></div>';
        }
        return '<div class="comms-chat-attach-preview-item"><span class="file-icon"><i class="fas fa-file"></i></span><span>' + escapeCommsHtml(f.name) + '</span></div>';
    }).join('');
}

function pushHeaderFiles(files, kind) {
    var maxImg = 100, maxFile = 10, maxBytes = 25 * 1024 * 1024;
    var imgCount = 0, fileCount = 0, totalBytes = 0;
    headerChatState.pendingAttachments.forEach(function (f) {
        totalBytes += f.size || 0;
        if (isHeaderImageFile(f)) imgCount++; else fileCount++;
    });
    for (var i = 0; i < files.length; i++) {
        var f = files[i];
        totalBytes += f.size || 0;
        if (totalBytes > maxBytes) { alert('Total size exceeds 25 MB.'); break; }
        if (kind === 'image') {
            imgCount++;
            if (imgCount > maxImg) { alert('Max ' + maxImg + ' images.'); break; }
        } else {
            fileCount++;
            if (fileCount > maxFile) { alert('Max ' + maxFile + ' files.'); break; }
        }
        headerChatState.pendingAttachments.push(f);
        headerChatState.pendingPreviewUrls.push(isHeaderImageFile(f) ? URL.createObjectURL(f) : null);
    }
    renderHeaderAttachPreview();
}

function headerFileKindClass(a) {
    var name = String((a && a.file_name) || '');
    var mime = String((a && a.mime_type) || '').toLowerCase();
    if (/\.pdf$/i.test(name) || mime === 'application/pdf') return ' is-pdf';
    if (/\.docx?$/i.test(name) || mime.indexOf('msword') !== -1 || mime.indexOf('wordprocessingml') !== -1) return ' is-word';
    if (/\.xlsx?$/i.test(name) || mime.indexOf('spreadsheetml') !== -1 || mime.indexOf('ms-excel') !== -1) return ' is-excel';
    return '';
}

function formatHeaderDaySeparator(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    var now = new Date();
    var time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
    if (d.toDateString() === now.toDateString()) return time;
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
    var weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
    if (d >= weekAgo) {
        return d.toLocaleDateString([], { weekday: 'short' }) + ' ' + time;
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) + ', ' + time;
}

function shouldInsertHeaderDateSeparator(prevIso, currIso) {
    if (!currIso) return false;
    if (!prevIso) return true;
    var prev = new Date(prevIso);
    var curr = new Date(currIso);
    if (Number.isNaN(prev.getTime()) || Number.isNaN(curr.getTime())) return true;
    if (prev.toDateString() !== curr.toDateString()) return true;
    return Math.abs(curr.getTime() - prev.getTime()) >= 45 * 60 * 1000;
}

function renderHeaderDateSeparator(iso) {
    var label = formatHeaderDaySeparator(iso);
    if (!label) return '';
    return '<div class="comms-chat-date-sep" role="separator"><span class="comms-chat-date-sep-text">' +
        escapeCommsHtml(label) + '</span></div>';
}

function toastHeaderMessageCharLimit() {
    if (window.Comms && typeof window.Comms.showToast === 'function') {
        window.Comms.showToast('Message cannot exceed 1000 characters.', 'error');
    }
}

function normalizeHeaderMessageBody(raw) {
    if (window.Comms && typeof window.Comms.normalizeMessageBody === 'function') {
        return window.Comms.normalizeMessageBody(raw);
    }
    return String(raw == null ? '' : raw).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
}

function clampHeaderMessageToMaxChars(text) {
    var next = normalizeHeaderMessageBody(text);
    if (next.length <= 1000) return next;
    return next.slice(0, 1000);
}

function wireHeaderEditLimit(editor) {
    if (!editor || editor.dataset.limitWired === '1') return;
    editor.removeAttribute('maxlength');
    editor.addEventListener('keydown', function (ev) {
        var current = normalizeHeaderMessageBody(editor.value || '');
        if (ev.key === 'Enter' && !(ev.ctrlKey || ev.metaKey)) {
            if ((current + '\n').length > 1000) {
                ev.preventDefault();
                toastHeaderMessageCharLimit();
            }
            return;
        }
        if (ev.key.length === 1 && !ev.ctrlKey && !ev.metaKey && !ev.altKey) {
            if ((current + ev.key).length > 1000) {
                ev.preventDefault();
                toastHeaderMessageCharLimit();
            }
        }
    });
    editor.addEventListener('paste', function (ev) {
        var paste = (ev.clipboardData || window.clipboardData);
        if (!paste) return;
        var incoming = paste.getData('text');
        if (incoming == null) return;
        ev.preventDefault();
        var start = editor.selectionStart || 0;
        var end = editor.selectionEnd || 0;
        var value = editor.value || '';
        var raw = value.slice(0, start) + incoming + value.slice(end);
        var next = clampHeaderMessageToMaxChars(raw);
        editor.value = next;
        if (next !== normalizeHeaderMessageBody(raw)) toastHeaderMessageCharLimit();
        if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
            window.Comms.resizeGrowTextarea(editor, 6, 2);
        }
    });
    editor.addEventListener('input', function () {
        var next = clampHeaderMessageToMaxChars(editor.value || '');
        if (next !== normalizeHeaderMessageBody(editor.value || '')) {
            editor.value = next;
            toastHeaderMessageCharLimit();
        }
        if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
            window.Comms.resizeGrowTextarea(editor, 6, 2);
        }
    });
    editor.dataset.limitWired = '1';
}

function validateHeaderEditBody(raw) {
    var body = (window.Comms && typeof window.Comms.normalizeMessageBody === 'function')
        ? window.Comms.normalizeMessageBody(raw)
        : String(raw == null ? '' : raw).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    if (body.trim() === '') {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot be empty.', 'error');
        }
        return null;
    }
    var max = 1000;
    var count = (window.Comms && typeof window.Comms.measureMessageLength === 'function')
        ? window.Comms.measureMessageLength(body)
        : body.length;
    if (count > max) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot exceed 1000 characters.', 'error');
        }
        return null;
    }
    var lines = body.split('\n').length;
    if (lines > 500) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot exceed 500 lines.', 'error');
        }
        return null;
    }
    return body;
}

function formatHeaderFileBytes(n) {
    n = Number(n || 0);
    if (!n) return '';
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(2) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
}

function isHeaderImageAttachment(a) {
    if (!a) return false;
    return a.file_type === 'image'
        || a.storage_provider === 'cloudinary'
        || /^image\//i.test(String(a.mime_type || ''))
        || /\.(jpe?g|png|gif|webp|bmp|heic)$/i.test(String(a.file_name || ''));
}

function headerFileIconSvg() {
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';
}

function renderHeaderAttachmentBubbles(attachments) {
    if (!attachments || !attachments.length) return '';
    var images = [];
    var files = [];
    attachments.forEach(function (a) {
        if (isHeaderImageAttachment(a)) images.push(a);
        else files.push(a);
    });

    var html = '';
    if (images.length) {
        html += '<div class="comms-chat-attachment-grid" data-count="' + Math.min(images.length, 9) + '">';
        images.forEach(function (a) {
            var src = a.url || a.file_path || a.download_url || '';
            html += '<button type="button" class="comms-chat-attachment-image-btn" data-comms-lightbox-src="' +
                escapeCommsHtml(src) + '" aria-label="View image fullscreen">' +
                '<img src="' + escapeCommsHtml(src) + '" alt="' + escapeCommsHtml(a.file_name || 'image') + '" loading="lazy">' +
                '</button>';
        });
        html += '</div>';
    }

    files.forEach(function (a) {
        var dlUrl = a.download_url || '#';
        var fileName = a.file_name || 'document';
        var kind = headerFileKindClass(a);
        var sizeLabel = formatHeaderFileBytes(a.file_size);
        var attrs = 'href="' + escapeCommsHtml(dlUrl) + '" class="comms-chat-attachment-file' + kind + '" title="' + escapeCommsHtml(fileName) + '"';
        if (kind.indexOf('is-pdf') !== -1 && dlUrl && dlUrl !== '#') {
            attrs += ' data-comms-doc-preview="' + escapeCommsHtml(dlUrl) + '" data-comms-doc-title="' + escapeCommsHtml(fileName) + '"';
        } else {
            attrs += ' download="' + escapeCommsHtml(fileName) + '"';
        }
        html += '<a ' + attrs + '>' +
            '<span class="comms-chat-attachment-file-icon" aria-hidden="true">' + headerFileIconSvg() + '</span>' +
            '<span class="comms-chat-attachment-file-meta">' +
            '<span class="comms-chat-attachment-file-name">' + escapeCommsHtml(fileName) + '</span>' +
            (sizeLabel ? '<span class="comms-chat-attachment-file-size">' + escapeCommsHtml(sizeLabel) + '</span>' : '') +
            '</span></a>';
    });
    return html;
}

function closeHeaderChatModal() {
    var modal = document.getElementById('commsChatModal');
    if (!modal) return;
    stopHeaderLocalTyping();
    modal.hidden = true;
    modal.classList.remove('is-fullscreen', 'is-empty-thread', 'is-thread-open');
    document.body.classList.remove('comms-chat-modal-open');
    headerChatState.conversationId = null;
    headerChatState.messages = [];
    headerChatState.fullscreen = false;
    window.__COMMS_HEADER_ACTIVE_ID__ = null;
    var typingEl = document.getElementById('commsChatModalTyping');
    if (typingEl) {
        typingEl.hidden = true;
        typingEl.textContent = '';
    }
    hideHeaderReactionPicker();
    clearHeaderChatAttachments();
    var sidebar = document.getElementById('commsChatFsSidebar');
    var empty = document.getElementById('commsChatFsEmpty');
    var active = document.getElementById('commsChatFsActive');
    var backBtn = document.getElementById('commsChatFsBack');
    if (sidebar) sidebar.hidden = true;
    if (empty) empty.hidden = true;
    if (active) active.hidden = false;
    if (backBtn) backBtn.hidden = true;
}

function setHeaderChatEmptyThread(isEmpty) {
    var modal = document.getElementById('commsChatModal');
    var empty = document.getElementById('commsChatFsEmpty');
    var active = document.getElementById('commsChatFsActive');
    var backBtn = document.getElementById('commsChatFsBack');
    if (!modal) return;

    if (!modal.classList.contains('is-fullscreen')) {
        modal.classList.remove('is-empty-thread');
        modal.classList.toggle('is-thread-open', !!headerChatState.conversationId);
        if (empty) empty.hidden = true;
        if (active) active.hidden = false;
        if (backBtn) backBtn.hidden = true;
        return;
    }

    modal.classList.toggle('is-empty-thread', !!isEmpty);
    modal.classList.toggle('is-thread-open', !isEmpty);
    if (empty) empty.hidden = !isEmpty;
    if (active) active.hidden = !!isEmpty;
    if (backBtn) {
        backBtn.hidden = !(!isEmpty && window.innerWidth <= 768);
    }
}

function renderFullscreenChatList(conversations) {
    var list = document.getElementById('commsChatFsList');
    var empty = document.getElementById('commsChatFsListEmpty');
    if (!list) return;

    var items = Array.isArray(conversations) ? conversations : [];
    window.__COMMS_HEADER_CONVERSATIONS__ = items;

    if (!items.length) {
        list.innerHTML = '';
        if (empty) empty.hidden = false;
        return;
    }

    if (empty) empty.hidden = true;
    list.innerHTML = items.map(function (c) {
        var peer = c.other_user || {};
        var name = peer.name || 'User';
        var preview = (c.last_message && c.last_message.body) || 'No messages yet';
        var unread = Number(c.unread_count || 0);
        var avatar = peer.profile_image_url || defaultCommsAvatar(name);
        var convId = Number(c.id || 0);
        var userId = Number(peer.id || 0);
        var searchBlob = String(name + ' ' + preview + ' ' + (peer.barangay_name || '') + ' ' + (peer.position || '')).toLowerCase();
        var active = convId > 0 && convId === Number(headerChatState.conversationId) ? ' is-active' : '';
        return '<button type="button" class="comms-chat-fs-item' + active + (unread > 0 ? ' is-unread' : '') + '"' +
            ' data-id="' + (convId > 0 ? convId : '') + '"' +
            ' data-user-id="' + (userId > 0 ? userId : '') + '"' +
            ' data-name="' + escapeCommsHtml(name) + '"' +
            ' data-avatar="' + escapeCommsHtml(avatar) + '"' +
            ' data-unread="' + (unread > 0 ? '1' : '0') + '"' +
            ' data-search="' + escapeCommsHtml(searchBlob) + '"' +
            ' role="listitem">' +
            '<img class="comms-chat-fs-item-avatar" src="' + escapeCommsHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(name)) + '\'">' +
            '<div class="comms-chat-fs-item-main">' +
            '<span class="comms-chat-fs-item-name">' + escapeCommsHtml(name) + '</span>' +
            '<span class="comms-chat-fs-item-preview">' + escapeCommsHtml(preview) + '</span>' +
            '</div>' +
            '<div class="comms-chat-fs-item-meta">' +
            '<span class="comms-chat-fs-item-time">' + escapeCommsHtml(formatCommsRelativeTime(c.updated_at)) + '</span>' +
            (unread > 0 ? '<span class="comms-chat-fs-item-badge">' + (unread > 99 ? '99+' : unread) + '</span>' : '') +
            '</div>' +
            '</button>';
    }).join('');

    applyFullscreenChatFilters();
}

function applyFullscreenChatFilters() {
    var list = document.getElementById('commsChatFsList');
    var empty = document.getElementById('commsChatFsListEmpty');
    var searchInput = document.getElementById('commsChatFsSearch');
    var activeChip = document.querySelector('.comms-chat-fs-filter.is-active');
    if (!list) return;

    var query = String(searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
    var filter = activeChip ? String(activeChip.getAttribute('data-fs-filter') || 'all') : 'all';
    var items = list.querySelectorAll('.comms-chat-fs-item');
    var visible = 0;

    items.forEach(function (item) {
        var unread = item.getAttribute('data-unread') === '1';
        var haystack = String(item.getAttribute('data-search') || '').toLowerCase();
        var show = (filter === 'all' || (filter === 'unread' && unread))
            && (!query || haystack.indexOf(query) !== -1);
        item.classList.toggle('is-filtered-out', !show);
        if (show) visible += 1;
    });

    if (empty) {
        empty.hidden = items.length > 0 && visible > 0;
        if (items.length && visible === 0) {
            empty.hidden = false;
            empty.innerHTML = '<p>' + (filter === 'unread' ? 'No unread messages' : 'No matching chats') + '</p>';
        } else if (!items.length) {
            empty.hidden = false;
            empty.innerHTML = '<p>No conversations yet</p>';
        }
    }
}

function setHeaderChatFullscreen() {
    // Fullscreen chat removed — always use docked floating panel.
    var modal = document.getElementById('commsChatModal');
    var sidebar = document.getElementById('commsChatFsSidebar');
    if (!modal) return;

    headerChatState.fullscreen = false;
    modal.classList.remove('is-fullscreen');
    if (sidebar) sidebar.hidden = true;
    setHeaderChatEmptyThread(!headerChatState.conversationId);
}

function openHeaderMessagesFullscreen(conversationId, name, avatarUrl) {
    // Legacy entry point — open docked chat (or Messages page when no conversation).
    if (conversationId) {
        openHeaderChatModal(conversationId, name, avatarUrl);
        return;
    }
    var link = document.getElementById('commsMsgNewLink');
    if (link && link.href) {
        window.location.href = link.href;
        return;
    }
    window.location.href = '/communications';
}

function openHeaderChatModal(conversationId, name, avatarUrl) {
    var modal = document.getElementById('commsChatModal');
    var title = document.getElementById('commsChatModalTitle');
    var sub = document.getElementById('commsChatModalSub');
    var input = document.getElementById('commsChatModalInput');
    var body = document.getElementById('commsChatModalBody');
    if (!modal || !conversationId) return;

    closeMessagesPopover();
    hideHeaderReactionPicker();
    if (headerChatState.conversationId && Number(headerChatState.conversationId) !== Number(conversationId)) {
        stopHeaderLocalTyping(headerChatState.conversationId);
    }
    headerChatState.conversationId = Number(conversationId);
    window.__COMMS_HEADER_ACTIVE_ID__ = headerChatState.conversationId;
    var typingEl = document.getElementById('commsChatModalTyping');
    if (typingEl) {
        typingEl.hidden = true;
        typingEl.textContent = '';
    }
    if (title) title.textContent = name || 'Chat';
    if (sub) sub.textContent = 'Conversation';
    setHeaderChatAvatar(name, avatarUrl);
    if (input) {
        input.value = '';
    }

    var cached = readHeaderChatCache(conversationId);
    if (cached && Array.isArray(cached.messages) && cached.messages.length) {
        var cachedEmojis = cached.reactionEmojis || cached.reaction_emojis;
        if (Array.isArray(cachedEmojis) && cachedEmojis.length) {
            headerChatState.reactionEmojis = cachedEmojis;
        }
        headerChatState.messages = cached.messages;
        renderHeaderChatMessages(cached.messages);
    } else if (body) {
        body.innerHTML = '';
    }

    modal.hidden = false;
    document.body.classList.add('comms-chat-modal-open');
    setHeaderChatEmptyThread(false);
    if (headerChatState.fullscreen) {
        setHeaderChatFullscreen(true);
        document.querySelectorAll('#commsChatFsList .comms-chat-fs-item').forEach(function (el) {
            el.classList.toggle('is-active', Number(el.getAttribute('data-id')) === Number(conversationId));
        });
    }

    // Subscribe immediately so new messages appear while history loads.
    if (window.CommsRealtime && typeof window.CommsRealtime.subscribeConversation === 'function') {
        window.CommsRealtime.subscribeConversation(conversationId);
    }
    loadHeaderChatMessages(conversationId);
    markHeaderConversationRead(conversationId);
    window.setTimeout(function () {
        if (input) input.focus();
    }, 50);
}

function setHeaderChatAvatar(name, avatarUrl) {
    var el = document.getElementById('commsChatModalAvatar');
    if (!el) return;
    var src = avatarUrl || defaultCommsAvatar(name);
    el.innerHTML = '<img src="' + escapeCommsHtml(src) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(name)) + '\'">';
    el.classList.remove('comms-chat-modal-avatar--fallback');
}

function wireFullscreenChatControls() {
    var searchInput = document.getElementById('commsChatFsSearch');
    var chips = document.querySelectorAll('.comms-chat-fs-filter');
    var list = document.getElementById('commsChatFsList');
    var backBtn = document.getElementById('commsChatFsBack');

    if (searchInput && searchInput.dataset.wired !== 'true') {
        searchInput.addEventListener('input', applyFullscreenChatFilters);
        searchInput.dataset.wired = 'true';
    }
    chips.forEach(function (chip) {
        if (chip.dataset.wired === 'true') return;
        chip.addEventListener('click', function () {
            chips.forEach(function (other) {
                other.classList.toggle('is-active', other === chip);
                other.setAttribute('aria-selected', other === chip ? 'true' : 'false');
            });
            applyFullscreenChatFilters();
        });
        chip.dataset.wired = 'true';
    });
    if (list && list.dataset.fsWired !== 'true') {
        list.addEventListener('click', function (e) {
            var item = e.target.closest('.comms-chat-fs-item');
            if (!item) return;
            e.preventDefault();
            var convId = Number(item.getAttribute('data-id') || 0);
            var userId = Number(item.getAttribute('data-user-id') || 0);
            var name = item.getAttribute('data-name') || 'Chat';
            var avatar = item.getAttribute('data-avatar') || '';
            if (convId > 0) {
                openHeaderChatModal(convId, name, avatar);
                return;
            }
            if (userId > 0) {
                startHeaderChatWithUser(userId, name, avatar);
            }
        });
        list.dataset.fsWired = 'true';
    }
    if (backBtn && backBtn.dataset.wired !== 'true') {
        backBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeHeaderChatModal();
        });
        backBtn.dataset.wired = 'true';
    }
}

function renderHeaderReactionChips(reactions, messageId) {
    var items = Array.isArray(reactions)
        ? reactions.filter(function (r) { return Number(r.count || 0) > 0; })
        : [];
    if (!items.length) return '';
    var escape = window.Comms && Comms.escapeHtml ? Comms.escapeHtml : escapeCommsHtml;
    return '<div class="comms-chat-reaction-chips" role="group" aria-label="Reactions">' +
        items.map(function (r) {
            return '<button type="button" class="comms-reaction-summary' + (r.mine ? ' is-mine' : '') + '"' +
                ' data-react-people="' + Number(messageId || 0) + '"' +
                ' data-react-people-emoji="' + escape(r.emoji) + '"' +
                ' aria-haspopup="dialog" aria-label="' + escape(r.emoji) + ' ' + Number(r.count || 0) + ' reactions">' +
                '<span class="comms-reaction-stack-btn" aria-hidden="true">' + escape(r.emoji) + '</span>' +
                '<span class="comms-reaction-count" aria-hidden="true">' + Number(r.count || 0) + '</span>' +
                '</button>';
        }).join('') +
        '</div>';
}

function renderHeaderReactionPicker() {
    return '';
}

function getHeaderReactionPickerEl() {
    var el = document.getElementById('commsChatReactionPicker');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'commsChatReactionPicker';
    el.className = 'comms-chat-reaction-picker comms-chat-reaction-picker--fixed';
    el.setAttribute('role', 'menu');
    el.setAttribute('aria-label', 'Choose reaction');
    el.style.display = 'none';
    el.style.position = 'fixed';
    el.style.zIndex = '40050';
    document.body.appendChild(el);
    return el;
}

function hideHeaderReactionPicker() {
    headerChatState.openReactionPickerId = null;
    var el = document.getElementById('commsChatReactionPicker');
    if (el) {
        el.style.display = 'none';
        el.innerHTML = '';
        el.removeAttribute('data-react-picker');
    }
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap.is-picker-open').forEach(function (wrap) {
        wrap.classList.remove('is-picker-open');
        var btn = wrap.querySelector('[data-react-toggle]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    });
}

function positionHeaderReactionPicker(triggerBtn) {
    var el = getHeaderReactionPickerEl();
    if (!triggerBtn) return;
    var rect = triggerBtn.getBoundingClientRect();
    var pickerW = el.offsetWidth || 240;
    var pickerH = el.offsetHeight || 48;
    var pad = 8;
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var top = rect.top - pickerH - 8;
    var left = rect.left + (rect.width / 2) - (pickerW / 2);
    if (top < pad) {
        top = rect.bottom + 8;
    }
    left = Math.max(pad, Math.min(left, vw - pickerW - pad));
    top = Math.max(pad, Math.min(top, vh - pickerH - pad));
    el.style.top = top + 'px';
    el.style.left = left + 'px';
}

function showHeaderReactionPicker(messageId, triggerBtn) {
    headerChatState.openReactionPickerId = Number(messageId) || null;
    if (!headerChatState.openReactionPickerId || !triggerBtn) {
        hideHeaderReactionPicker();
        return;
    }
    var el = getHeaderReactionPickerEl();
    el.setAttribute('data-react-picker', String(headerChatState.openReactionPickerId));
    el.innerHTML = headerChatState.reactionEmojis.map(function (emoji) {
        return '<button type="button" class="comms-chat-reaction-pick" data-react-emoji="' +
            escapeCommsHtml(emoji) + '" role="menuitem">' +
            escapeCommsHtml(emoji) + '</button>';
    }).join('');
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap').forEach(function (wrap) {
        var isOpen = Number(wrap.getAttribute('data-id')) === headerChatState.openReactionPickerId;
        wrap.classList.toggle('is-picker-open', isOpen);
        var btn = wrap.querySelector('[data-react-toggle]');
        if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    el.style.display = 'inline-flex';
    positionHeaderReactionPicker(triggerBtn);
    requestAnimationFrame(function () {
        positionHeaderReactionPicker(triggerBtn);
    });
}

var headerReactBtnIcon = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>';

function playHeaderChatReactionSound() {
    if (window.Comms && typeof window.Comms.playReactionSound === 'function') {
        window.Comms.playReactionSound();
        return;
    }
    try {
        var audio = new Audio('/sounds/reactions_ux.mp3');
        audio.volume = 0.75;
        audio.play().catch(function () {});
    } catch (e) {}
}

function headerCallBubbleClass(body) {
    var t = String(body || '').toLowerCase();
    if (t.indexOf('ended') !== -1 || t.indexOf('missed') !== -1 || t.indexOf('declined') !== -1 || t.indexOf('cancelled') !== -1 || t.indexOf('canceled') !== -1) return 'is-system is-call-ended';
    if (t.indexOf('started') !== -1 || t.indexOf('ringing') !== -1 || t.indexOf('answered') !== -1) return 'is-system is-call-started';
    return 'is-system';
}

var headerMsgMoreIcon = '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.85"/><circle cx="12" cy="12" r="1.85"/><circle cx="12" cy="19" r="1.85"/></svg>';

function renderHeaderMessageActions(msg, deleted) {
    var items = '';
    if (!deleted && msg.mine && msg.message_type === 'text') {
        items += '<button type="button" role="menuitem" data-msg-edit="' + msg.id + '">Edit</button>';
    }
    items += '<button type="button" role="menuitem" data-msg-delete="' + msg.id + '">Delete</button>';
    return '<div class="comms-chat-msg-actions">' +
        '<button type="button" class="comms-chat-msg-more" data-msg-more="' + msg.id + '" aria-label="Message actions" aria-haspopup="menu" aria-expanded="false" title="More">' + headerMsgMoreIcon + '</button>' +
        '<div class="comms-chat-msg-menu" data-msg-menu="' + msg.id + '" role="menu" hidden>' + items + '</div>' +
        '</div>';
}

function closeHeaderMessageMenus() {
    document.querySelectorAll('#commsChatModalBody [data-msg-menu]').forEach(function (menu) {
        menu.hidden = true;
    });
    document.querySelectorAll('#commsChatModalBody [data-msg-more]').forEach(function (btn) {
        btn.setAttribute('aria-expanded', 'false');
    });
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap.is-menu-open').forEach(function (wrap) {
        wrap.classList.remove('is-menu-open');
    });
}

function replaceHeaderMessage(updated) {
    if (!updated) return;
    var idx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(updated.id); });
    if (idx === -1) return;
    headerChatState.messages[idx] = updated;
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
}

function removeHeaderMessageLocal(messageId) {
    headerChatState.messages = headerChatState.messages.filter(function (m) {
        return Number(m.id) !== Number(messageId);
    });
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
}

function saveHeaderEditedMessage(messageId) {
    var bodyEl = document.getElementById('commsChatModalBody');
    var input = bodyEl ? bodyEl.querySelector('[data-edit-input="' + messageId + '"]') : null;
    if (!input) return;
    var body = validateHeaderEditBody(input.value);
    if (body == null) return;
    headerChatState.msgChangeSkipToast[String(messageId)] = true;
    fetch('/api/communications/messages/' + encodeURIComponent(messageId), {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ body: body })
    }).then(function (r) {
        return r.ok ? r.json() : r.json().then(function (data) { throw new Error((data && data.message) || 'Unable to edit message.'); });
    }).then(function (data) {
        headerChatState.editingId = null;
        replaceHeaderMessage(data.message);
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message edited.', 'success');
        }
        window.setTimeout(function () { delete headerChatState.msgChangeSkipToast[String(messageId)]; }, 800);
    }).catch(function (err) {
        delete headerChatState.msgChangeSkipToast[String(messageId)];
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(err.message || 'Unable to edit message.', 'error');
        }
    });
}

function deleteHeaderMessage(messageId, scope) {
    headerChatState.msgChangeSkipToast[String(messageId)] = true;
    fetch('/api/communications/messages/' + encodeURIComponent(messageId) + '?scope=' + encodeURIComponent(scope), {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ scope: scope })
    }).then(function (r) {
        return r.ok ? r.json() : r.json().then(function (data) { throw new Error((data && data.message) || 'Unable to delete message.'); });
    }).then(function (data) {
        headerChatState.editingId = null;
        if (data && data.scope === 'me') {
            removeHeaderMessageLocal(messageId);
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                window.Comms.showToast('Message deleted for you.', 'success');
            }
            return;
        }
        if (data && data.message) {
            replaceHeaderMessage(data.message);
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                window.Comms.showToast('Message deleted for everyone.', 'success');
            }
        }
        window.setTimeout(function () { delete headerChatState.msgChangeSkipToast[String(messageId)]; }, 800);
    }).catch(function (err) {
        delete headerChatState.msgChangeSkipToast[String(messageId)];
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(err.message || 'Unable to delete message.', 'error');
        }
    });
}

function openHeaderDeletePrompt(messageId) {
    var msg = headerChatState.messages.find(function (m) { return Number(m.id) === Number(messageId); });
    if (!msg) return;
    var deleted = msg.deleted_for_all || msg.message_type === 'deleted';
    var canDeleteAll = !!msg.mine && !deleted;
    if (!window.Comms || typeof window.Comms.openDeleteMessageDialog !== 'function') {
        deleteHeaderMessage(messageId, canDeleteAll ? 'all' : 'me');
        return;
    }
    window.Comms.openDeleteMessageDialog({ canDeleteAll: canDeleteAll }).then(function (scope) {
        if (!scope) return;
        deleteHeaderMessage(messageId, scope);
    });
}

function headerLatestMineMessageId(items) {
    for (var i = items.length - 1; i >= 0; i -= 1) {
        if (items[i] && items[i].mine && items[i].message_type !== 'call' && items[i].message_type !== 'system') {
            return String(items[i].id);
        }
    }
    return null;
}

function renderHeaderSendStatus(msg, isLatestMine) {
    if (!msg || !msg.mine || !isLatestMine) return '';
    var status = msg.send_status || '';
    if (!status && String(msg.id || '').indexOf('local-') === 0) status = 'sending';
    if (status !== 'sending' && status !== 'sent' && status !== 'failed') return '';
    var label = status === 'sending' ? 'Sending...' : (status === 'sent' ? 'Sent' : 'Failed');
    return '<div class="comms-send-status is-' + status + '" role="status">' + label + '</div>';
}

function renderHeaderChatMessages(messages, opts) {
    opts = opts || {};
    var body = document.getElementById('commsChatModalBody');
    if (!body) return;
    var prevScroll = body.scrollTop;
    var items = Array.isArray(messages) ? messages.slice() : [];
    headerChatState.messages = items;
    if (!items.length) {
        hideHeaderReactionPicker();
        body.innerHTML = '<p class="comms-chat-modal-empty">No messages yet. Say hello.</p>';
        return;
    }
    var parts = [];
    var prevCreatedAt = null;
    var latestIndex = items.length - 1;
    var latestMineId = headerLatestMineMessageId(items);
    items.forEach(function (msg, index) {
        var insertedSep = false;
        if (shouldInsertHeaderDateSeparator(prevCreatedAt, msg.created_at)) {
            parts.push(renderHeaderDateSeparator(msg.created_at));
            insertedSep = true;
        }
        if (index === latestIndex && !insertedSep) {
            parts.push(renderHeaderDateSeparator(msg.created_at || prevCreatedAt));
        }

        if (msg.message_type === 'call' || msg.message_type === 'system') {
            var callCls = headerCallBubbleClass(msg.body);
            var callIcon = '<svg class="comms-chat-call-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>';
            parts.push('<div class="comms-chat-bubble-wrap ' + (msg.mine ? 'is-mine' : 'is-theirs') + ' is-call" data-id="' + msg.id + '">' +
                '<div class="comms-chat-bubble-row">' +
                '<div class="comms-chat-bubble ' + callCls + '">' +
                callIcon +
                '<div class="comms-chat-bubble-text">' + escapeCommsHtml(msg.body || '') + '</div>' +
                '</div>' +
                '<div class="comms-chat-bubble-tools">' + renderHeaderMessageActions(msg, false) + '</div>' +
                '</div></div>');
            prevCreatedAt = msg.created_at;
            return;
        }
        var mine = Boolean(msg.mine);
        var pickerOpen = Number(headerChatState.openReactionPickerId) === Number(msg.id);
        var editing = Number(headerChatState.editingId) === Number(msg.id);
        if (msg.deleted_for_all || msg.message_type === 'deleted') {
            parts.push('<div class="comms-chat-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + '" data-id="' + msg.id + '">' +
                '<div class="comms-chat-bubble-row">' +
                '<div class="comms-chat-bubble comms-chat-bubble--deleted">This message was deleted</div>' +
                '<div class="comms-chat-bubble-tools">' + renderHeaderMessageActions(msg, true) + '</div>' +
                '</div></div>');
            prevCreatedAt = msg.created_at;
            return;
        }

        var hasAttachments = Array.isArray(msg.attachments) && msg.attachments.length > 0;
        var hasTextBody = !!String(msg.body || '').trim() || editing;
        var attachmentOnly = hasAttachments && !hasTextBody;
        var attachHtml = renderHeaderAttachmentBubbles(msg.attachments);
        var bodyHtml = '';
        if (editing) {
            bodyHtml = '<div class="comms-chat-edit-panel" role="group" aria-label="Edit message">' +
                '<label class="visually-hidden" for="commsHeaderEdit' + msg.id + '">Edit message</label>' +
                '<textarea id="commsHeaderEdit' + msg.id + '" class="comms-chat-edit-input" data-edit-input="' + msg.id + '" rows="3">' + escapeCommsHtml(msg.body || '') + '</textarea>' +
                '<div class="comms-chat-edit-actions">' +
                '<button type="button" class="comms-chat-edit-cancel" data-edit-cancel="' + msg.id + '">Cancel</button>' +
                '<button type="button" class="comms-chat-edit-save" data-edit-save="' + msg.id + '">Save</button>' +
                '</div></div>';
        } else if (msg.body) {
            bodyHtml = '<div class="comms-chat-bubble-text">' + escapeCommsHtml(msg.body) +
                (msg.edited ? ' <em class="comms-chat-edited">edited</em>' : '') + '</div>';
        }

        var bubbleInner;
        if (editing) {
            bubbleInner = bodyHtml;
        } else if (attachmentOnly) {
            bubbleInner = '<div class="comms-chat-bubble ' + (mine ? 'is-mine' : 'is-theirs') + ' has-attachment-only">' +
                attachHtml + '</div>';
        } else {
            bubbleInner = '<div class="comms-chat-bubble ' + (mine ? 'is-mine' : 'is-theirs') + (hasAttachments ? ' has-attachment' : '') + '">' +
                attachHtml + bodyHtml + '</div>';
        }

        parts.push('<div class="comms-chat-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + (pickerOpen ? ' is-picker-open' : '') + '" data-id="' + msg.id + '">' +
            '<div class="comms-chat-bubble-row">' +
            bubbleInner +
            (!editing ? '<div class="comms-chat-bubble-tools">' +
            renderHeaderMessageActions(msg, false) +
            '<button type="button" class="comms-chat-react-btn" data-react-toggle="' + msg.id + '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' + headerReactBtnIcon + '</button>' +
            '</div>' : '') +
            '</div>' +
            (!editing ? renderHeaderReactionPicker(msg.id) : '') +
            (!editing ? renderHeaderReactionChips(msg.reactions, msg.id) : '') +
            renderHeaderSendStatus(msg, latestMineId != null && String(msg.id) === latestMineId) +
            '</div>');
        prevCreatedAt = msg.created_at;
    });
    body.innerHTML = parts.join('');
    if (opts.preserveScroll) {
        body.scrollTop = prevScroll;
    } else {
        body.scrollTop = body.scrollHeight;
    }
    if (headerChatState.openReactionPickerId) {
        var pickerBtn = body.querySelector('[data-react-toggle="' + headerChatState.openReactionPickerId + '"]');
        if (pickerBtn) {
            positionHeaderReactionPicker(pickerBtn);
        } else {
            hideHeaderReactionPicker();
        }
    }
    if (headerChatState.editingId) {
        var editEl = document.querySelector('#commsChatModalBody [data-edit-input="' + headerChatState.editingId + '"]');
        if (editEl) {
            wireHeaderEditLimit(editEl);
            if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
                window.Comms.resizeGrowTextarea(editEl, 6, 2);
            }
        }
    }
    if (window.Comms && typeof window.Comms.wireDocumentPreview === 'function') {
        window.Comms.wireDocumentPreview(body);
    }
}

function cloneHeaderReactions(reactions) {
    return (Array.isArray(reactions) ? reactions : []).map(function (r) {
        return { emoji: r.emoji, count: Number(r.count || 0), mine: !!r.mine };
    });
}

function optimisticHeaderToggleReactions(reactions, emoji) {
    var list = cloneHeaderReactions(reactions).filter(function (r) { return r.count > 0; });
    var mineEntry = null;
    var target = null;
    list.forEach(function (r) {
        if (r.mine) mineEntry = r;
        if (r.emoji === emoji) target = r;
    });

    if (target && target.mine) {
        target.count -= 1;
        target.mine = false;
    } else {
        if (mineEntry && mineEntry !== target) {
            mineEntry.count -= 1;
            mineEntry.mine = false;
        }
        if (target) {
            target.count += 1;
            target.mine = true;
        } else {
            list.push({ emoji: emoji, count: 1, mine: true });
        }
    }

    return list.filter(function (r) { return Number(r.count) > 0; });
}

function applyHeaderMessageReactions(messageId, reactions, opts) {
    opts = opts || {};
    var msg = headerChatState.messages.find(function (m) {
        return Number(m.id) === Number(messageId);
    });
    if (!msg) return;
    msg.reactions = Array.isArray(reactions) ? reactions : [];
    if (opts.closePicker !== false) {
        hideHeaderReactionPicker();
    }
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
    if (headerChatState.conversationId) {
        writeHeaderChatCache(headerChatState.conversationId, {
            messages: headerChatState.messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    }
}

function patchHeaderRemoteReaction(messageId, eventType, row) {
    var msg = headerChatState.messages.find(function (m) {
        return Number(m.id) === Number(messageId);
    });
    if (!msg || !row) return false;
    var boot = document.getElementById('commsRealtimeBoot');
    var list = cloneHeaderReactions(msg.reactions);
    var emoji = String(row.emoji || '');
    if (!emoji) return false;
    var isMine = boot
        && Number(row.user_id) === Number(boot.dataset.currentUserId)
        && String(row.user_type || '') === String(boot.dataset.portalUserType || '');
    if (isMine && headerChatState.reactSkipRealtime[String(messageId)]) return true;
    var entry = null;
    list.forEach(function (r) {
        if (r.emoji === emoji) entry = r;
    });

    if (eventType === 'DELETE') {
        if (!entry) return true;
        entry.count = Math.max(0, entry.count - 1);
        if (isMine) entry.mine = false;
    } else if (eventType === 'INSERT') {
        if (entry) {
            entry.count += 1;
            if (isMine) entry.mine = true;
        } else {
            list.push({ emoji: emoji, count: 1, mine: isMine });
        }
    } else if (eventType === 'UPDATE') {
        var oldEmoji = String(row._old_emoji || '');
        if (!oldEmoji || oldEmoji === emoji) return false;
        var oldEntry = null;
        list.forEach(function (r) {
            if (r.emoji === oldEmoji) oldEntry = r;
        });
        if (oldEntry) {
            oldEntry.count = Math.max(0, oldEntry.count - 1);
            if (isMine) oldEntry.mine = false;
        }
        if (entry) {
            entry.count += 1;
            if (isMine) entry.mine = true;
        } else {
            list.push({ emoji: emoji, count: 1, mine: isMine });
        }
    } else {
        return false;
    }

    applyHeaderMessageReactions(messageId, list.filter(function (r) { return Number(r.count) > 0; }), { closePicker: false });
    return true;
}

function toggleHeaderChatReaction(messageId, emoji) {
    if (!messageId || !emoji) return;
    var key = String(messageId);
    var msg = headerChatState.messages.find(function (m) {
        return Number(m.id) === Number(messageId);
    });
    if (!msg) return;

    var previous = cloneHeaderReactions(msg.reactions);
    var next = optimisticHeaderToggleReactions(previous, emoji);
    var added = next.some(function (r) { return r.emoji === emoji && r.mine; })
        && !previous.some(function (r) { return r.emoji === emoji && r.mine; });

    var seq = (headerChatState.reactSeq[key] || 0) + 1;
    headerChatState.reactSeq[key] = seq;
    headerChatState.reactSkipRealtime[key] = true;

    if (added) playHeaderChatReactionSound();
    applyHeaderMessageReactions(messageId, next);

    fetch('/api/communications/messages/' + encodeURIComponent(messageId) + '/reactions', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ emoji: emoji })
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (headerChatState.reactSeq[key] !== seq) return;
        if (!data) {
            applyHeaderMessageReactions(messageId, previous, { closePicker: false });
            return;
        }
        applyHeaderMessageReactions(
            data.message_id || messageId,
            Array.isArray(data.reactions) ? data.reactions : next,
            { closePicker: false }
        );
    }).catch(function () {
        if (headerChatState.reactSeq[key] !== seq) return;
        applyHeaderMessageReactions(messageId, previous, { closePicker: false });
    }).finally(function () {
        if (headerChatState.reactSeq[key] !== seq) return;
        window.setTimeout(function () {
            if (headerChatState.reactSeq[key] !== seq) return;
            delete headerChatState.reactSkipRealtime[key];
        }, 500);
    });
}

function headerChatCacheKey(conversationId) {
    return 'comms:thread:' + String(conversationId);
}

function readHeaderChatCache(conversationId) {
    if (window.Comms && typeof window.Comms.readThreadCache === 'function') {
        var payload = window.Comms.readThreadCache(conversationId);
        if (payload && Array.isArray(payload.messages)) return payload;
    }
    if (window.SkLocalCache && typeof window.SkLocalCache.get === 'function') {
        var entry = window.SkLocalCache.get(headerChatCacheKey(conversationId));
        if (entry && entry.payload && Array.isArray(entry.payload.messages)) return entry.payload;
    }
    return null;
}

function writeHeaderChatCache(conversationId, payload) {
    var stored = {
        messages: payload.messages || [],
        reactionEmojis: payload.reactionEmojis || payload.reaction_emojis || [],
        ts: Date.now()
    };
    if (window.Comms && typeof window.Comms.writeThreadCache === 'function') {
        window.Comms.writeThreadCache(conversationId, stored);
        return;
    }
    if (window.SkLocalCache && typeof window.SkLocalCache.set === 'function') {
        window.SkLocalCache.set(headerChatCacheKey(conversationId), stored);
    }
}

function refreshHeaderChatReaction(messageId, meta) {
    var conversationId = headerChatState.conversationId;
    if (!conversationId || !messageId) return;
    if (!headerChatState.messages.some(function (m) { return Number(m.id) === Number(messageId); })) return;
    if (headerChatState.reactSkipRealtime[String(messageId)]) return;

    if (meta && meta.eventType && patchHeaderRemoteReaction(messageId, meta.eventType, meta.row)) {
        return;
    }

    var watchSeq = headerChatState.reactSeq[String(messageId)] || 0;
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data || Number(headerChatState.conversationId) !== Number(conversationId)) return;
        if ((headerChatState.reactSeq[String(messageId)] || 0) !== watchSeq) return;
        if (headerChatState.reactSkipRealtime[String(messageId)]) return;
        var fresh = (data.messages || []).find(function (m) {
            return Number(m.id) === Number(messageId);
        });
        if (!fresh) return;
        applyHeaderMessageReactions(messageId, Array.isArray(fresh.reactions) ? fresh.reactions : [], { closePicker: false });
    }).catch(function () { /* ignore */ });
}

window.onCommsHeaderReactionChange = function (messageId, meta) {
    refreshHeaderChatReaction(messageId, meta || null);
};

window.onCommsHeaderMessageInsert = function (message) {
    if (!message || Number(message.conversation_id) !== Number(headerChatState.conversationId)) return;
    if (headerChatState.messages.some(function (m) { return Number(m.id) === Number(message.id); })) return;
    var boot = document.getElementById('commsRealtimeBoot');
    var mine = boot
        && Number(message.sender_id) === Number(boot.dataset.currentUserId)
        && message.sender_type === boot.dataset.portalUserType;
    message.mine = !!mine;
    if (!message.reactions) message.reactions = [];
    headerChatState.messages.push(message);
    renderHeaderChatMessages(headerChatState.messages);
    if (typeof window.refreshMessagesPopover === 'function') {
        window.refreshMessagesPopover();
    }
};

window.onCommsHeaderMessageChange = function (row) {
    if (!row || Number(row.conversation_id) !== Number(headerChatState.conversationId)) return;
    var idx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(row.id); });
    if (idx === -1) return;
    var skipToast = !!headerChatState.msgChangeSkipToast[String(row.id)];
    if (row.deleted_for_all_at) {
        headerChatState.messages[idx].deleted_for_all = true;
        headerChatState.messages[idx].message_type = 'deleted';
        headerChatState.messages[idx].body = null;
        headerChatState.messages[idx].attachments = [];
        if (!skipToast && window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message deleted.', 'info');
        }
    } else if (row.body != null) {
        var prevBody = headerChatState.messages[idx].body;
        headerChatState.messages[idx].body = row.body;
        headerChatState.messages[idx].edited = true;
        if (!skipToast && String(prevBody || '') !== String(row.body || '')
            && window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message edited.', 'info');
        }
    }
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
};

function loadHeaderChatMessages(conversationId) {
    if (headerChatState.loading) return;
    headerChatState.loading = true;
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data || Number(headerChatState.conversationId) !== Number(conversationId)) return;
        var messages = Array.isArray(data.messages) ? data.messages : [];
        if (Array.isArray(data.reaction_emojis) && data.reaction_emojis.length) {
            headerChatState.reactionEmojis = data.reaction_emojis;
        }
        renderHeaderChatMessages(messages);
        writeHeaderChatCache(conversationId, {
            messages: messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    }).catch(function () {
        var body = document.getElementById('commsChatModalBody');
        if (body && !headerChatState.messages.length) {
            body.innerHTML = '<p class="comms-chat-modal-empty">Unable to load messages.</p>';
        }
    }).finally(function () {
        headerChatState.loading = false;
    });
}

function markHeaderConversationRead(conversationId) {
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/read', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin'
    }).then(function () {
        if (window.CommsRealtime && typeof window.CommsRealtime.refreshUnread === 'function') {
            window.CommsRealtime.refreshUnread();
        } else if (typeof window.refreshMessagesUnreadBadge === 'function') {
            window.refreshMessagesUnreadBadge();
        }
    }).catch(function () { /* ignore */ });
}

function sendHeaderChatMessage(event) {
    if (event) event.preventDefault();
    var input = document.getElementById('commsChatModalInput');
    var conversationId = headerChatState.conversationId;
    if (!input || !conversationId) return;
    var raw = (window.Comms && typeof window.Comms.normalizeMessageBody === 'function')
        ? window.Comms.normalizeMessageBody(input.value)
        : String(input.value || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    var body = raw.trim() === '' ? '' : raw;
    var files = headerChatState.pendingAttachments.slice();
    if (!body.trim() && !files.length) {
        if (raw.length > 0 && window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot be only spaces.', 'error');
        }
        return;
    }
    var count = (window.Comms && typeof window.Comms.measureMessageLength === 'function')
        ? window.Comms.measureMessageLength(raw)
        : raw.length;
    if (body && count > 1000) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot exceed 1000 characters.', 'error');
        }
        return;
    }
    if (headerChatState.sending && files.length) return;

    stopHeaderLocalTyping(conversationId);

    var sendBtn = document.getElementById('commsChatModalSend');
    input.value = '';
    clearHeaderChatAttachments();

    var tempId = 'local-' + Date.now();
    if (body) {
        headerChatState.messages.push({
            id: tempId,
            conversation_id: conversationId,
            body: body,
            message_type: 'text',
            mine: true,
            send_status: 'sending',
            created_at: new Date().toISOString(),
            reactions: [],
            attachments: []
        });
        renderHeaderChatMessages(headerChatState.messages);
    }

    var headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCommsCsrfToken()
    };
    var jobs = [];

    if (body) {
        jobs.push(fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
            credentials: 'same-origin',
            body: JSON.stringify({ body: body })
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data || !data.message) return;
            var sent = Object.assign({}, data.message, { send_status: 'sent', mine: true });
            var realIdx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(sent.id); });
            var tempIdx = headerChatState.messages.findIndex(function (m) { return String(m.id) === tempId; });
            if (realIdx !== -1 && tempIdx !== -1) {
                headerChatState.messages[realIdx] = Object.assign({}, headerChatState.messages[realIdx], { send_status: 'sent', mine: true });
                headerChatState.messages.splice(tempIdx, 1);
            } else if (tempIdx !== -1) {
                headerChatState.messages[tempIdx] = sent;
            } else {
                headerChatState.messages.push(sent);
            }
            renderHeaderChatMessages(headerChatState.messages);
        }));
    }

    if (files.length) {
        headerChatState.sending = true;
        if (sendBtn) sendBtn.disabled = true;
        var fileTempId = 'local-att-' + Date.now();
        headerChatState.messages.push({
            id: fileTempId,
            conversation_id: conversationId,
            body: '',
            message_type: 'file',
            mine: true,
            send_status: 'sending',
            created_at: new Date().toISOString(),
            reactions: [],
            attachments: files.map(function (f) {
                return {
                    id: 0,
                    file_name: f.name,
                    file_type: (f.type || '').indexOf('image/') === 0 ? 'image' : 'file',
                    mime_type: f.type || null,
                    file_size: f.size || null,
                    storage_provider: 'local',
                    url: null,
                    download_url: '#'
                };
            })
        });
        renderHeaderChatMessages(headerChatState.messages);
        var fd = new FormData();
        files.forEach(function (f, i) {
            fd.append('attachments[' + i + ']', f, f.name);
        });
        jobs.push(fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: fd
        }).then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return null; }).then(function (err) {
                    var msg = (err && (err.message || (err.errors && Object.values(err.errors)[0] && Object.values(err.errors)[0][0]))) || 'Failed to send attachment.';
                    if (window.Comms && typeof window.Comms.showToast === 'function') {
                        window.Comms.showToast(msg, 'error');
                    } else if (typeof window.showToast === 'function') {
                        window.showToast(msg, 'error');
                    }
                    headerChatState.messages = headerChatState.messages.filter(function (m) {
                        return String(m.id) !== fileTempId;
                    });
                    renderHeaderChatMessages(headerChatState.messages);
                    return null;
                });
            }
            return r.json();
        }).then(function (data) {
            if (!data || !data.message) return;
            var sent = Object.assign({}, data.message, { send_status: 'sent', mine: true });
            var realIdx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(sent.id); });
            var tempIdx = headerChatState.messages.findIndex(function (m) { return String(m.id) === fileTempId; });
            if (realIdx !== -1 && tempIdx !== -1) {
                headerChatState.messages[realIdx] = Object.assign({}, headerChatState.messages[realIdx], { send_status: 'sent', mine: true });
                headerChatState.messages.splice(tempIdx, 1);
            } else if (tempIdx !== -1) {
                headerChatState.messages[tempIdx] = sent;
            } else {
                headerChatState.messages.push(sent);
            }
            renderHeaderChatMessages(headerChatState.messages);
        }));
    }

    Promise.all(jobs).finally(function () {
        headerChatState.sending = false;
        if (sendBtn) sendBtn.disabled = false;
        if (input) input.focus();
        writeHeaderChatCache(conversationId, {
            messages: headerChatState.messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    });
}

function wireHeaderChatAttachButtons() {
    var attachBtn = document.getElementById('commsChatAttachBtn');
    var attachMenu = document.getElementById('commsChatAttachMenu');
    var pickCamera = document.getElementById('commsChatPickCamera');
    var pickPhotos = document.getElementById('commsChatPickPhotos');
    var pickFiles = document.getElementById('commsChatPickFiles');
    var cameraInput = document.getElementById('commsChatCameraInput');
    var photoInput = document.getElementById('commsChatPhotoInput');
    var fileInput = document.getElementById('commsChatFileInput');
    var clearBtn = document.getElementById('commsChatAttachClear');
    if (!attachBtn || attachBtn.dataset.wired === 'true') return;
    if (attachMenu) attachMenu.hidden = true;

    attachBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (attachMenu) attachMenu.hidden = !attachMenu.hidden;
    });
    if (pickCamera && cameraInput) {
        pickCamera.addEventListener('click', function () {
            cameraInput.click();
            if (attachMenu) attachMenu.hidden = true;
        });
    }
    if (pickPhotos && photoInput) {
        pickPhotos.addEventListener('click', function () { photoInput.click(); if (attachMenu) attachMenu.hidden = true; });
    }
    if (pickFiles && fileInput) {
        pickFiles.addEventListener('click', function () { fileInput.click(); if (attachMenu) attachMenu.hidden = true; });
    }
    if (cameraInput) {
        cameraInput.addEventListener('change', function () {
            if (cameraInput.files && cameraInput.files.length) pushHeaderFiles(cameraInput.files, 'image');
            cameraInput.value = '';
        });
    }
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            if (photoInput.files && photoInput.files.length) pushHeaderFiles(photoInput.files, 'image');
            photoInput.value = '';
        });
    }
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) pushHeaderFiles(fileInput.files, 'file');
            fileInput.value = '';
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () { clearHeaderChatAttachments(); });
    }
    document.addEventListener('click', function (e) {
        if (attachMenu && !attachMenu.hidden && !e.target.closest('.comms-chat-attach-wrap')) {
            attachMenu.hidden = true;
        }
    });
    attachBtn.dataset.wired = 'true';
}

function wireHeaderChatCallButtons() {
    var voiceBtn = document.getElementById('commsChatModalVoiceBtn');
    var videoBtn = document.getElementById('commsChatModalVideoBtn');
    if (voiceBtn && voiceBtn.dataset.wired !== 'true') {
        voiceBtn.addEventListener('click', function () {
            var cid = headerChatState.conversationId;
            if (!cid) return;
            if (window.CommsWebRTC && typeof window.CommsWebRTC.startCall === 'function') {
                window.CommsWebRTC.startCall(cid, 'voice', null);
            } else {
                window.location.href = '/communications?conversation=' + encodeURIComponent(cid);
            }
        });
        voiceBtn.dataset.wired = 'true';
    }
    if (videoBtn && videoBtn.dataset.wired !== 'true') {
        videoBtn.addEventListener('click', function () {
            var cid = headerChatState.conversationId;
            if (!cid) return;
            if (window.CommsWebRTC && typeof window.CommsWebRTC.startCall === 'function') {
                window.CommsWebRTC.startCall(cid, 'video', null);
            } else {
                window.location.href = '/communications?conversation=' + encodeURIComponent(cid);
            }
        });
        videoBtn.dataset.wired = 'true';
    }
}

function wireHeaderChatModal() {
    var modal = document.getElementById('commsChatModal');
    var list = document.getElementById('commsMsgList');
    var form = document.getElementById('commsChatModalForm');
    var input = document.getElementById('commsChatModalInput');
    wireHeaderChatAttachButtons();
    wireHeaderChatCallButtons();
    wireFullscreenChatControls();
    var chatBody = document.getElementById('commsChatModalBody');
    if (chatBody && chatBody.dataset.menuScroll !== 'true') {
        chatBody.addEventListener('scroll', function () {
            closeHeaderMessageMenus();
            if (headerChatState.openReactionPickerId) {
                hideHeaderReactionPicker();
            }
        }, { passive: true });
        chatBody.dataset.menuScroll = 'true';
    }
    var headerPicker = getHeaderReactionPickerEl();
    if (headerPicker && headerPicker.dataset.wired !== 'true') {
        headerPicker.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-react-emoji]');
            if (!pick) return;
            e.preventDefault();
            e.stopPropagation();
            var mid = Number(headerChatState.openReactionPickerId);
            var emoji = pick.getAttribute('data-react-emoji');
            if (mid && emoji) toggleHeaderChatReaction(mid, emoji);
        });
        headerPicker.dataset.wired = 'true';
    }
    if (document.documentElement.dataset.commsHeaderPickerResize !== 'true') {
        window.addEventListener('resize', function () {
            if (headerChatState.openReactionPickerId) {
                hideHeaderReactionPicker();
            }
        }, { passive: true });
        document.documentElement.dataset.commsHeaderPickerResize = 'true';
    }
    if (modal && modal.dataset.wired !== 'true') {
        modal.addEventListener('click', function (e) {
            if (e.target && e.target.closest('[data-comms-chat-close]')) {
                closeHeaderChatModal();
                return;
            }
            var editBtn = e.target.closest('[data-msg-edit]');
            if (editBtn && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                closeHeaderMessageMenus();
                headerChatState.editingId = Number(editBtn.getAttribute('data-msg-edit'));
                renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
                var editor = document.querySelector('#commsChatModalBody [data-edit-input="' + headerChatState.editingId + '"]');
                if (editor) {
                    wireHeaderEditLimit(editor);
                    editor.focus();
                }
                return;
            }
            var saveBtn = e.target.closest('[data-edit-save]');
            if (saveBtn) {
                e.preventDefault();
                saveHeaderEditedMessage(Number(saveBtn.getAttribute('data-edit-save')));
                return;
            }
            var cancelBtn = e.target.closest('[data-edit-cancel]');
            if (cancelBtn) {
                e.preventDefault();
                headerChatState.editingId = null;
                renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
                return;
            }
            var deleteChoice = e.target.closest('[data-msg-delete]');
            if (deleteChoice && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                closeHeaderMessageMenus();
                openHeaderDeletePrompt(Number(deleteChoice.getAttribute('data-msg-delete')));
                return;
            }
            var moreBtn = e.target.closest('[data-msg-more]');
            if (moreBtn && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                e.stopPropagation();
                var wrapMore = moreBtn.closest('.comms-chat-bubble-wrap');
                var menu = wrapMore ? wrapMore.querySelector('[data-msg-menu]') : null;
                var opening = menu && menu.hidden;
                closeHeaderMessageMenus();
                if (opening && menu) {
                    menu.hidden = false;
                    moreBtn.setAttribute('aria-expanded', 'true');
                    if (wrapMore) wrapMore.classList.add('is-menu-open');
                }
                return;
            }
            var peopleBtn = e.target.closest('[data-react-people]');
            if (peopleBtn && e.target.closest('#commsChatModal')) {
                e.preventDefault();
                e.stopPropagation();
                var pid = Number(peopleBtn.getAttribute('data-react-people'));
                var pmsg = headerChatState.messages.find(function (m) { return Number(m.id) === pid; });
                var emojiEl = e.target.closest('[data-react-people-emoji]');
                var filterEmoji = emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : '';
                if (window.Comms && Comms.openReactionModal) {
                    Comms.openReactionModal(pmsg ? pmsg.reactions : [], filterEmoji);
                } else if (window.Comms && Comms.toggleReactionPeople) {
                    Comms.toggleReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                        pinned: true,
                        emoji: filterEmoji
                    });
                }
                return;
            }
            var toggle = e.target.closest('[data-react-toggle]');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                var tid = Number(toggle.getAttribute('data-react-toggle'));
                if (Number(headerChatState.openReactionPickerId) === tid) {
                    hideHeaderReactionPicker();
                } else {
                    showHeaderReactionPicker(tid, toggle);
                }
                return;
            }
            var emojiBtn = e.target.closest('[data-react-emoji]');
            if (emojiBtn && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = emojiBtn.closest('.comms-chat-bubble-wrap');
                var mid = wrap ? Number(wrap.getAttribute('data-id')) : 0;
                var emoji = emojiBtn.getAttribute('data-react-emoji');
                if (mid && emoji) toggleHeaderChatReaction(mid, emoji);
            }
        });
        modal.addEventListener('pointerover', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn || !modal.contains(peopleBtn)) return;
            var pid = Number(peopleBtn.getAttribute('data-react-people'));
            var pmsg = headerChatState.messages.find(function (m) { return Number(m.id) === pid; });
            var emojiEl = e.target.closest('[data-react-people-emoji]');
            if (window.Comms && Comms.showReactionPeople) {
                Comms.showReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                    pinned: false,
                    emoji: emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : ''
                });
            }
        });
        modal.addEventListener('pointerout', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn) return;
            var related = e.relatedTarget;
            if (related && (peopleBtn.contains(related) || (related.closest && related.closest('#commsReactionPeople')))) {
                return;
            }
            if (window.Comms && Comms.scheduleHideReactionPeople) Comms.scheduleHideReactionPeople();
        });
        modal.dataset.wired = 'true';
    }
    if (document.documentElement.dataset.commsReactOutside !== 'true') {
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-msg-more]') && !e.target.closest('[data-msg-menu]')) {
                closeHeaderMessageMenus();
            }
            if (!headerChatState.openReactionPickerId) return;
            if (e.target.closest('[data-react-toggle]') || e.target.closest('.comms-chat-reaction-picker')) return;
            hideHeaderReactionPicker();
        });
        document.documentElement.dataset.commsReactOutside = 'true';
    }
    if (list && list.dataset.chatWired !== 'true') {
        list.addEventListener('click', function (e) {
            var item = e.target.closest('.comms-msg-item');
            if (!item) return;
            e.preventDefault();
            e.stopPropagation();
            var convId = Number(item.getAttribute('data-id') || 0);
            var userId = Number(item.getAttribute('data-user-id') || 0);
            var name = item.getAttribute('data-name') || 'Chat';
            var avatar = item.getAttribute('data-avatar') || '';
            if (convId > 0) {
                openHeaderChatModal(convId, name, avatar);
                return;
            }
            if (userId > 0) {
                startHeaderChatWithUser(userId, name, avatar);
            }
        });
        list.dataset.chatWired = 'true';
    }
    if (form && form.dataset.wired !== 'true') {
        form.addEventListener('submit', sendHeaderChatMessage);
        form.dataset.wired = 'true';
    }
    if (input && input.dataset.wired !== 'true') {
        input.removeAttribute('maxlength');
        input.addEventListener('input', function () {
            var next = clampHeaderMessageToMaxChars(input.value || '');
            if (next !== normalizeHeaderMessageBody(input.value || '')) {
                input.value = next;
                toastHeaderMessageCharLimit();
            } else if (next !== input.value) {
                input.value = next;
            }
            if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
                window.Comms.resizeGrowTextarea(input, 5, 1);
            }
            notifyHeaderComposerTyping(input.value);
        });
        input.addEventListener('paste', function (e) {
            var paste = (e.clipboardData || window.clipboardData);
            if (!paste) return;
            var incoming = paste.getData('text');
            if (incoming == null) return;
            e.preventDefault();
            var start = input.selectionStart || 0;
            var end = input.selectionEnd || 0;
            var value = input.value || '';
            var raw = value.slice(0, start) + incoming + value.slice(end);
            var next = clampHeaderMessageToMaxChars(raw);
            input.value = next;
            if (next !== normalizeHeaderMessageBody(raw)) toastHeaderMessageCharLimit();
            if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
                window.Comms.resizeGrowTextarea(input, 5, 1);
            }
            notifyHeaderComposerTyping(input.value);
        });
        input.addEventListener('keydown', function (e) {
            // Enter = new line. Ctrl/Cmd+Enter sends (same as See All / Officials).
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                sendHeaderChatMessage(e);
                return;
            }
            var current = normalizeHeaderMessageBody(input.value || '');
            if (e.key === 'Enter' && !(e.ctrlKey || e.metaKey)) {
                if ((current + '\n').length > 1000) {
                    e.preventDefault();
                    toastHeaderMessageCharLimit();
                }
                return;
            }
            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if ((current + e.key).length > 1000) {
                    e.preventDefault();
                    toastHeaderMessageCharLimit();
                }
            }
        });
        input.dataset.wired = 'true';
        if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
            window.Comms.resizeGrowTextarea(input, 5, 1);
        }
    }
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var reactionModal = document.getElementById('commsReactionModal');
        if (reactionModal && !reactionModal.hidden) return;
        var docModal = document.getElementById('commsDocModal');
        if (docModal && !docModal.hidden) return;
        closeHeaderChatModal();
    });
}

window.openHeaderChatModal = openHeaderChatModal;
window.closeHeaderChatModal = closeHeaderChatModal;
window.openHeaderMessagesFullscreen = openHeaderMessagesFullscreen;

// ── Profile Dropdown ──
window.toggleProfileDropdown = function (e) {
    e.stopPropagation();
    var dd = document.getElementById('profileDropdown');
    var btn = document.querySelector('.profile-btn');
    closeNotifPopover();
    closeMessagesPopover();
    if (dd) {
        dd.classList.toggle('show');
    }
    if (btn) {
        btn.classList.toggle('open');
    }
};

document.addEventListener('click', function (e) {
    var notifMenu = document.getElementById('notifMenu');
    var msgMenu = document.getElementById('commsMsgMenu');
    var profileWrapper = document.querySelector('.profile-dropdown-wrapper');
    var chatModal = document.getElementById('commsChatModal');

    if (chatModal && !chatModal.hidden && chatModal.contains(e.target)) {
        return;
    }

    if (notifMenu && !notifMenu.contains(e.target)) {
        closeNotifPopover();
    }
    if (msgMenu && !msgMenu.contains(e.target)) {
        closeMessagesPopover();
    }
    if (profileWrapper && !profileWrapper.contains(e.target)) {
        var profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown) {
            profileDropdown.classList.remove('show');
        }
        var profileBtn = document.querySelector('.profile-btn');
        if (profileBtn) {
            profileBtn.classList.remove('open');
        }
    }
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll(
        'input[type="email"], input[name="email"], input[name="new_email"], input[name="current_email"], input[name="pending_email"]'
    ).forEach(function (input) {
        var apply = function () {
            var start = input.selectionStart;
            var end = input.selectionEnd;
            var lower = String(input.value || '').toLowerCase();
            if (input.value !== lower) {
                input.value = lower;
                if (typeof start === 'number' && typeof end === 'number' && document.activeElement === input) {
                    input.setSelectionRange(start, end);
                }
            }
        };
        input.addEventListener('input', apply);
        input.addEventListener('blur', function () {
            input.value = String(input.value || '').trim().toLowerCase();
        });
        apply();
    });

    var notifPopover = document.getElementById('notifPopover');
    var notifList = document.getElementById('notifList');
    var markAllBtn = document.getElementById('notifMarkAllBtn');
    var msgPopover = document.getElementById('commsMsgPopover');
    wireHeaderChatModal();
    syncFederationsMessagesPageHeaderBtn();
    wireFederationsSeeAllMessagesLinks();
    openPendingFederationsHeaderChat();
    // Peer header may paint after chat.js boots — refresh minimize label once ready.
    window.setTimeout(syncFederationsMessagesPageHeaderBtn, 400);

    if (notifPopover) {
        notifPopover.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    if (msgPopover) {
        msgPopover.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    function getNotifCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function markNotifItemRead(item) {
        if (!item) return;
        var id = item.getAttribute('data-id');
        var actionUrl = item.getAttribute('data-action-url') || '';
        var wasUnread = item.classList.contains('notif-unread');

        if (wasUnread && id) {
            try {
                var response = await fetch('/api/sk-federations/notifications/' + id + '/read', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getNotifCsrfToken(),
                    },
                    credentials: 'same-origin',
                });
                var data = await response.json();
                if (data && typeof data.unread_count === 'number') {
                    updateNotifBadge(data.unread_count);
                } else {
                    updateNotifBadge(Math.max(0, getUnreadTotal() - 1));
                }
            } catch (err) {
                updateNotifBadge(Math.max(0, getUnreadTotal() - 1));
            }

            item.classList.remove('notif-unread');
            var dot = item.querySelector('.notif-unread-dot');
            if (dot) {
                dot.remove();
            }
        }

        if (actionUrl) {
            window.location.href = actionUrl;
        }
    }

    if (notifList) {
        notifList.addEventListener('click', function (e) {
            var item = e.target.closest('.notif-item');
            if (!item) return;
            markNotifItemRead(item);
        });

        notifList.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var item = e.target.closest('.notif-item');
            if (!item) return;
            e.preventDefault();
            markNotifItemRead(item);
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', async function (e) {
            e.stopPropagation();
            try {
                var response = await fetch('/api/sk-federations/notifications/read-all', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getNotifCsrfToken(),
                    },
                    credentials: 'same-origin',
                });
                var data = await response.json();
                updateNotifBadge(data && typeof data.unread_count === 'number' ? data.unread_count : 0);
            } catch (err) {
                updateNotifBadge(0);
            }
            if (notifList) {
                notifList.querySelectorAll('.notif-unread').forEach(function (item) {
                    item.classList.remove('notif-unread');
                    var dot = item.querySelector('.notif-unread-dot');
                    if (dot) {
                        dot.remove();
                    }
                });
            }
        });
    }

    updateNotifBadge(getUnreadTotal());
    refreshFederationsNotifications(true);
    window.setInterval(function () {
        if (document.hidden) return;
        refreshFederationsNotifications(false);
    }, 20000);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshFederationsNotifications(true);
        }
    });
});

// ── Logout Modal ──
var logoutInProgress = false;

window.showLogoutModal = function (event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    var modal = document.getElementById('logoutModal');
    if (!modal) {
        return;
    }

    var profileDropdown = document.getElementById('profileDropdown');
    if (profileDropdown) {
        profileDropdown.classList.remove('show');
    }
    var profileBtn = document.querySelector('.profile-btn');
    if (profileBtn) {
        profileBtn.classList.remove('open');
    }

    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    var confirmBtn = document.getElementById('logoutConfirmBtn');
    if (confirmBtn && !logoutInProgress) {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Yes, Logout';
        requestAnimationFrame(function () {
            confirmBtn.focus();
        });
    }
};

window.closeLogoutModal = function () {
    if (logoutInProgress) {
        return;
    }
    var modal = document.getElementById('logoutModal');
    if (!modal) {
        return;
    }
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
};

window.confirmLogout = function () {
    if (logoutInProgress) {
        return;
    }
    logoutInProgress = true;

    var confirmBtn = document.getElementById('logoutConfirmBtn');
    var cancelBtn = document.getElementById('logoutCancelBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Logging out...';
    }
    if (cancelBtn) {
        cancelBtn.disabled = true;
    }

    var form = document.getElementById('logoutForm');
    if (form) {
        form.submit();
        return;
    }

    // Fallback if form markup is missing.
    var logoutUrl = window.logoutRoute || '/logout';
    var csrfToken = getCsrfToken();
    var fallback = document.createElement('form');
    fallback.method = 'POST';
    fallback.action = logoutUrl;
    if (csrfToken) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = '_token';
        inp.value = csrfToken;
        fallback.appendChild(inp);
    }
    document.body.appendChild(fallback);
    fallback.submit();
};

document.addEventListener('DOMContentLoaded', function () {
    var logoutModal = document.getElementById('logoutModal');
    var logoutTrigger = document.getElementById('logoutTriggerBtn');
    var logoutConfirmBtn = document.getElementById('logoutConfirmBtn');
    var logoutCancelBtn = document.getElementById('logoutCancelBtn');
    var logoutCloseBtn = document.getElementById('logoutModalCloseBtn');

    if (logoutTrigger) {
        logoutTrigger.addEventListener('click', window.showLogoutModal);
    }

    if (logoutConfirmBtn) {
        logoutConfirmBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            window.confirmLogout();
        });
    }

    if (logoutCancelBtn) {
        logoutCancelBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            window.closeLogoutModal();
        });
    }

    if (logoutCloseBtn) {
        logoutCloseBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            window.closeLogoutModal();
        });
    }

    if (logoutModal) {
        logoutModal.setAttribute('aria-hidden', 'true');
        logoutModal.addEventListener('click', function (event) {
            if (event.target === logoutModal) {
                window.closeLogoutModal();
            }
        });
    }

    initSessionTimeoutWarning();
    initSessionExpiredFetchGuard();
});

function getCsrfToken() {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    return csrfMeta ? csrfMeta.getAttribute('content') : '';
}

function redirectToLoginAfterExpiry() {
    var loginUrl = window.loginRoute || '/login';
    window.location.replace(loginUrl);
}

function initSessionExpiredFetchGuard() {
    if (window.__skFedSessionFetchPatched) {
        return;
    }
    window.__skFedSessionFetchPatched = true;

    var originalFetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
        return originalFetch(input, init).then(function (response) {
            if (response.status !== 401) {
                return response;
            }

            var requestUrl = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            if (requestUrl && (requestUrl.indexOf('/login') !== -1 || requestUrl.indexOf('/csrf-token') !== -1)) {
                return response;
            }

            return response.clone().json().then(function (payload) {
                if (payload && payload.session_expired) {
                    redirectToLoginAfterExpiry();
                }
                return response;
            }).catch(function () {
                return response;
            });
        });
    };
}

function initSessionTimeoutWarning() {
    var config = window.sessionTimeoutConfig || {};
    var timeoutMinutes = Number(config.timeoutMinutes || 0);
    var warningMinutes = Number(config.warningMinutes || 0);
    var lastActivityAt = Number(config.lastActivityAt || 0);
    var modal = document.getElementById('sessionTimeoutModal');

    if (!modal || !timeoutMinutes || warningMinutes <= 0 || warningMinutes >= timeoutMinutes || !lastActivityAt) {
        return;
    }

    var warningTimer = null;
    var expireTimer = null;
    var warningShown = false;
    var continueBtn = document.getElementById('sessionTimeoutContinueBtn');
    var logoutBtn = document.getElementById('sessionTimeoutLogoutBtn');
    var messageEl = document.getElementById('sessionTimeoutMessage');

    function clearTimers() {
        if (warningTimer) {
            clearTimeout(warningTimer);
            warningTimer = null;
        }
        if (expireTimer) {
            clearTimeout(expireTimer);
            expireTimer = null;
        }
    }

    function hideWarning() {
        warningShown = false;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    function showWarning(secondsRemaining) {
        if (warningShown) {
            return;
        }
        warningShown = true;
        var minutesLeft = Math.max(1, Math.ceil(secondsRemaining / 60));
        if (messageEl) {
            messageEl.textContent = 'Your session will expire in ' + minutesLeft + ' minute' + (minutesLeft === 1 ? '' : 's') + ' due to inactivity.';
        }
        modal.hidden = false;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function scheduleFrom(activityTimestamp) {
        clearTimers();
        hideWarning();

        var nowSec = Math.floor(Date.now() / 1000);
        var timeoutAt = activityTimestamp + (timeoutMinutes * 60);
        var warnAt = timeoutAt - (warningMinutes * 60);
        var msUntilWarn = (warnAt - nowSec) * 1000;
        var msUntilExpire = (timeoutAt - nowSec) * 1000;

        if (msUntilExpire <= 0) {
            forceServerSessionCheck();
            return;
        }

        if (msUntilWarn <= 0) {
            showWarning(Math.max(1, Math.floor(msUntilExpire / 1000)));
        } else {
            warningTimer = setTimeout(function () {
                showWarning(warningMinutes * 60);
            }, msUntilWarn);
        }

        expireTimer = setTimeout(function () {
            forceServerSessionCheck();
        }, msUntilExpire);
    }

    function forceServerSessionCheck() {
        var continueUrl = window.sessionContinueRoute || '/session/continue';
        fetch(continueUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken() || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).catch(function () {
            // Ignore network errors; redirect anyway.
        }).finally(function () {
            redirectToLoginAfterExpiry();
        });
    }

    function continueSession() {
        var continueUrl = window.sessionContinueRoute || '/session/continue';
        if (continueBtn) {
            continueBtn.disabled = true;
        }

        fetch(continueUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken() || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('continue_failed');
            }
            return response.json();
        }).then(function (data) {
            var refreshed = Number(data && data.last_activity_at ? data.last_activity_at : Math.floor(Date.now() / 1000));
            window.sessionTimeoutConfig.lastActivityAt = refreshed;
            scheduleFrom(refreshed);
            try {
                localStorage.setItem('sk_fed_session_activity', String(refreshed));
            } catch (e) {
                // Ignore storage errors.
            }
        }).catch(function () {
            redirectToLoginAfterExpiry();
        }).finally(function () {
            if (continueBtn) {
                continueBtn.disabled = false;
            }
        });
    }

    if (continueBtn) {
        continueBtn.addEventListener('click', continueSession);
    }
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function () {
            if (typeof window.confirmLogout === 'function') {
                window.confirmLogout();
            } else {
                redirectToLoginAfterExpiry();
            }
        });
    }

    window.addEventListener('storage', function (event) {
        if (event.key !== 'sk_fed_session_activity' || !event.newValue) {
            return;
        }
        var synced = Number(event.newValue);
        if (!synced) {
            return;
        }
        window.sessionTimeoutConfig.lastActivityAt = synced;
        scheduleFrom(synced);
    });

    scheduleFrom(lastActivityAt);
}

/* Communications unread badge fallback when Realtime is not loaded. */
(function () {
    var badge = document.getElementById('commsMsgBadge');
    if (!badge) return;
    var url = '/api/communications/unread-count';
    var csrf = document.querySelector('meta[name=\"csrf-token\"]');
    function refresh() {
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
            },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data) return;
            var n = Number(data.unread_count || 0);
            badge.dataset.unreadTotal = String(n);
            if (n <= 0) {
                badge.style.display = 'none';
                badge.textContent = '0';
                return;
            }
            badge.style.display = '';
            badge.textContent = n > 99 ? '99+' : String(n);
        }).catch(function () { /* ignore */ });
    }
    setInterval(function () {
        if (window.CommsRealtime && typeof window.CommsRealtime.refreshUnread === 'function') {
            return;
        }
        refresh();
    }, 30000);
})();
