/**
 * Shared table pagination footer (Kabataan / KK Profiling style).
 */
(function (global) {
    function bindTablePageFooter(config) {
        const {
            prefix,
            getTotalRecords,
            getCurrentPage,
            setCurrentPage,
            getRecordsPerPage,
            setRecordsPerPage,
            onPageChange,
        } = config;

        const prevBtn = document.getElementById(`${prefix}PrevBtn`);
        const nextBtn = document.getElementById(`${prefix}NextBtn`);
        const pageInput = document.getElementById(`${prefix}PageInput`);
        const totalPagesEl = document.getElementById(`${prefix}TotalPages`);
        const infoEl = document.getElementById(`${prefix}PaginationInfo`);
        const rowsSelect = document.getElementById(`${prefix}RowsPerPageSelect`);

        function getTotalPages() {
            const total = getTotalRecords();
            const perPage = getRecordsPerPage();
            return Math.max(1, Math.ceil(total / perPage) || 1);
        }

        function updateFooter() {
            const total = getTotalRecords();
            const totalPages = getTotalPages();
            let page = getCurrentPage();

            if (page > totalPages) {
                page = totalPages;
                setCurrentPage(page);
            }

            if (pageInput) {
                pageInput.value = String(page);
                pageInput.min = '1';
                pageInput.max = String(totalPages);
            }
            if (totalPagesEl) totalPagesEl.textContent = String(totalPages);
            if (prevBtn) prevBtn.disabled = page <= 1;
            if (nextBtn) nextBtn.disabled = page >= totalPages;
            if (infoEl) infoEl.textContent = `${total} record${total === 1 ? '' : 's'}`;
        }

        function goToPage(page) {
            const totalPages = getTotalPages();
            const next = Math.min(Math.max(1, page), totalPages);
            setCurrentPage(next);
            updateFooter();
            onPageChange();
        }

        if (prevBtn) prevBtn.addEventListener('click', () => goToPage(getCurrentPage() - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => goToPage(getCurrentPage() + 1));
        if (pageInput) {
            pageInput.addEventListener('change', () => {
                const val = parseInt(pageInput.value, 10);
                if (!Number.isNaN(val)) goToPage(val);
            });
        }
        if (rowsSelect) {
            rowsSelect.addEventListener('change', () => {
                setRecordsPerPage(parseInt(rowsSelect.value, 10) || 10);
                setCurrentPage(1);
                updateFooter();
                onPageChange();
            });
        }

        return { updateFooter, goToPage, getTotalPages };
    }

    function paginateSlice(items, page, perPage) {
        const start = (page - 1) * perPage;
        return items.slice(start, start + perPage);
    }

    global.bindTablePageFooter = bindTablePageFooter;
    global.paginateSlice = paginateSlice;
})(window);
