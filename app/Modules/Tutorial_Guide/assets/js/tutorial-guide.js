/**
 * SK OnePortal - SK Federations Interactive Guided Tour Engine
 * Zero dependencies, pure vanilla JS + CSS spotlight highlight engine.
 */

(function () {
    'use strict';

    // ── Static Step Configurations ──────────────────────────────────────────
    const TOUR_STEPS = [
        {
            key: 'dashboard',
            target: '[data-tour="dashboard"]',
            icon: 'fa-home',
            title: 'Dashboard',
            description: 'This is your main overview page. View important statistics, activities, notifications, and quick information about the SK Federation.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'profile',
            target: '[data-tour="profile"]',
            icon: 'fa-user',
            title: 'Profile',
            description: 'Manage and view your SK Federation profile and account information.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'calendar',
            target: '[data-tour="calendar"]',
            icon: 'fa-calendar-alt',
            title: 'Calendar',
            description: 'View and manage important schedules, meetings, activities, and events.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'community-feed',
            target: '[data-tour="community-feed"]',
            icon: 'fa-rss',
            title: 'SK Community Feed',
            description: 'View and manage community posts, announcements, updates, and interactions across the SK OnePortal community.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'barangay-monitoring',
            target: '[data-tour="barangay-monitoring"]',
            icon: 'fa-map-marker-alt',
            title: 'Barangay Compliance',
            description: 'Monitor barangay SK compliance and check which barangays have submitted or completed required requirements.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'kabataan-monitoring',
            target: '[data-tour="kabataan-monitoring"]',
            icon: 'fa-users',
            title: 'KK Profiling Records',
            description: 'View and monitor KK Profiling records submitted by Kabataan users across the barangays.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'barangay-logos',
            target: '[data-tour="barangay-logos"]',
            icon: 'fa-image',
            title: 'Barangay Logos',
            description: 'Manage the official barangay logos used throughout the SK OnePortal system.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'audit-logs',
            target: '[data-tour="audit-logs"]',
            icon: 'fa-clipboard-list',
            title: 'Audit Logs',
            description: 'Review important system activities and actions performed by users for monitoring and accountability.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'manage-accounts',
            target: '[data-tour="manage-accounts"]',
            icon: 'fa-user-cog',
            title: 'Manage Accounts',
            description: 'Manage SK-related accounts and account access according to your Federation permissions.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'archive-management',
            target: '[data-tour="archive-management"]',
            icon: 'fa-box-archive',
            title: 'Archive Management',
            description: 'Access archived records and manage information that is no longer part of the active records.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'sidebar-toggle',
            target: '[data-tour="sidebar-toggle"]',
            icon: 'fa-bars',
            title: 'Sidebar Toggle',
            description: 'Expand or collapse the sidebar navigation anytime to maximize your working space on screen.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'messages',
            target: '[data-tour="messages"]',
            icon: 'fa-comments',
            title: 'Messages',
            description: 'Access your conversations and communicate with authorized users through the SK OnePortal communication system.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'notifications',
            target: '[data-tour="notifications"]',
            icon: 'fa-bell',
            title: 'Notifications',
            description: 'Check important updates, alerts, and system notifications here.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'tutorial-guide',
            target: '[data-tour="tutorial-guide"]',
            icon: 'fa-compass',
            title: 'Tutorial Guide',
            description: 'Click this compass button anytime you wish to restart or review this interactive guided tour.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'account-menu',
            target: '[data-tour="account-menu"]',
            icon: 'fa-user-circle',
            title: 'Account Menu',
            description: 'Access your profile settings, switch accounts, and find secure logout controls.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'finish',
            target: null,
            icon: 'fa-check-circle',
            title: "You're all set!",
            description: 'You now know where the main SK Federations features and navigation controls are located. You can relaunch this tutorial anytime using the compass button in the header.',
            placement: 'center',
            isSidebar: false
        }
    ];

    // ── Engine State ─────────────────────────────────────────────────────────
    let currentStepIndex = 0;
    let isActive = false;
    let autoOpenedMobileSidebar = false;
    let currentHighlightedEl = null;
    let windowResizeDebounce = null;

    // ── DOM Element Cache ────────────────────────────────────────────────────
    let rootEl = null;
    let backdropEl = null;
    let spotlightEl = null;
    let cardEl = null;
    let badgeEl = null;
    let iconEl = null;
    let titleEl = null;
    let descEl = null;
    let skipBtn = null;
    let backBtn = null;
    let nextBtn = null;
    let nextLabel = null;
    let nextIcon = null;
    let closeBtn = null;
    let confirmOverlay = null;
    let confirmCancelBtn = null;
    let confirmSkipBtn = null;

    function getCsrfToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        return tokenMeta ? tokenMeta.getAttribute('content') : '';
    }

    function isMobileViewport() {
        return window.innerWidth <= 1024;
    }

    function cacheDom() {
        rootEl = document.getElementById('skTourRoot');
        if (!rootEl) return false;

        backdropEl = document.getElementById('skTourBackdrop');
        spotlightEl = document.getElementById('skTourSpotlight');
        cardEl = document.getElementById('skTourCard');
        badgeEl = document.getElementById('skTourBadge');
        iconEl = document.getElementById('skTourStepIcon');
        titleEl = document.getElementById('skTourTitle');
        descEl = document.getElementById('skTourDescription');
        skipBtn = document.getElementById('skTourSkipBtn');
        backBtn = document.getElementById('skTourBackBtn');
        nextBtn = document.getElementById('skTourNextBtn');
        nextLabel = document.getElementById('skTourNextLabel');
        nextIcon = document.getElementById('skTourNextIcon');
        closeBtn = document.getElementById('skTourCloseBtn');
        confirmOverlay = document.getElementById('skTourConfirmOverlay');
        confirmCancelBtn = document.getElementById('skTourConfirmCancelBtn');
        confirmSkipBtn = document.getElementById('skTourConfirmSkipBtn');

        return true;
    }

    // ── API Communications ───────────────────────────────────────────────────
    async function apiRequest(endpoint, data = null) {
        try {
            const options = {
                method: data ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            if (data) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch('/api/tutorial-guide/' + endpoint, options);
            if (!response.ok) return null;
            return await response.json();
        } catch (err) {
            console.warn('Tutorial guide API call failed:', err);
            return null;
        }
    }

    // ── Step Resolution ──────────────────────────────────────────────────────
    function getValidSteps() {
        return TOUR_STEPS.filter(step => {
            if (!step.target) return true; // Center final step is always valid
            const el = document.querySelector(step.target);
            return el !== null;
        });
    }

    function findNextValidIndex(fromIndex, forward = true) {
        const stepCount = TOUR_STEPS.length;
        let idx = fromIndex;

        while (true) {
            idx = forward ? idx + 1 : idx - 1;
            if (idx < 0 || idx >= stepCount) return -1;

            const step = TOUR_STEPS[idx];
            if (!step.target) return idx; // Center step

            const el = document.querySelector(step.target);
            if (el) return idx;
        }
    }

    // ── Position Spotlight & Card ────────────────────────────────────────────
    function updatePosition() {
        if (!isActive || !cardEl || !spotlightEl) return;

        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return;

        // Clear previous highlight
        if (currentHighlightedEl) {
            currentHighlightedEl.classList.remove('sk-tour-highlighted-element');
            currentHighlightedEl = null;
        }

        if (!step.target) {
            // Center position (Final Step)
            spotlightEl.style.opacity = '0';
            spotlightEl.style.width = '0px';
            spotlightEl.style.height = '0px';
            spotlightEl.style.top = '-9999px';
            spotlightEl.style.left = '-9999px';

            const cardWidth = cardEl.offsetWidth || 380;
            const cardHeight = cardEl.offsetHeight || 220;
            const left = Math.max(16, (window.innerWidth - cardWidth) / 2);
            const top = Math.max(16, (window.innerHeight - cardHeight) / 2);

            cardEl.style.left = `${left}px`;
            cardEl.style.top = `${top}px`;
            return;
        }

        const targetEl = document.querySelector(step.target);
        if (!targetEl) {
            // Skip target if missing
            goToNextStep();
            return;
        }

        // Handle mobile sidebar auto-opening
        if (step.isSidebar && isMobileViewport()) {
            if (!document.body.classList.contains('sidebar-open') && typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
                autoOpenedMobileSidebar = true;
            }
        }

        // Scroll element into view smoothly
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });

        // Highlight element
        targetEl.classList.add('sk-tour-highlighted-element');
        currentHighlightedEl = targetEl;

        const rect = targetEl.getBoundingClientRect();
        const padding = 6;
        const spotLeft = Math.max(0, rect.left - padding);
        const spotTop = Math.max(0, rect.top - padding);
        const spotWidth = rect.width + (padding * 2);
        const spotHeight = rect.height + (padding * 2);

        spotlightEl.style.opacity = '1';
        spotlightEl.style.left = `${spotLeft}px`;
        spotlightEl.style.top = `${spotTop}px`;
        spotlightEl.style.width = `${spotWidth}px`;
        spotlightEl.style.height = `${spotHeight}px`;

        // Determine Card Placement
        const cardWidth = cardEl.offsetWidth || 380;
        const cardHeight = cardEl.offsetHeight || 220;
        const margin = 14;

        let left = 0;
        let top = 0;
        let placement = step.placement || 'right';

        // Responsive fallback: on small screens, sidebar steps flip to bottom or top
        if (isMobileViewport() && placement === 'right') {
            placement = 'bottom';
        }

        if (placement === 'right') {
            left = spotLeft + spotWidth + margin;
            top = spotTop + (spotHeight - cardHeight) / 2;

            // Check viewport overflow on the right
            if (left + cardWidth > window.innerWidth - 16) {
                left = spotLeft - cardWidth - margin;
                if (left < 16) {
                    placement = 'bottom';
                }
            }
        }

        if (placement === 'bottom') {
            top = spotTop + spotHeight + margin;
            left = spotLeft + (spotWidth - cardWidth) / 2;

            // Check viewport overflow on the bottom
            if (top + cardHeight > window.innerHeight - 16) {
                top = spotTop - cardHeight - margin;
            }
        }

        // Clamp inside screen bounds
        left = Math.max(16, Math.min(window.innerWidth - cardWidth - 16, left));
        top = Math.max(16, Math.min(window.innerHeight - cardHeight - 16, top));

        cardEl.style.left = `${left}px`;
        cardEl.style.top = `${top}px`;
    }

    // ── Render Step Content ──────────────────────────────────────────────────
    function renderStep() {
        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return;

        const validSteps = getValidSteps();
        const stepNumber = validSteps.findIndex(s => s.key === step.key) + 1;
        const totalSteps = validSteps.length;

        badgeEl.textContent = `Step ${stepNumber} of ${totalSteps}`;
        titleEl.textContent = step.title;
        descEl.textContent = step.description;

        // Icon
        iconEl.className = `fas ${step.icon || 'fa-compass'}`;

        // Back button visibility
        if (currentStepIndex === 0) {
            backBtn.style.display = 'none';
        } else {
            backBtn.style.display = 'inline-flex';
        }

        // Next vs Finish button
        const isFinal = (currentStepIndex === TOUR_STEPS.length - 1) || (stepNumber === totalSteps);
        if (isFinal) {
            nextLabel.textContent = 'Finish';
            nextIcon.className = 'fas fa-check';
            nextBtn.classList.add('sk-tour-btn--finish');
        } else {
            nextLabel.textContent = 'Next';
            nextIcon.className = 'fas fa-arrow-right';
            nextBtn.classList.remove('sk-tour-btn--finish');
        }

        // Reposition
        window.requestAnimationFrame(() => {
            updatePosition();
        });

        // Save server progress in background
        apiRequest('progress', { step: currentStepIndex + 1 });
    }

    // ── Navigation Handlers ──────────────────────────────────────────────────
    function goToNextStep() {
        const nextIdx = findNextValidIndex(currentStepIndex, true);
        if (nextIdx === -1) {
            finishTutorial();
            return;
        }

        // If leaving sidebar and moving to header/finish, restore mobile sidebar
        if (TOUR_STEPS[currentStepIndex].isSidebar && !TOUR_STEPS[nextIdx].isSidebar) {
            if (autoOpenedMobileSidebar && document.body.classList.contains('sidebar-open') && typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
                autoOpenedMobileSidebar = false;
            }
        }

        currentStepIndex = nextIdx;
        renderStep();
    }

    function goToPrevStep() {
        const prevIdx = findNextValidIndex(currentStepIndex, false);
        if (prevIdx === -1) return;

        currentStepIndex = prevIdx;
        renderStep();
    }

    function showSkipConfirmation() {
        if (confirmOverlay) {
            confirmOverlay.style.display = 'flex';
        }
    }

    function hideSkipConfirmation() {
        if (confirmOverlay) {
            confirmOverlay.style.display = 'none';
        }
    }

    async function skipTutorial() {
        hideSkipConfirmation();
        await apiRequest('skip');
        endTour();
    }

    async function finishTutorial() {
        await apiRequest('complete');
        endTour();
    }

    function endTour() {
        isActive = false;

        // Cleanup highlight
        if (currentHighlightedEl) {
            currentHighlightedEl.classList.remove('sk-tour-highlighted-element');
            currentHighlightedEl = null;
        }

        // Restore mobile sidebar if we opened it
        if (autoOpenedMobileSidebar && document.body.classList.contains('sidebar-open') && typeof window.toggleSidebar === 'function') {
            window.toggleSidebar();
            autoOpenedMobileSidebar = false;
        }

        // Hide root
        if (rootEl) {
            rootEl.style.display = 'none';
            rootEl.setAttribute('aria-hidden', 'true');
        }

        // Remove keyboard navigation
        document.removeEventListener('keydown', handleKeydown);
    }

    function handleKeydown(e) {
        if (!isActive) return;

        if (e.key === 'ArrowRight' || e.key === 'Enter') {
            e.preventDefault();
            goToNextStep();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            goToPrevStep();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            showSkipConfirmation();
        }
    }

    // ── Launching Tour ───────────────────────────────────────────────────────
    async function startTour(fromScratch = false, initialStep = 1) {
        if (!cacheDom()) return;

        if (fromScratch) {
            await apiRequest('reset');
            currentStepIndex = 0;
        } else {
            await apiRequest('start');
            currentStepIndex = Math.max(0, initialStep - 1);
            if (currentStepIndex >= TOUR_STEPS.length) {
                currentStepIndex = 0;
            }
        }

        isActive = true;
        rootEl.style.display = 'block';
        rootEl.setAttribute('aria-hidden', 'false');

        document.addEventListener('keydown', handleKeydown);

        renderStep();
    }

    // ── Setup Event Listeners ────────────────────────────────────────────────
    function bindUiEvents() {
        if (!cacheDom()) return;

        nextBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            goToNextStep();
        });

        backBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            goToPrevStep();
        });

        skipBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            showSkipConfirmation();
        });

        closeBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            showSkipConfirmation();
        });

        confirmCancelBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            hideSkipConfirmation();
        });

        confirmSkipBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            skipTutorial();
        });

        // Window resize repositioning
        window.addEventListener('resize', () => {
            if (!isActive) return;
            window.clearTimeout(windowResizeDebounce);
            windowResizeDebounce = window.setTimeout(updatePosition, 100);
        });
    }

    // ── Auto-Start Detection on First Login ──────────────────────────────────
    async function checkFirstLoginStatus() {
        const res = await apiRequest('status');
        if (!res || !res.success || !res.tutorial) return;

        if (res.tutorial.should_auto_start) {
            const stepToStart = res.tutorial.current_step || 1;
            // Small timeout to allow DOM layout to stabilize
            window.setTimeout(() => {
                startTour(false, stepToStart);
            }, 600);
        }
    }

    // ── Global Interface ─────────────────────────────────────────────────────
    window.startSkFedTutorial = function (forceRestart = false) {
        startTour(forceRestart, 1);
    };

    // ── Boot ─────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            bindUiEvents();
            checkFirstLoginStatus();
        });
    } else {
        bindUiEvents();
        checkFirstLoginStatus();
    }
})();
