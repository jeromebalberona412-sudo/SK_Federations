/**
 * SK Federation — Profile Page JS
 * Handles: tab switching (Profile Information / Account Settings)
 */

document.addEventListener('DOMContentLoaded', function () {
    initTabs();
});

function initTabs() {
    const tabs = [
        { btn: document.getElementById('tabBtnInfo'), panel: document.getElementById('tabInfo') },
        { btn: document.getElementById('tabBtnSettings'), panel: document.getElementById('tabSettings') },
    ];

    tabs.forEach(function (t, idx) {
        if (!t.btn) {
            return;
        }

        t.btn.addEventListener('click', function () {
            activateTab(idx);
        });
    });

    function activateTab(activeIdx) {
        tabs.forEach(function (t, i) {
            const isActive = i === activeIdx;

            if (t.btn) {
                t.btn.classList.toggle('active', isActive);
                t.btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            }

            if (t.panel) {
                t.panel.classList.toggle('active', isActive);
            }
        });
    }

    const params = new URLSearchParams(window.location.search);
    const hashTab = (window.location.hash || '').replace('#', '').toLowerCase();
    const requestedTab = (params.get('tab') || hashTab || '').toLowerCase();

    if (requestedTab === 'settings' || requestedTab === 'account-settings') {
        activateTab(1);
    }
}
