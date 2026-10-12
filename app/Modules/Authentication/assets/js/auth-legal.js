/**
 * Login Terms & Privacy document modals (SK Federations)
 */
(function () {
    'use strict';

    const ACK_STORAGE_PREFIX = 'sk_oneportal_legal_ack_';
    const SCROLL_THRESHOLD = 12;

    function getAckKey(type) {
        return `${ACK_STORAGE_PREFIX}federation_${type}`;
    }

    function isScrolledToBottom(element) {
        if (!element) return false;
        return element.scrollHeight - element.scrollTop - element.clientHeight <= SCROLL_THRESHOLD;
    }

    function getScrollPercent(element) {
        if (!element) return 100;
        const scrollable = element.scrollHeight - element.clientHeight;
        if (scrollable <= 0) return 100;
        return Math.min(100, Math.max(0, Math.round((element.scrollTop / scrollable) * 100)));
    }

    function getScrollMessage(percent) {
        if (percent >= 100) {
            return "You've reached the end of the document. Please check the checkbox below to acknowledge.";
        }
        if (percent <= 0) {
            return 'Please scroll down to read the entire document before acknowledging.';
        }
        if (percent < 50) {
            return 'Keep scrolling. Please read the full document before acknowledging.';
        }
        if (percent < 80) {
            return "You're halfway there. Keep scrolling to finish reading.";
        }
        return 'Almost done. Scroll a little more to reach the end of the document.';
    }

    function bindModalAckStorage(ack) {
        const type = ack.getAttribute('data-legal-ack');
        if (!type) return;

        ack.addEventListener('change', () => {
            if (ack.checked) {
                sessionStorage.setItem(getAckKey(type), '1');
            } else {
                sessionStorage.removeItem(getAckKey(type));
            }
        });
    }

    function syncModalOkButton(modal) {
        const ack = modal.querySelector('[data-legal-ack]');
        const okBtn = modal.querySelector('.auth-legal-modal-btn');
        if (!ack || !okBtn) return;

        okBtn.disabled = !ack.checked;
    }

    function syncModalHint(modal, body) {
        const hint = modal.querySelector('[data-legal-hint]');
        const notice = modal.querySelector('[data-legal-scroll-notice]');
        const noticeText = modal.querySelector('[data-legal-scroll-text]');
        const noticePercent = modal.querySelector('[data-legal-scroll-percent]');
        const ack = modal.querySelector('[data-legal-ack]');
        const atBottom = isScrolledToBottom(body);
        const percent = getScrollPercent(body);

        if (notice) {
            notice.hidden = false;
            notice.classList.toggle('is-complete', percent >= 100);
        }

        if (noticeText) {
            noticeText.textContent = getScrollMessage(percent);
        }

        if (noticePercent) {
            noticePercent.hidden = false;
            noticePercent.textContent = `${percent}%`;
        }

        if (!hint) return;

        hint.classList.toggle('is-ready', atBottom && Boolean(ack?.checked));

        if (atBottom && ack?.checked) {
            hint.textContent = 'You can now acknowledge and continue.';
            return;
        }

        hint.textContent = 'Please check the checkbox to acknowledge and continue.';
    }

    function syncModalScrollGate(modal) {
        const body = modal.querySelector('.auth-legal-modal-body');
        const ack = modal.querySelector('[data-legal-ack]');
        const ackLabel = modal.querySelector('.auth-legal-modal-ack');
        if (!body || !ack) return;

        const atBottom = isScrolledToBottom(body);

        ack.disabled = !atBottom;
        ackLabel?.classList.toggle('auth-legal-modal-ack--locked', !atBottom);

        if (!atBottom && ack.checked) {
            ack.checked = false;
        }

        syncModalHint(modal, body);
        syncModalOkButton(modal);
    }

    function resetModalScrollGate(modal) {
        const body = modal.querySelector('.auth-legal-modal-body');
        const ack = modal.querySelector('[data-legal-ack]');
        const ackLabel = modal.querySelector('.auth-legal-modal-ack');
        const okBtn = modal.querySelector('.auth-legal-modal-btn');
        const hint = modal.querySelector('[data-legal-hint]');
        const notice = modal.querySelector('[data-legal-scroll-notice]');

        if (body) {
            body.scrollTop = 0;
        }

        if (notice) {
            notice.hidden = false;
            notice.classList.remove('is-complete');
        }

        if (hint) {
            hint.classList.remove('is-ready');
        }

        if (ack) {
            ack.checked = false;
            ack.disabled = true;
        }

        ackLabel?.classList.add('auth-legal-modal-ack--locked');
        modal._legalAckRevealed = false;

        if (okBtn) {
            okBtn.disabled = true;
        }

        requestAnimationFrame(() => {
            syncModalScrollGate(modal);
        });
    }

    function initLegalModalScrollGates() {
        document.querySelectorAll('.auth-legal-modal').forEach((modal) => {
            const body = modal.querySelector('.auth-legal-modal-body');
            const ack = modal.querySelector('[data-legal-ack]');
            const ackLabel = modal.querySelector('.auth-legal-modal-ack');

            if (!body || !ack) return;

            bindModalAckStorage(ack);

            body.addEventListener('scroll', () => {
                const wasRevealed = modal._legalAckRevealed === true;
                syncModalScrollGate(modal);

                if (!wasRevealed && isScrolledToBottom(body)) {
                    modal._legalAckRevealed = true;
                    ack.scrollIntoView({ block: 'end', behavior: 'smooth' });
                }
            }, { passive: true });

            if (typeof ResizeObserver === 'function') {
                new ResizeObserver(() => syncModalScrollGate(modal)).observe(body);
            } else {
                window.addEventListener('resize', () => syncModalScrollGate(modal));
            }

            ack.addEventListener('change', () => {
                syncModalHint(modal, body);
                syncModalOkButton(modal);
            });

            ackLabel?.addEventListener('click', (e) => {
                if (ack.disabled) {
                    e.preventDefault();
                }
            });

            modal._resetLegalScrollGate = () => resetModalScrollGate(modal);
        });
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (typeof modal._resetLegalScrollGate === 'function') {
            modal._resetLegalScrollGate();
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.auth-legal-modal:not([hidden])')) {
            document.body.style.overflow = '';
        }
    }

    function bindModals() {
        document.querySelectorAll('[data-open-legal-modal]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openModal(btn.getAttribute('data-open-legal-modal'));
            });
        });

        // Legal documents require acknowledgement: only the OK button closes them,
        // and only after the checkbox has been ticked. Backdrop clicks and Escape
        // deliberately do nothing.
        document.querySelectorAll('[data-close-legal-modal]').forEach((el) => {
            el.addEventListener('click', () => {
                if (el.disabled) return;
                closeModal(el.getAttribute('data-close-legal-modal'));
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initLegalModalScrollGates();
        bindModals();
    });
})();
