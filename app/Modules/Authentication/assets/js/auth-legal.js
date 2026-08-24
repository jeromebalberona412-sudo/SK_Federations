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

        syncModalOkButton(modal);
    }

    function resetModalScrollGate(modal) {
        const body = modal.querySelector('.auth-legal-modal-body');
        const ack = modal.querySelector('[data-legal-ack]');
        const ackLabel = modal.querySelector('.auth-legal-modal-ack');
        const okBtn = modal.querySelector('.auth-legal-modal-btn');

        if (body) {
            body.scrollTop = 0;
        }

        if (ack) {
            ack.checked = false;
            ack.disabled = true;
        }

        ackLabel?.classList.add('auth-legal-modal-ack--locked');

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
                syncModalScrollGate(modal);
            }, { passive: true });

            ack.addEventListener('change', () => {
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

        document.querySelectorAll('[data-close-legal-modal]').forEach((el) => {
            el.addEventListener('click', () => {
                if (el.disabled) return;
                closeModal(el.getAttribute('data-close-legal-modal'));
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.auth-legal-modal:not([hidden])').forEach((modal) => {
                closeModal(modal.id);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initLegalModalScrollGates();
        bindModals();
    });
})();
