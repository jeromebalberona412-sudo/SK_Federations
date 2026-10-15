/**
 * SK OnePortal - SK Federations Interactive Guided Tour Engine
 * Zero dependencies, pure vanilla JS + CSS spotlight highlight engine.
 *
 * First login: mandatory — Next/Finish only (no Skip / X).
 * After COMPLETED once: auto-start stops; header ? can relaunch with Skip/X allowed.
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
            title: 'Barangay ABYIP',
            description: 'Monitor barangay ABYIP submissions and check which barangays have submitted or completed required requirements.',
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
            target: 'button[data-tour="messages"]',
            icon: 'fa-comments',
            title: 'Messages',
            description: 'Access your conversations and communicate with authorized users through the SK OnePortal communication system.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'notifications',
            target: 'button[data-tour="notifications"]',
            icon: 'fa-bell',
            title: 'Notifications',
            description: 'Check important updates, alerts, and system notifications here.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'tutorial-guide',
            target: 'button[data-tour="tutorial-guide"]',
            icon: 'fa-question-circle',
            title: 'Tutorial Guide',
            description: 'Click this question mark anytime you wish to restart or review this interactive guided tour.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'account-menu',
            target: 'button[data-tour="account-menu"]',
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
            description: 'You now know where the main SK Federations features and navigation controls are located. You can relaunch this tutorial anytime using the question mark button in the header.',
            placement: 'center',
            isSidebar: false
        }
    ];

    // ── Engine State ─────────────────────────────────────────────────────────
    let currentStepIndex = 0;
    let isActive = false;
    let canSkip = false;
    let isMandatory = true;
    let finishInFlight = false;
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

    function applyTutorialPayload(tutorial) {
        if (!tutorial || typeof tutorial !== 'object') return;
        canSkip = tutorial.can_skip === true;
        isMandatory = tutorial.is_mandatory !== false && !canSkip;
        applyDismissControls();
    }

    function applyDismissControls() {
        const allowDismiss = canSkip && !isMandatory;

        if (skipBtn) {
            skipBtn.hidden = !allowDismiss;
            skipBtn.style.display = allowDismiss ? 'inline-flex' : 'none';
            skipBtn.setAttribute('aria-hidden', allowDismiss ? 'false' : 'true');
        }

        if (closeBtn) {
            closeBtn.hidden = !allowDismiss;
            closeBtn.style.display = allowDismiss ? 'inline-flex' : 'none';
            closeBtn.setAttribute('aria-hidden', allowDismiss ? 'false' : 'true');
        }

        if (rootEl) {
            rootEl.classList.toggle('sk-tour-mandatory', !allowDismiss);
        }

        if (backBtn && isActive) {
            // First-run: Next/Finish only. Replay: Back allowed.
            const showBack = allowDismiss && currentStepIndex > 0;
            backBtn.style.display = showBack ? 'inline-flex' : 'none';
        }
    }

    // ── API Communications ───────────────────────────────────────────────────
    async function apiRequest(endpoint, data = null, forcePost = false) {
        try {
            const usePost = forcePost || data !== null;
            const options = {
                method: usePost ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            };
            if (usePost) {
                options.body = JSON.stringify(data && typeof data === 'object' ? data : {});
            }

            const response = await fetch('/api/tutorial-guide/' + endpoint, options);
            let payload = null;
            try {
                payload = await response.json();
            } catch (_) {
                payload = null;
            }

            if (payload?.tutorial) {
                applyTutorialPayload(payload.tutorial);
            }

            if (!response.ok) {
                return { success: false, message: payload?.message || 'Request failed.', status: response.status };
            }

            return payload;
        } catch (err) {
            console.warn('Tutorial guide API call failed:', err);
            return null;
        }
    }

    // ── Step Resolution ──────────────────────────────────────────────────────
    function getValidSteps() {
        return TOUR_STEPS.filter(step => {
            if (!step.target) return true;
            return document.querySelector(step.target) !== null;
        });
    }

    function findNextValidIndex(fromIndex, forward = true) {
        const stepCount = TOUR_STEPS.length;
        let idx = fromIndex;

        while (true) {
            idx = forward ? idx + 1 : idx - 1;
            if (idx < 0 || idx >= stepCount) return -1;

            const step = TOUR_STEPS[idx];
            if (!step.target) return idx;

            if (document.querySelector(step.target)) return idx;
        }
    }

    function isFinalStep() {
        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return false;
        const validSteps = getValidSteps();
        const stepNumber = validSteps.findIndex(s => s.key === step.key) + 1;
        return currentStepIndex === TOUR_STEPS.length - 1 || stepNumber === validSteps.length;
    }

    // ── Position Spotlight & Card ────────────────────────────────────────────
    function updatePosition() {
        if (!isActive || !cardEl || !spotlightEl) return;

        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return;

        if (currentHighlightedEl) {
            currentHighlightedEl.classList.remove('sk-tour-highlighted-element');
            currentHighlightedEl = null;
        }

        if (!step.target) {
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
            goToNextStep();
            return;
        }

        if (step.isSidebar && isMobileViewport()) {
            if (!document.body.classList.contains('sidebar-open') && typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
                autoOpenedMobileSidebar = true;
            }
            document.body.classList.add('sk-tour-active-sidebar');
            // Remeasure after sidebar open animation so highlight is not behind the drawer.
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    window.setTimeout(function () {
                        if (!isActive || !targetEl) return;
                        const lateRect = targetEl.getBoundingClientRect();
                        const pad = 6;
                        spotlightEl.style.left = `${Math.max(0, lateRect.left - pad)}px`;
                        spotlightEl.style.top = `${Math.max(0, lateRect.top - pad)}px`;
                        spotlightEl.style.width = `${lateRect.width + (pad * 2)}px`;
                        spotlightEl.style.height = `${lateRect.height + (pad * 2)}px`;
                    }, 320);
                });
            });
        } else {
            document.body.classList.remove('sk-tour-active-sidebar');
        }

        targetEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
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

        const cardWidth = cardEl.offsetWidth || 380;
        const cardHeight = cardEl.offsetHeight || 220;
        const margin = 14;

        let left = 0;
        let top = 0;
        let placement = step.placement || 'right';

        if (isMobileViewport() && placement === 'right') {
            placement = 'bottom';
        }

        if (placement === 'right') {
            left = spotLeft + spotWidth + margin;
            top = spotTop + (spotHeight - cardHeight) / 2;

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

            if (top + cardHeight > window.innerHeight - 16) {
                top = spotTop - cardHeight - margin;
            }
        }

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
        iconEl.className = `fas ${step.icon || 'fa-question-circle'}`;

        applyDismissControls();

        if (isFinalStep()) {
            nextLabel.textContent = 'Finish';
            nextIcon.className = 'fas fa-check';
            nextBtn.classList.add('sk-tour-btn--finish');
            nextBtn.disabled = false;
        } else {
            nextLabel.textContent = 'Next';
            nextIcon.className = 'fas fa-arrow-right';
            nextBtn.classList.remove('sk-tour-btn--finish');
            nextBtn.disabled = false;
        }

        window.requestAnimationFrame(() => {
            updatePosition();
        });

        apiRequest('progress', { step: currentStepIndex + 1 });
    }

    function setFinishLoading(loading) {
        if (!nextBtn || !nextLabel) return;
        nextBtn.disabled = !!loading;
        nextBtn.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (loading) {
            nextLabel.textContent = 'Saving...';
            nextIcon.className = 'fas fa-spinner fa-spin';
        } else if (isFinalStep()) {
            nextLabel.textContent = 'Finish';
            nextIcon.className = 'fas fa-check';
        }
    }

    // ── Navigation Handlers ──────────────────────────────────────────────────
    function goToNextStep() {
        if (finishInFlight) return;

        if (isFinalStep()) {
            finishTutorial();
            return;
        }

        const nextIdx = findNextValidIndex(currentStepIndex, true);
        if (nextIdx === -1) {
            finishTutorial();
            return;
        }

        if (TOUR_STEPS[currentStepIndex].isSidebar && !TOUR_STEPS[nextIdx].isSidebar) {
            document.body.classList.remove('sk-tour-active-sidebar');
            if (autoOpenedMobileSidebar && document.body.classList.contains('sidebar-open') && typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
                autoOpenedMobileSidebar = false;
            }
        }

        currentStepIndex = nextIdx;
        renderStep();
    }

    function goToPrevStep() {
        if (!canSkip || isMandatory || finishInFlight) return;

        const prevIdx = findNextValidIndex(currentStepIndex, false);
        if (prevIdx === -1) return;

        currentStepIndex = prevIdx;
        renderStep();
    }

    function showSkipConfirmation() {
        if (!canSkip || isMandatory) return;
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
        if (!canSkip || isMandatory) {
            hideSkipConfirmation();
            return;
        }

        hideSkipConfirmation();
        // Close overlay immediately; persist skip in the background.
        endTour();
        apiRequest('skip', {}, true);
    }

    async function finishTutorial() {
        if (finishInFlight) return;
        finishInFlight = true;
        setFinishLoading(true);

        try {
            const res = await apiRequest('complete', {}, true);
            if (!res || res.success !== true) {
                setFinishLoading(false);
                finishInFlight = false;
                if (nextLabel) {
                    nextLabel.textContent = 'Retry Finish';
                }
                if (nextIcon) {
                    nextIcon.className = 'fas fa-check';
                }
                return;
            }

            canSkip = true;
            isMandatory = false;
            applyDismissControls();
            endTour();
        } finally {
            finishInFlight = false;
        }
    }

    function endTour() {
        isActive = false;
        finishInFlight = false;

        if (currentHighlightedEl) {
            currentHighlightedEl.classList.remove('sk-tour-highlighted-element');
            currentHighlightedEl = null;
        }

        if (autoOpenedMobileSidebar && document.body.classList.contains('sidebar-open') && typeof window.toggleSidebar === 'function') {
            window.toggleSidebar();
            autoOpenedMobileSidebar = false;
        }

        if (rootEl) {
            rootEl.style.display = 'none';
            rootEl.setAttribute('aria-hidden', 'true');
            rootEl.classList.remove('sk-tour-mandatory');
        }

        hideSkipConfirmation();
        document.removeEventListener('keydown', handleKeydown, true);
    }

    function isTypingField(target) {
        if (!target || typeof target.closest !== 'function') return false;
        return !!target.closest('input, textarea, select, [contenteditable="true"]');
    }

    function handleKeydown(e) {
        if (!isActive || e.repeat || isTypingField(e.target)) return;

        if (e.key === 'ArrowRight' || e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            goToNextStep();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            e.stopPropagation();
            if (canSkip && !isMandatory) {
                goToPrevStep();
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            if (canSkip && !isMandatory) {
                showSkipConfirmation();
            }
        }
    }

    // ── Launching Tour ───────────────────────────────────────────────────────
    function startTour(fromScratch = false, initialStep = 1) {
        if (!cacheDom()) return;
        if (isActive) return;

        // Open the modal immediately; sync progress with the API in the background.
        if (fromScratch) {
            currentStepIndex = 0;
        } else {
            currentStepIndex = Math.max(0, initialStep - 1);
            if (currentStepIndex >= TOUR_STEPS.length) {
                currentStepIndex = 0;
            }
        }

        isActive = true;
        rootEl.style.display = 'block';
        rootEl.setAttribute('aria-hidden', 'false');
        applyDismissControls();
        document.addEventListener('keydown', handleKeydown, true);
        renderStep();

        const syncPromise = fromScratch
            ? apiRequest('reset', {}, true)
            : apiRequest('start', {}, true);

        syncPromise.then((res) => {
            if (!isActive || !res?.tutorial) return;
            applyTutorialPayload(res.tutorial);
            applyDismissControls();
        }).catch(() => {
            // Tour UI already opened; ignore background sync failures.
        });
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

        applyTutorialPayload(res.tutorial);

        if (res.tutorial.should_auto_start) {
            const stepToStart = res.tutorial.current_step || 1;
            // Next frame so layout/sidebar targets are ready without a noticeable delay.
            window.requestAnimationFrame(() => {
                startTour(false, stepToStart);
            });
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
