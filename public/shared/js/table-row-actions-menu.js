(function () {
    const ICON_ELLIPSIS = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="12" r="2"></circle>
            <circle cx="12" cy="12" r="2"></circle>
            <circle cx="19" cy="12" r="2"></circle>
        </svg>
    `;

    let suppressScrollCloseUntil = 0;

    function resetRowActionsDropdown(menu) {
        const dropdown = menu?.querySelector('.row-actions-dropdown')
            || document.querySelector(`.row-actions-dropdown[data-owner-menu="${menu?.dataset?.menuId || ''}"]`);
        if (!dropdown) {
            return;
        }

        // Return portal dropdown back into its menu
        if (dropdown.dataset.portaled === '1' && menu && dropdown.parentElement !== menu) {
            menu.appendChild(dropdown);
            delete dropdown.dataset.portaled;
            delete dropdown.dataset.ownerMenu;
        }

        dropdown.classList.remove('is-floating', 'row-actions-dropdown-up');
        dropdown.style.position = '';
        dropdown.style.top = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.bottom = '';
        dropdown.style.zIndex = '';
        dropdown.style.width = '';
        dropdown.style.minWidth = '';
        dropdown.style.visibility = '';
        dropdown.style.opacity = '';
        dropdown.style.pointerEvents = '';
        dropdown.style.display = '';
        dropdown.style.transform = '';
    }

    function ensureMenuId(menu) {
        if (!menu.dataset.menuId) {
            menu.dataset.menuId = `ram-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        }
        return menu.dataset.menuId;
    }

    function positionRowActionsDropdown(menu) {
        const trigger = menu.querySelector('.row-actions-trigger');
        let dropdown = menu.querySelector('.row-actions-dropdown');
        if (!trigger || !dropdown) {
            return;
        }

        const menuId = ensureMenuId(menu);

        // Portal to body so table overflow / stacking never hides or covers the menu
        if (dropdown.parentElement !== document.body) {
            dropdown.dataset.portaled = '1';
            dropdown.dataset.ownerMenu = menuId;
            document.body.appendChild(dropdown);
        }

        dropdown.classList.add('is-floating');
        dropdown.style.position = 'fixed';
        dropdown.style.zIndex = '10050';
        dropdown.style.display = 'flex';
        dropdown.style.flexDirection = 'column';
        dropdown.style.visibility = 'hidden';
        dropdown.style.opacity = '0';
        dropdown.style.pointerEvents = 'none';
        dropdown.style.transform = 'none';
        dropdown.style.minWidth = '168px';
        dropdown.style.width = '168px';

        const rect = trigger.getBoundingClientRect();
        const gap = 4;
        const dropdownHeight = Math.max(dropdown.offsetHeight || 0, 120);
        const dropdownWidth = 168;

        // Prefer below the trigger; flip up if needed. Align so menu covers the ⋯ button area.
        let top = rect.bottom + gap;
        dropdown.classList.remove('row-actions-dropdown-up');

        if (top + dropdownHeight > window.innerHeight - 8) {
            const upTop = rect.top - dropdownHeight - gap;
            if (upTop > 8) {
                top = upTop;
                dropdown.classList.add('row-actions-dropdown-up');
            } else {
                top = Math.max(8, window.innerHeight - dropdownHeight - 8);
            }
        }

        let left = rect.right - dropdownWidth;
        left = Math.max(8, Math.min(left, window.innerWidth - dropdownWidth - 8));

        dropdown.style.top = `${Math.max(8, top)}px`;
        dropdown.style.left = `${left}px`;
        dropdown.style.right = 'auto';
        dropdown.style.bottom = 'auto';
        dropdown.style.visibility = 'visible';
        dropdown.style.opacity = '1';
        dropdown.style.pointerEvents = 'auto';
    }

    function closeAllRowActionMenus(exceptMenu = null) {
        document.querySelectorAll('.row-actions-menu.is-open').forEach((menu) => {
            if (menu === exceptMenu) {
                return;
            }

            menu.classList.remove('is-open');
            const trigger = menu.querySelector('.row-actions-trigger');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
            resetRowActionsDropdown(menu);
        });

        // Close any orphaned portaled dropdowns
        document.querySelectorAll('.row-actions-dropdown[data-portaled="1"]').forEach((dropdown) => {
            const ownerId = dropdown.dataset.ownerMenu;
            const owner = ownerId
                ? document.querySelector(`.row-actions-menu[data-menu-id="${ownerId}"]`)
                : null;
            if (!owner || !owner.classList.contains('is-open') || owner === exceptMenu) {
                if (owner) {
                    resetRowActionsDropdown(owner);
                } else {
                    dropdown.remove();
                }
            }
        });
    }

    function toggleRowActionsMenu(trigger) {
        const menu = trigger.closest('.row-actions-menu');
        if (!menu) {
            return;
        }

        const willOpen = !menu.classList.contains('is-open');
        closeAllRowActionMenus();

        if (willOpen) {
            menu.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            positionRowActionsDropdown(menu);
            suppressScrollCloseUntil = Date.now() + 250;
        }
    }

    function bindRowActionsTable(tbody) {
        if (!tbody || tbody.dataset.rowActionsBound === '1') {
            return;
        }

        tbody.dataset.rowActionsBound = '1';
        tbody.addEventListener('click', (event) => {
            const trigger = event.target.closest('.row-actions-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                toggleRowActionsMenu(trigger);
            }
        });
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('.row-actions-trigger')) {
            return;
        }

        if (event.target.closest('.row-actions-item')) {
            closeAllRowActionMenus();
            return;
        }

        if (!event.target.closest('.row-actions-menu') && !event.target.closest('.row-actions-dropdown')) {
            closeAllRowActionMenus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllRowActionMenus();
        }
    });

    window.addEventListener('resize', () => {
        const openMenu = document.querySelector('.row-actions-menu.is-open');
        if (openMenu) {
            positionRowActionsDropdown(openMenu);
        }
    });

    window.addEventListener('scroll', () => {
        if (Date.now() < suppressScrollCloseUntil) {
            return;
        }

        closeAllRowActionMenus();
    }, true);

    window.ROW_ACTIONS_ELLIPSIS = ICON_ELLIPSIS;
    window.bindRowActionsTable = bindRowActionsTable;
    window.closeAllRowActionMenus = closeAllRowActionMenus;
})();
