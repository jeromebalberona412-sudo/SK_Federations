(function (window) {
    'use strict';

    let activeHandler = null;
    let activeMenu = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderMenu(recordId, options) {
        const id = escapeHtml(recordId);
        let items = '';

        if (options.view) {
            items += `<button type="button" class="ar-row-actions-item" data-ar-action="view" data-id="${id}" role="menuitem"><i class="fas fa-eye" aria-hidden="true"></i><span>View</span></button>`;
        }
        if (options.restore) {
            items += `<button type="button" class="ar-row-actions-item" data-ar-action="restore" data-id="${id}" role="menuitem"><i class="fas fa-rotate-left" aria-hidden="true"></i><span>Restore</span></button>`;
        }
        if (options.deletePermanent) {
            items += `<button type="button" class="ar-row-actions-item is-danger" data-ar-action="delete" data-id="${id}" role="menuitem"><i class="fas fa-trash" aria-hidden="true"></i><span>Delete Permanently</span></button>`;
        }

        return `
            <div class="ar-row-actions-menu">
                <button type="button" class="ar-row-actions-trigger" aria-label="More actions" aria-haspopup="menu" aria-expanded="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle></svg>
                </button>
                <div class="ar-row-actions-dropdown" role="menu" hidden>${items}</div>
            </div>`;
    }

    function restoreDropdown(dropdown) {
        if (!dropdown) return;
        const home = dropdown._arHomeParent;
        if (home && dropdown.parentElement !== home) {
            home.appendChild(dropdown);
        }
        dropdown._arHomeParent = null;
        dropdown.classList.remove('is-floating');
        dropdown.style.position = '';
        dropdown.style.top = '';
        dropdown.style.right = '';
        dropdown.style.left = '';
        dropdown.style.bottom = '';
        dropdown.style.zIndex = '';
        dropdown.style.display = '';
    }

    function closeAll(exceptMenu) {
        document.querySelectorAll('.ar-row-actions-menu.is-open').forEach((menu) => {
            if (exceptMenu && menu === exceptMenu) return;
            menu.classList.remove('is-open');
            const trigger = menu.querySelector('.ar-row-actions-trigger');
            const dropdown = menu._arDropdown
                || menu.querySelector('.ar-row-actions-dropdown')
                || document.querySelector(`.ar-row-actions-dropdown[data-ar-menu-id="${menu.dataset.arMenuId || ''}"]`);
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
            if (dropdown) {
                dropdown.hidden = true;
                restoreDropdown(dropdown);
                delete dropdown.dataset.arMenuId;
            }
            delete menu._arDropdown;
        });

        // Clean up any orphaned floating dropdowns left after a table re-render.
        document.querySelectorAll('.ar-row-actions-dropdown.is-floating').forEach((dropdown) => {
            const home = dropdown._arHomeParent;
            if (exceptMenu && (home === exceptMenu || dropdown.dataset.arMenuId === exceptMenu.dataset.arMenuId)) {
                return;
            }
            dropdown.hidden = true;
            restoreDropdown(dropdown);
            delete dropdown.dataset.arMenuId;
        });

        if (!exceptMenu || exceptMenu !== activeMenu) {
            activeHandler = null;
            activeMenu = null;
        }
    }

    function positionDropdown(menu) {
        const trigger = menu.querySelector('.ar-row-actions-trigger');
        let dropdown = menu.querySelector('.ar-row-actions-dropdown');
        if (!trigger || !dropdown) {
            dropdown = menu._arDropdown;
        }
        if (!trigger || !dropdown) return;

        if (!menu.dataset.arMenuId) {
            menu.dataset.arMenuId = `ar-menu-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        }

        // Portal to body so overflow:auto/hidden ancestors cannot clip the menu.
        if (dropdown.parentElement !== document.body) {
            dropdown._arHomeParent = menu;
            document.body.appendChild(dropdown);
        }

        menu._arDropdown = dropdown;
        dropdown.dataset.arMenuId = menu.dataset.arMenuId;
        dropdown.hidden = false;
        dropdown.classList.add('is-floating');
        dropdown.style.position = 'fixed';
        dropdown.style.zIndex = '1400';
        dropdown.style.display = 'flex';

        const rect = trigger.getBoundingClientRect();
        const width = dropdown.offsetWidth || 168;
        const height = dropdown.offsetHeight || 140;
        let top = rect.bottom + 6;
        if (top + height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - height - 6);
        }
        let right = Math.max(8, window.innerWidth - rect.right);
        if (right + width > window.innerWidth - 8) {
            right = 8;
        }
        dropdown.style.top = `${top}px`;
        dropdown.style.right = `${right}px`;
        dropdown.style.left = 'auto';
        dropdown.style.bottom = 'auto';
    }

    function runAction(action, id, actionBtn) {
        const handler = activeHandler;
        closeAll();
        if (typeof handler === 'function' && action) {
            handler(action, id, actionBtn);
        }
    }

    function bind(container, handler) {
        if (!container) return;

        container._arActionsHandler = handler;

        if (container.dataset.arActionsBound === '1') {
            return;
        }
        container.dataset.arActionsBound = '1';

        container.addEventListener('click', (event) => {
            const trigger = event.target.closest('.ar-row-actions-trigger');
            if (trigger && container.contains(trigger)) {
                event.preventDefault();
                event.stopPropagation();
                const menu = trigger.closest('.ar-row-actions-menu');
                if (!menu) return;

                const isOpen = menu.classList.contains('is-open');
                closeAll();
                if (!isOpen) {
                    menu.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                    activeHandler = container._arActionsHandler || handler;
                    activeMenu = menu;
                    positionDropdown(menu);
                }
                return;
            }

            const actionBtn = event.target.closest('[data-ar-action]');
            if (!actionBtn || !container.contains(actionBtn)) return;

            event.preventDefault();
            event.stopPropagation();
            activeHandler = container._arActionsHandler || handler;
            runAction(
                actionBtn.getAttribute('data-ar-action'),
                actionBtn.getAttribute('data-id'),
                actionBtn
            );
        });
    }

    function initGlobalClose() {
        if (document.body.dataset.arActionsGlobalBound === '1') return;
        document.body.dataset.arActionsGlobalBound = '1';

        document.addEventListener('click', (event) => {
            const floatingItem = event.target.closest('.ar-row-actions-dropdown.is-floating [data-ar-action]');
            if (floatingItem) {
                event.preventDefault();
                event.stopPropagation();
                runAction(
                    floatingItem.getAttribute('data-ar-action'),
                    floatingItem.getAttribute('data-id'),
                    floatingItem
                );
                return;
            }

            if (event.target.closest('.ar-row-actions-menu')
                || event.target.closest('.ar-row-actions-dropdown.is-floating')) {
                return;
            }

            closeAll();
        });

        window.addEventListener('scroll', () => closeAll(), true);
        window.addEventListener('resize', () => closeAll());
    }

    function fullNameFieldHtml(label, value) {
        return `
            <div class="account-profile-field account-profile-field--fullname">
                <label>${escapeHtml(label)} <span class="table-col-hint">LN, FN, MN, Suffix</span></label>
                <p>${escapeHtml(value || '-')}</p>
            </div>`;
    }

    function bindViewModalControls(prefix, options) {
        const opts = options || {};
        const modal = document.getElementById(`${prefix}ViewModal`);
        const box = document.getElementById(`${prefix}ViewModalBox`);
        const closeBtn = document.getElementById(`${prefix}ViewClose`);
        const maximizeBtn = document.getElementById(`${prefix}ViewMaximizeBtn`);
        const restoreBtn = document.getElementById(`${prefix}ViewRestoreBtn`);

        const close = () => {
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove(`${prefix}-maximized`);
            }
            if (box) box.classList.remove(`${prefix}-maximized`);
            if (maximizeBtn) maximizeBtn.hidden = false;
            if (restoreBtn) restoreBtn.hidden = true;
            if (opts.bodyClass) document.body.classList.remove(opts.bodyClass);
        };

        closeBtn?.addEventListener('click', close);
        modal?.addEventListener('click', (e) => { if (e.target === modal) close(); });

        maximizeBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            modal?.classList.add(`${prefix}-maximized`);
            box?.classList.add(`${prefix}-maximized`);
            if (maximizeBtn) maximizeBtn.hidden = true;
            if (restoreBtn) restoreBtn.hidden = false;
        });

        restoreBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            modal?.classList.remove(`${prefix}-maximized`);
            box?.classList.remove(`${prefix}-maximized`);
            if (maximizeBtn) maximizeBtn.hidden = false;
            if (restoreBtn) restoreBtn.hidden = true;
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && modal.style.display === 'flex') close();
        });
    }

    window.ArchiveRowActions = {
        renderMenu,
        bind,
        closeAll,
        initGlobalClose,
        fullNameFieldHtml,
        bindViewModalControls,
        escapeHtml,
    };
})(window);
