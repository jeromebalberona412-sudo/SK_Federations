(function (window) {
    'use strict';

    let sortMenu = null;
    let sortMenuAnchor = null;
    let onSortCallback = null;
    let getCurrentDir = null;

    function closeSortMenu() {
        if (sortMenu) sortMenu.hidden = true;
        sortMenuAnchor = null;
        document.querySelectorAll('.ar-sort-btn').forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
    }

    function updateHeaderState(dir) {
        const th = document.querySelector('.ar-th-sortable[data-sort-key="fullname"]');
        if (!th) return;
        th.classList.remove('is-sorted-asc', 'is-sorted-desc');
        th.setAttribute('aria-sort', 'none');
        if (dir === 'asc') {
            th.classList.add('is-sorted-asc');
            th.setAttribute('aria-sort', 'ascending');
        } else if (dir === 'desc') {
            th.classList.add('is-sorted-desc');
            th.setAttribute('aria-sort', 'descending');
        }
    }

    function openSortMenu(anchor) {
        if (!sortMenu || !anchor) return;
        sortMenuAnchor = anchor;
        const currentDir = typeof getCurrentDir === 'function' ? getCurrentDir() : null;

        sortMenu.innerHTML = '';
        [
            { dir: 'asc', label: 'Sort ascending', hint: 'A → Z', icon: '↑' },
            { dir: 'desc', label: 'Sort descending', hint: 'Z → A', icon: '↓' },
        ].forEach((opt) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ar-sort-option' + (currentDir === opt.dir ? ' is-active' : '');
            btn.setAttribute('role', 'menuitem');
            btn.innerHTML = `<span class="ar-sort-option-icon">${opt.icon}</span><span class="ar-sort-option-text"><span class="ar-sort-option-label">${opt.label}</span><span class="ar-sort-option-hint">${opt.hint}</span></span>`;
            btn.addEventListener('click', () => {
                if (typeof onSortCallback === 'function') onSortCallback(opt.dir);
                updateHeaderState(opt.dir);
                closeSortMenu();
            });
            sortMenu.appendChild(btn);
        });

        const rect = anchor.getBoundingClientRect();
        sortMenu.hidden = false;
        sortMenu.style.top = `${rect.bottom + 4}px`;
        sortMenu.style.left = `${Math.max(8, rect.left)}px`;
        anchor.setAttribute('aria-expanded', 'true');
    }

    function init(options) {
        sortMenu = document.getElementById(options.menuId || 'arFullnameSortMenu');
        onSortCallback = options.onSort;
        getCurrentDir = options.getCurrentDir;

        document.querySelectorAll('.ar-th-sortable .ar-sort-btn').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                if (sortMenuAnchor === btn && sortMenu && !sortMenu.hidden) {
                    closeSortMenu();
                    return;
                }
                closeSortMenu();
                openSortMenu(btn);
            });
        });

        if (document.body.dataset.arSortGlobalBound === '1') return;
        document.body.dataset.arSortGlobalBound = '1';

        document.addEventListener('click', (event) => {
            if (sortMenu && !sortMenu.hidden
                && !sortMenu.contains(event.target)
                && event.target !== sortMenuAnchor
                && !sortMenuAnchor?.contains(event.target)) {
                closeSortMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeSortMenu();
        });

        window.addEventListener('scroll', () => closeSortMenu(), true);

        // Safe: never call getCurrentDir until callback is assigned and callable.
        try {
            if (typeof getCurrentDir === 'function') {
                updateHeaderState(getCurrentDir());
            }
        } catch (_error) {
            updateHeaderState(null);
        }
    }

    window.ArchiveFullnameSort = { init, closeSortMenu, updateHeaderState };
})(window);
