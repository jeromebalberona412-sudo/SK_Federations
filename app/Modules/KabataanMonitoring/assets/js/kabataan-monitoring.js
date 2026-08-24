(function () {
    const config = window.kmConfig || {};
    const records = [];
    const selectedIds = new Set();
    let pendingDeleteRecord = null;

    const state = { search: '', status: 'all', barangay: 'all', year: 'all', voter: 'all', ageGroup: 'all', purok: 'all', sortKey: '', sortDir: '' };
    const modalCache = {};
    const modalInflight = {};

    function youthAgeGroupKey(r) {
        var label = String(r.youthAgeGroup || '').toLowerCase();
        if (label.indexOf('15-17') !== -1 || label.indexOf('child youth') !== -1) return 'child';
        if (label.indexOf('18-24') !== -1 || label.indexOf('core youth') !== -1) return 'core';
        if (label.indexOf('25-30') !== -1 || label.indexOf('young adult') !== -1) return 'young';
        var n = parseInt(r.age, 10);
        if (n >= 15 && n <= 17) return 'child';
        if (n >= 18 && n <= 24) return 'core';
        if (n >= 25 && n <= 30) return 'young';
        return '';
    }

    function getFiltered() {
        var q = state.search.trim().toLowerCase();
        return records.filter(function(r) {
            var matchStatus = state.status === 'all' || r.status === state.status;
            var matchBrgy   = state.barangay === 'all' || r.barangay === state.barangay;
            var matchYear = state.year === 'all' || String(r.submittedYear || '') === String(state.year);
            var voterValue = String(r.registeredVoter || '').trim().toLowerCase();
            var matchVoter = state.voter === 'all'
                || (state.voter === 'Yes' && voterValue === 'yes')
                || (state.voter === 'No' && voterValue === 'no');
            var matchAgeGroup = state.ageGroup === 'all' || youthAgeGroupKey(r) === state.ageGroup;
            var purokValue = String(r.purokZone || '').trim();
            var matchPurok = state.purok === 'all' || purokValue === state.purok;
            var hay = (r.name + ' ' + r.barangay + ' ' + r.focus + ' ' + r.youthClassification + ' ' + (r.respondentNumber || '') + ' ' + (r.purokZone || '')).toLowerCase();
            var matchSearch = !q || hay.includes(q);
            return matchStatus && matchBrgy && matchSearch && matchYear && matchVoter && matchAgeGroup && matchPurok;
        });
    }

    function updateSummary() {
        // Stats cards removed from index page.
    }

    function populateBrgyFilter() {
        // Barangays are now hardcoded in the HTML, so this function is no longer needed
        // but kept for compatibility
    }

    function populateYearFilter() {}

    function populateBarangayYearFilter() {
        var yearFilter = document.getElementById('km-brgy-year-filter');
        if (!yearFilter) return;

        var brgy = window.kmBarangay || '';
        var years = Array.from(new Set(records.filter(function (r) {
            return r.barangay === brgy && r.submittedYear;
        }).map(function (r) {
            return String(r.submittedYear);
        }))).sort(function (a, b) {
            return parseInt(b, 10) - parseInt(a, 10);
        });

        setYearSelectOptions(yearFilter, years);
    }

    function populatePurokFilter() {
        var selectEl = document.getElementById('km-brgy-purok-filter');
        if (!selectEl) return;

        var current = selectEl.value || 'all';
        var puroks = [];

        // Use DB-configured zones (even when there are no records yet).
        if (Array.isArray(window.kmPurokZones) && window.kmPurokZones.length) {
            puroks = window.kmPurokZones
                .map(function (z) { return String(z || '').trim(); })
                .filter(function (z) { return z !== '' && z !== '—'; });
        } else {
            // Fallback: zones derived from existing KK records.
            var brgy = window.kmBarangay || '';
            puroks = Array.from(new Set(records.filter(function (r) {
                return (!brgy || r.barangay === brgy) && String(r.purokZone || '').trim() && String(r.purokZone).trim() !== '—';
            }).map(function (r) {
                return String(r.purokZone).trim();
            })));
        }

        puroks = puroks.sort(function (a, b) {
            return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
        });

        var html = '<option value="all">Purok/Zone</option>';
        puroks.forEach(function (purok) {
            html += '<option value="' + escapeHtml(purok) + '">' + escapeHtml(purok) + '</option>';
        });
        selectEl.innerHTML = html;
        if (puroks.indexOf(current) !== -1) {
            selectEl.value = current;
            state.purok = current;
        } else {
            selectEl.value = 'all';
            state.purok = 'all';
        }
    }

    function setYearSelectOptions(selectEl, years) {
        var current = selectEl.value || 'all';
        var html = '<option value="all">All Years</option>';
        years.forEach(function (year) {
            html += '<option value="' + escapeHtml(String(year)) + '">' + escapeHtml(String(year)) + '</option>';
        });
        selectEl.innerHTML = html;
        if (Array.from(selectEl.options).some(function (opt) { return opt.value === current; })) {
            selectEl.value = current;
        } else {
            selectEl.value = 'all';
            state.year = 'all';
        }
    }

    var statusLabel = { active: 'Active', moderate: 'Moderate', inactive: 'Inactive' };

    function renderBrgyCards() {
        var container = document.getElementById('km-brgy-cards');
        var empty = document.getElementById('km-empty');
        var countEl = document.getElementById('km-result-count');
        if (!container) return;

        var filtered = getFiltered();
        if (countEl) countEl.textContent = filtered.length + ' record' + (filtered.length !== 1 ? 's' : '');

        // Define all 26 barangays
        var allBrgys = [
            'Alipit', 'Bagumbayan', 'Bubukal', 'Calios', 'Duhat', 'Gatid', 'Jasaan', 
            'Labuin', 'Malinao', 'Oogong', 'Pagsawitan', 'Palasan', 'Patimbao',
            'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Poblacion V',
            'San Jose', 'San Juan', 'San Pablo Norte', 'San Pablo Sur',
            'Santisima Cruz', 'Santo Angel Central', 'Santo Angel Norte', 'Santo Angel Sur'
        ];

        var query = state.search.trim().toLowerCase();
        var brgysToDisplay = allBrgys.filter(function (brgy) {
            return !query || brgy.toLowerCase().includes(query);
        });

        if (empty) empty.hidden = brgysToDisplay.length > 0;

        var today = new Date();
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        container.innerHTML = '<div class="km-brgy-grid">' + brgysToDisplay.map(function(brgy) {
            var members = records.filter(function(r){ return r.barangay === brgy; });
            var logoUrl = '';
            members.some(function(member) {
                if (member.barangayLogoUrl) {
                    logoUrl = member.barangayLogoUrl;
                    return true;
                }
                return false;
            });
            var total    = members.length;
            var active   = members.filter(function(r){ return r.status === 'active'; }).length;
            var moderate = members.filter(function(r){ return r.status === 'moderate'; }).length;
            var inactive = members.filter(function(r){ return r.status === 'inactive'; }).length;
            var rate     = total > 0 ? Math.round(((active + moderate) / total) * 100) : 0;
            var lastUpdate = months[today.getMonth()] + ' ' + today.getDate() + ', ' + today.getFullYear();

            var statusClass = total === 0 ? 'inactive' : (rate >= 70 ? 'active' : (rate >= 40 ? 'moderate' : 'inactive'));
            var statusText  = total === 0 ? 'No Data' : (rate >= 70 ? 'Active' : (rate >= 40 ? 'Moderate' : 'Inactive'));

            return '<div class="km-brgy-summary-card">' +
                '<div class="km-bsc-header">' +
                    '<div class="km-bsc-brand">' +
                        '<div class="km-bsc-logo">' +
                            (logoUrl
                                ? '<img src="' + logoUrl + '" alt="' + brgy + ' logo" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">' +
                                  '<span class="km-bsc-logo-fallback" hidden>' + brgy.charAt(0).toUpperCase() + '</span>'
                                : '<span class="km-bsc-logo-fallback">' + brgy.charAt(0).toUpperCase() + '</span>') +
                        '</div>' +
                        '<h3 class="km-bsc-name">' + brgy + '</h3>' +
                    '</div>' +
                    '<span class="km-badge ' + statusClass + '">' + statusText + '</span>' +
                '</div>' +
                '<div class="km-bsc-footer">' +
                    '<span class="km-bsc-update"><i class="fas fa-clock"></i> Last update: ' + lastUpdate + '</span>' +
                    '<button class="km-bsc-view-btn" onclick="openBrgyModal(\'' + brgy.replace(/'/g, "\\'") + '\')">' +
                        'View full details <i class="fas fa-arrow-right"></i>' +
                    '</button>' +
                '</div>' +
            '</div>';
        }).join('') + '</div>';
    }

    // ── Barangay detail modal ──
    window.openBrgyModal = function(brgy) {
        var detailBase = (document.querySelector('main.km-main') || {}).dataset?.detailBase || '/kabataan-monitoring';
        var encodedBrgy = encodeURIComponent(brgy);
        window.location.href = detailBase + '/barangay/' + encodedBrgy;
    };

    function initIndex() {
        loadRecords().then(function () {
            updateSummary();
            populateBrgyFilter();
            renderBrgyCards();
        });

        var searchInput = document.getElementById('km-search');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                state.search = e.target.value || '';
                renderBrgyCards();
            });
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    renderBrgyCards();
                }
            });
        }

        var indexSearchBtn = document.querySelector('.km-search-group--index .km-search-btn');
        if (indexSearchBtn) {
            indexSearchBtn.addEventListener('click', function () {
                renderBrgyCards();
            });
        }

    }

    // ── Detail page ──
    var statusLabelDetail = { active: 'Active', moderate: 'Moderate', inactive: 'Inactive', high: 'High', low: 'Low' };

    function metricCard(label, value) {
        return '<article class="km-metric-card"><small>' + label + '</small><strong>' + value + '</strong></article>';
    }

    function renderDetail() {
        var root = document.querySelector('main.km-main');
        if (!root) return;
        var detailBase = root.dataset.detailBase || '/kabataan-monitoring';
        var slug = root.dataset.kabataanSlug || '';
        var profile = records.find(function(r){ return r.slug === slug; });
        var hero = document.getElementById('km-profile-hero');
        var detailGrid = document.getElementById('km-detail-grid');
        var notFound = document.getElementById('km-not-found');
        if (!hero || !detailGrid || !notFound) return;

        if (!profile) { detailGrid.hidden = true; notFound.hidden = false; return; }
        notFound.hidden = true; detailGrid.hidden = false;

        hero.innerHTML = '<div class="km-profile-head">' +
            '<a class="km-back-link" href="' + detailBase + '"><i class="fas fa-arrow-left"></i> Back to list</a>' +
            '<h1>' + profile.name + '</h1>' +
            '<p>' + profile.barangay + ' | ' + profile.age + ' years old | Focus: ' + profile.focus + '</p>' +
            '<div class="km-profile-strip">' +
                '<span class="km-profile-pill">Status: ' + (statusLabelDetail[profile.status] || profile.status) + '</span>' +
                '<span class="km-profile-pill">Attendance: ' + profile.attendance + '</span>' +
                '<span class="km-profile-pill">Last check-in: ' + profile.lastCheckIn + '</span>' +
            '</div></div>';

        var metricGrid = document.getElementById('km-metric-grid');
        var programList = document.getElementById('km-program-list');
        var recoList = document.getElementById('km-reco-list');
        var timeline = document.getElementById('km-timeline');
        if (!metricGrid || !programList || !recoList || !timeline) return;

        metricGrid.innerHTML = [
            metricCard('Engagement Score', profile.score),
            metricCard('Attendance', profile.attendance),
            metricCard('Status', statusLabelDetail[profile.status] || profile.status),
            metricCard('Focus Area', profile.focus)
        ].join('');

        programList.innerHTML = profile.programs.map(function(p){
            return '<article class="km-list-item"><h4>' + p.title + '</h4><p>' + p.summary + '</p></article>';
        }).join('');

        recoList.innerHTML = profile.recommendations.map(function(r){ return '<li>' + r + '</li>'; }).join('');

        timeline.innerHTML = profile.timeline.map(function(t){
            return '<article class="km-time-item"><h4>' + t.title + '</h4><p>' + t.note + '</p></article>';
        }).join('');
    }

    async function loadRecords() {
        if (!config.dataUrl) {
            return;
        }

        try {
            const response = await fetch(config.dataUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load kabataan records');
            }

            const payload = await response.json();
            records.length = 0;
            (payload.data || []).forEach(function (item) {
                records.push(item);
            });
            return payload.years || [];
        } catch (error) {
            console.error(error);
            return [];
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.kmPageMode === 'show') {
            loadRecords().then(renderDetail);
            return;
        }
        if (window.kmPageMode === 'barangay-detail') {
            loadRecords().then(function () {
                populateBarangayYearFilter();
                populatePurokFilter();
                renderBarangayDetail();
                setupBarangayDetailFilters();
            });
            return;
        }
        initIndex();
    });

    function setupBarangayDetailFilters() {
        var searchInput = document.getElementById('km-brgy-search');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                state.search = e.target.value || '';
                resetBarangayPagination();
                renderBarangayDetail();
            });
            // Also trigger on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performBarangaySearch();
                }
            });
        }

        var yearFilter = document.getElementById('km-brgy-year-filter');
        if (yearFilter) {
            yearFilter.addEventListener('change', function(e) {
                state.year = e.target.value || 'all';
                selectedIds.clear();
                resetBarangayPagination();
                renderBarangayDetail();
            });
        }

        var voterFilter = document.getElementById('km-brgy-voter-filter');
        if (voterFilter) {
            voterFilter.addEventListener('change', function(e) {
                state.voter = e.target.value || 'all';
                selectedIds.clear();
                resetBarangayPagination();
                renderBarangayDetail();
            });
        }

        var ageGroupFilter = document.getElementById('km-brgy-age-group-filter');
        if (ageGroupFilter) {
            ageGroupFilter.addEventListener('change', function(e) {
                state.ageGroup = e.target.value || 'all';
                selectedIds.clear();
                resetBarangayPagination();
                renderBarangayDetail();
            });
        }

        var purokFilter = document.getElementById('km-brgy-purok-filter');
        if (purokFilter) {
            purokFilter.addEventListener('change', function(e) {
                state.purok = e.target.value || 'all';
                selectedIds.clear();
                resetBarangayPagination();
                renderBarangayDetail();
            });
        }

        var rowsSelect = document.getElementById('km-rows-per-page');
        if (rowsSelect) {
            rowsSelect.addEventListener('change', function(e) {
                var perPage = parseInt(e.target.value, 10) || 10;
                window.kmPaginationState = window.kmPaginationState || {};
                window.kmPaginationState.itemsPerPage = perPage;
                resetBarangayPagination();
                renderBarangayDetail();
            });
        }

        var pageInput = document.getElementById('km-page-input');
        if (pageInput) {
            pageInput.addEventListener('change', function(e) {
                var statePag = window.kmPaginationState || {};
                var page = parseInt(e.target.value, 10) || 1;
                var totalPages = statePag.totalPages || 1;
                page = Math.max(1, Math.min(totalPages, page));
                statePag.currentPage = page;
                renderPaginatedTable();
            });
        }

        var prevBtn = document.getElementById('km-prev-btn');
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                window.previousPage();
            });
        }

        var nextBtn = document.getElementById('km-next-btn');
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                window.nextPage();
            });
        }

        var selectAll = document.getElementById('km-select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                toggleSelectAllFiltered(selectAll.checked);
            });
        }

        var tbody = document.getElementById('km-table-tbody');
        if (tbody) {
            tbody.addEventListener('change', function(e) {
                var checkbox = e.target.closest('.km-row-checkbox');
                if (!checkbox) return;
                var id = String(checkbox.getAttribute('data-id') || '');
                if (!id) return;
                if (checkbox.checked) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
                syncSelectAllCheckbox();
                updateBatchPrintButton();
            });
        }

        var batchBtn = document.getElementById('km-batch-print-btn');
        if (batchBtn) {
            batchBtn.addEventListener('click', openBatchPrint);
        }

        setupRowActionMenus();
        setupDeleteConfirmModal();

        updateBatchPrintButton();
    }

    function filteredBarangayRecords() {
        var brgy = window.kmBarangay || '';
        return getFiltered().filter(function(r){ return r.barangay === brgy; });
    }

    function toggleSelectAllFiltered(checked) {
        filteredBarangayRecords().forEach(function(r) {
            var id = String(r.id || r.slug || '');
            if (!id) return;
            if (checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        renderPaginatedTable();
        updateBatchPrintButton();
    }

    function syncSelectAllCheckbox() {
        var selectAll = document.getElementById('km-select-all');
        if (!selectAll) return;
        var ids = filteredBarangayRecords().map(function(r) {
            return String(r.id || r.slug || '');
        }).filter(Boolean);
        var selectedCount = ids.filter(function(id) { return selectedIds.has(id); }).length;
        selectAll.checked = ids.length > 0 && selectedCount === ids.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < ids.length;
    }

    function updateBatchPrintButton() {
        var batchBtn = document.getElementById('km-batch-print-btn');
        if (!batchBtn) return;
        batchBtn.disabled = selectedIds.size === 0;
        batchBtn.title = selectedIds.size
            ? 'Print ' + selectedIds.size + ' selected record' + (selectedIds.size === 1 ? '' : 's')
            : 'Select records to batch print';
    }

    function openBatchPrint() {
        var ids = Array.from(selectedIds);
        if (!ids.length) return;

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = (window.kmConfig && window.kmConfig.batchPrintUrl) || '/kabataan-monitoring/batch-print';
        form.target = '_blank';
        form.style.display = 'none';

        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = (window.kmConfig && window.kmConfig.csrfToken) || '';
        form.appendChild(token);

        ids.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = String(id);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    function closeAllKmActionMenus(exceptMenu) {
        document.querySelectorAll('.km-row-actions-menu.is-open').forEach(function (menu) {
            if (exceptMenu && menu === exceptMenu) return;
            menu.classList.remove('is-open');
            var trigger = menu.querySelector('.km-row-actions-trigger');
            var dropdown = menu.querySelector('.km-row-actions-dropdown');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
            if (dropdown) {
                dropdown.hidden = true;
                dropdown.classList.remove('is-floating');
                dropdown.style.position = '';
                dropdown.style.top = '';
                dropdown.style.right = '';
                dropdown.style.left = '';
            }
        });
    }

    function positionKmActionDropdown(menu) {
        var trigger = menu.querySelector('.km-row-actions-trigger');
        var dropdown = menu.querySelector('.km-row-actions-dropdown');
        if (!trigger || !dropdown) return;

        dropdown.hidden = false;
        dropdown.classList.add('is-floating');
        dropdown.style.position = 'fixed';
        dropdown.style.zIndex = '1400';

        var rect = trigger.getBoundingClientRect();
        var width = dropdown.offsetWidth || 168;
        var height = dropdown.offsetHeight || 140;
        var top = rect.bottom + 6;
        if (top + height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - height - 6);
        }
        var right = Math.max(8, window.innerWidth - rect.right);
        if (right + width > window.innerWidth - 8) {
            right = 8;
        }
        dropdown.style.top = top + 'px';
        dropdown.style.right = right + 'px';
        dropdown.style.left = 'auto';
    }

    function setupRowActionMenus() {
        var tbody = document.getElementById('km-table-tbody');
        if (!tbody || tbody.dataset.kmActionsBound === '1') return;
        tbody.dataset.kmActionsBound = '1';

        tbody.addEventListener('click', function (event) {
            var trigger = event.target.closest('.km-row-actions-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                var menu = trigger.closest('.km-row-actions-menu');
                var isOpen = menu.classList.contains('is-open');
                closeAllKmActionMenus();
                if (!isOpen) {
                    menu.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                    positionKmActionDropdown(menu);
                    var prefetchBtn = menu.querySelector('[data-km-action="view"]');
                    var prefetchId = prefetchBtn && (prefetchBtn.getAttribute('data-id') || prefetchBtn.getAttribute('data-slug'));
                    if (prefetchId) window.kmFetchModalPayload(prefetchId);
                }
                return;
            }

            var actionBtn = event.target.closest('[data-km-action]');
            if (!actionBtn) return;

            event.preventDefault();
            var action = actionBtn.getAttribute('data-km-action');
            var slug = actionBtn.getAttribute('data-slug') || '';
            closeAllKmActionMenus();

            if (action === 'view') {
                window.openKKPModal(slug);
                return;
            }

            if (action === 'print') {
                var printId = actionBtn.getAttribute('data-id') || slug;
                if (typeof window.printKKPById === 'function') {
                    window.printKKPById(printId);
                }
                return;
            }

            if (action === 'edit') {
                var editId = actionBtn.getAttribute('data-id') || slug;
                if (typeof window.openKKPEditModal === 'function') {
                    window.openKKPEditModal(editId);
                }
                return;
            }

            if (action === 'delete') {
                openKmDeleteModal({
                    id: actionBtn.getAttribute('data-id') || '',
                    name: actionBtn.getAttribute('data-name') || 'this record',
                    slug: slug,
                });
            }
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.km-row-actions-menu')) {
                closeAllKmActionMenus();
            }
        });

        window.addEventListener('scroll', function () {
            closeAllKmActionMenus();
        }, true);
    }

    function syncDeleteConfirmState() {
        var input = document.getElementById('kmDeleteConfirmInput');
        var hint = document.getElementById('kmDeleteConfirmHint');
        var button = document.getElementById('kmDeleteConfirmBtn');
        if (!input || !button) return;
        var matched = input.value.trim().toLowerCase() === 'confirm';
        button.disabled = !matched;
        if (hint) hint.hidden = matched || input.value.trim() === '';
    }

    function openKmDeleteModal(record) {
        pendingDeleteRecord = record;
        var modal = document.getElementById('kmDeleteModal');
        var nameEl = document.getElementById('kmDeleteName');
        var input = document.getElementById('kmDeleteConfirmInput');
        if (nameEl) nameEl.textContent = record.name || 'this record';
        if (input) input.value = '';
        syncDeleteConfirmState();
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        input?.focus();
    }

    function closeKmDeleteModal() {
        var modal = document.getElementById('kmDeleteModal');
        if (modal) modal.classList.remove('show');
        document.body.style.overflow = '';
        pendingDeleteRecord = null;
        var input = document.getElementById('kmDeleteConfirmInput');
        if (input) input.value = '';
        syncDeleteConfirmState();
    }

    async function confirmKmDelete() {
        if (!pendingDeleteRecord || !pendingDeleteRecord.id) return;
        if (document.getElementById('kmDeleteConfirmInput')?.value.trim().toLowerCase() !== 'confirm') return;

        var button = document.getElementById('kmDeleteConfirmBtn');
        var urlTemplate = (window.kmConfig && window.kmConfig.destroyUrl) || '';
        var url = urlTemplate.replace('__ID__', encodeURIComponent(pendingDeleteRecord.id));
        var deletedId = String(pendingDeleteRecord.id);

        if (button) {
            button.disabled = true;
            button.textContent = 'Deleting...';
        }

        try {
            var response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (window.kmConfig && window.kmConfig.csrfToken) || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            var payload = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                throw new Error(payload.message || 'Failed to delete record.');
            }

            for (var i = records.length - 1; i >= 0; i--) {
                if (String(records[i].id || records[i].slug || '') === deletedId) {
                    records.splice(i, 1);
                }
            }
            selectedIds.delete(deletedId);
            closeKmDeleteModal();
            if (typeof window.kmInvalidateModalCache === 'function') {
                window.kmInvalidateModalCache(deletedId);
            }
            populateBarangayYearFilter();
            populatePurokFilter();
            renderBarangayDetail();
        } catch (error) {
            alert(error.message || 'Failed to delete record.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Delete Record';
            }
        }
    }

    function setupDeleteConfirmModal() {
        var input = document.getElementById('kmDeleteConfirmInput');
        input?.addEventListener('input', syncDeleteConfirmState);
        input?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirmKmDelete();
            }
        });
        document.getElementById('kmDeleteConfirmBtn')?.addEventListener('click', function () {
            confirmKmDelete();
        });
        document.querySelectorAll('[data-km-delete-close]').forEach(function (btn) {
            btn.addEventListener('click', closeKmDeleteModal);
        });
    }

    function resetBarangayPagination() {
        window.kmPaginationState = window.kmPaginationState || {};
        window.kmPaginationState.currentPage = 1;
    }

    window.performBarangaySearch = function() {
        var searchInput = document.getElementById('km-brgy-search');
        if (searchInput) {
            state.search = searchInput.value || '';
            resetBarangayPagination();
            renderBarangayDetail();
        }
    };

    // ── Barangay Detail Page ──
    function renderBarangayDetail() {
        var brgy = window.kmBarangay || '';
        var tbody = document.getElementById('km-table-tbody');
        var empty = document.getElementById('km-empty');
        if (!tbody) return;

        var filtered = getFiltered().filter(function(r){ return r.barangay === brgy; });
        filtered = applyKmSort(filtered);

        window.kmPaginationState = window.kmPaginationState || {};
        if (!window.kmPaginationState.itemsPerPage) {
            var rowsSelect = document.getElementById('km-rows-per-page');
            window.kmPaginationState.itemsPerPage = parseInt(rowsSelect?.value || '10', 10) || 10;
        }
        if (!window.kmPaginationState.currentPage) {
            window.kmPaginationState.currentPage = 1;
        }

        window.kmPaginationState.allItems = filtered;
        window.kmPaginationState.totalPages = Math.max(1, Math.ceil(filtered.length / window.kmPaginationState.itemsPerPage) || 1);

        if (window.kmPaginationState.currentPage > window.kmPaginationState.totalPages) {
            window.kmPaginationState.currentPage = window.kmPaginationState.totalPages;
        }

        if (!filtered.length) {
            tbody.innerHTML = '<tr class="km-empty-row"><td colspan="7">No profiles match your current filters.</td></tr>';
            if (empty) empty.hidden = true;
            selectedIds.clear();
            syncSelectAllCheckbox();
            updateBatchPrintButton();
            updatePagination([], 0);
            return;
        }
        if (empty) empty.hidden = true;

        renderPaginatedTable();
    }

    function applyKmSort(items) {
        if (!state.sortKey || !state.sortDir) return items;
        var copy = items.slice();
        copy.sort(function (a, b) {
            var valA = '';
            var valB = '';
            if (state.sortKey === 'respondent') {
                valA = String(a.respondentNumber || '');
                valB = String(b.respondentNumber || '');
            } else {
                valA = formatFullName(a);
                valB = formatFullName(b);
            }
            var cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' });
            return state.sortDir === 'asc' ? cmp : -cmp;
        });
        return copy;
    }

    window.kmApplySort = function (key, dir) {
        state.sortKey = key || '';
        state.sortDir = dir || '';
        resetBarangayPagination();
        renderBarangayDetail();
    };

    window.kmGetExportRows = function (startDate, endDate) {
        var allItems = (window.kmPaginationState && window.kmPaginationState.allItems) || [];
        var source = allItems;
        if (selectedIds.size > 0) {
            source = allItems.filter(function (r) {
                return selectedIds.has(String(r.id || r.slug || ''));
            });
        }

        var start = startDate || '';
        var end = endDate || '';
        if (start || end) {
            source = source.filter(function (r) {
                var dateValue = recordExportDate(r);
                if (!dateValue) return false;
                if (start && dateValue < start) return false;
                if (end && dateValue > end) return false;
                return true;
            });
        }

        return source.map(function (r) {
            return {
                region: dash(r.region) || 'IV-A',
                province: dash(r.province) || 'Laguna',
                city: dash(r.city) || 'Santa Cruz',
                barangay: dash(r.barangay),
                fullName: formatFullName(r),
                age: dash(r.age),
                birthday: formatExportBirthday(r.birthday),
                sex: dash(r.sex),
                civilStatus: dash(r.civilStatus),
                youthClassification: dash(r.youthClassification),
                youthAgeGroup: formatYouthAgeGroup(r.youthAgeGroup, r.age),
                contactNumber: dash(r.contactNumber),
                homeAddress: dash(r.purokZone),
                education: dash(r.education),
                workStatus: dash(r.workStatus),
                registeredVoter: dash(r.registeredVoter),
                votedLastElection: dash(r.votedLastElection),
                kkAssembly: dash(r.kkAssembly),
                kkTimes: dash(r.kkTimes),
            };
        });
    };

    function recordExportDate(r) {
        var submitted = String(r.submittedDate || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(submitted)) return submitted;

        var iso = String(r.submittedAt || '').match(/^(\d{4}-\d{2}-\d{2})/);
        if (iso) return iso[1];

        var birthday = String(r.birthday || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(birthday)) return birthday;
        var slash = birthday.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (slash) {
            return slash[3] + '-' + String(slash[1]).padStart(2, '0') + '-' + String(slash[2]).padStart(2, '0');
        }
        return '';
    }

    function dash(value) {
        var text = String(value ?? '').trim();
        return (!text || text === '—') ? '' : text;
    }

    function formatExportBirthday(value) {
        var text = dash(value);
        if (!text) return '';
        var iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
        if (iso) return iso[2] + '/' + iso[3] + '/' + iso[1];
        var slash = /^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/.exec(text);
        if (slash) {
            var year = slash[3].length === 2 ? '20' + slash[3] : slash[3];
            return String(slash[1]).padStart(2, '0') + '/' + String(slash[2]).padStart(2, '0') + '/' + year;
        }
        return text;
    }

    function formatYouthAgeGroup(label, age) {
        var text = dash(label).toLowerCase();
        if (text.indexOf('15-17') !== -1 || text.indexOf('child youth') !== -1) return '15-17';
        if (text.indexOf('18-24') !== -1 || text.indexOf('core youth') !== -1) return '18-24';
        if (text.indexOf('25-30') !== -1 || text.indexOf('young adult') !== -1) return '25-30';
        var n = parseInt(age, 10);
        if (n >= 15 && n <= 17) return '15-17';
        if (n >= 18 && n <= 24) return '18-24';
        if (n >= 25 && n <= 30) return '25-30';
        return dash(label);
    }

    window.kmReloadRecords = function () {
        return loadRecords().then(function () {
            populateBarangayYearFilter();
            populatePurokFilter();
            renderBarangayDetail();
        });
    };

    function formatFullName(r) {
        var suffixRaw = r.suffix || '';
        var suffix = (suffixRaw && String(suffixRaw).toLowerCase() !== 'none') ? ',' + suffixRaw : '';
        var parts = [r.firstName, r.middleName].filter(Boolean);
        var firstMiddle = parts.length ? parts.join(',') : '';
        var last = r.lastName || '';

        if (last && firstMiddle) {
            return last + ',' + firstMiddle + suffix;
        }
        if (last) {
            return last + suffix;
        }
        if (firstMiddle) {
            return firstMiddle + suffix;
        }

        return r.name || '—';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderPaginatedTable() {
        var state = window.kmPaginationState || {};
        var tbody = document.getElementById('km-table-tbody');
        var detailBase = (document.querySelector('main.km-main') || {}).dataset?.detailBase || '/kabataan-monitoring';
        if (!tbody || !state.allItems) return;

        var start = (state.currentPage - 1) * state.itemsPerPage;
        var end = start + state.itemsPerPage;
        var pageItems = state.allItems.slice(start, end);

        var rows = pageItems.map(function(r) {
            var fullName = formatFullName(r);
            var recordId = String(r.id || r.slug || '');
            var checked = selectedIds.has(recordId) ? ' checked' : '';
            return '<tr>' +
                '<td class="km-col-check"><input type="checkbox" class="km-row-checkbox" data-id="' + escapeHtml(recordId) + '" aria-label="Select row"' + checked + '></td>' +
                '<td class="km-respondent-cell">' + escapeHtml(r.respondentNumber || '—') + '</td>' +
                '<td class="km-fullname-cell"><span class="km-fullname">' + escapeHtml(fullName) + '</span></td>' +
                '<td>' + escapeHtml(String(r.age ?? '—')) + '</td>' +
                '<td>' + escapeHtml(r.purokZone || '—') + '</td>' +
                '<td>' + escapeHtml(r.registeredVoter || '—') + '</td>' +
                '<td class="col-actions">' +
                    '<div class="km-row-actions-menu">' +
                        '<button type="button" class="km-row-actions-trigger" aria-label="More actions" aria-haspopup="true" aria-expanded="false">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle></svg>' +
                        '</button>' +
                        '<div class="km-row-actions-dropdown" role="menu" hidden>' +
                            '<button type="button" class="km-row-actions-item" data-km-action="view" data-id="' + escapeHtml(recordId) + '" data-slug="' + escapeHtml(String(r.slug || '')) + '" role="menuitem"><i class="fas fa-eye"></i><span>View</span></button>' +
                            '<button type="button" class="km-row-actions-item" data-km-action="print" data-id="' + escapeHtml(recordId) + '" data-slug="' + escapeHtml(String(r.slug || '')) + '" role="menuitem"><i class="fas fa-print"></i><span>Print</span></button>' +
                            '<button type="button" class="km-row-actions-item" data-km-action="edit" data-id="' + escapeHtml(recordId) + '" data-slug="' + escapeHtml(String(r.slug || '')) + '" role="menuitem"><i class="fas fa-pen"></i><span>Edit</span></button>' +
                            '<button type="button" class="km-row-actions-item is-danger" data-km-action="delete" data-id="' + escapeHtml(recordId) + '" data-name="' + escapeHtml(fullName) + '" data-slug="' + escapeHtml(String(r.slug || '')) + '" role="menuitem"><i class="fas fa-trash"></i><span>Delete</span></button>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '</tr>';
        }).join('');
        tbody.innerHTML = rows;

        syncSelectAllCheckbox();
        updateBatchPrintButton();
        updatePagination(state.allItems, state.currentPage);
        prefetchVisibleModalPayloads(pageItems);
    }

    function updatePagination(items, currentPage) {
        var statePag = window.kmPaginationState || {};
        var paginationText = document.getElementById('km-pagination-text');
        var prevBtn = document.getElementById('km-prev-btn');
        var nextBtn = document.getElementById('km-next-btn');
        var pageInput = document.getElementById('km-page-input');
        var totalPagesEl = document.getElementById('km-total-pages');

        if (!items.length) {
            if (paginationText) paginationText.textContent = '0 records';
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            if (pageInput) pageInput.value = '1';
            if (totalPagesEl) totalPagesEl.textContent = '1';
            return;
        }

        if (paginationText) {
            paginationText.textContent = items.length + ' record' + (items.length === 1 ? '' : 's');
        }

        if (pageInput) {
            pageInput.value = String(currentPage);
            pageInput.max = String(statePag.totalPages || 1);
        }
        if (totalPagesEl) {
            totalPagesEl.textContent = String(statePag.totalPages || 1);
        }

        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= (statePag.totalPages || 1);
    }

    window.previousPage = function() {
        var state = window.kmPaginationState || {};
        if (state.currentPage > 1) {
            state.currentPage--;
            renderPaginatedTable();
        }
    };

    window.nextPage = function() {
        var state = window.kmPaginationState || {};
        if (state.currentPage < state.totalPages) {
            state.currentPage++;
            renderPaginatedTable();
        }
    };

    window.goToPage = function(pageNum) {
        var statePag = window.kmPaginationState || {};
        if (pageNum >= 1 && pageNum <= statePag.totalPages) {
            statePag.currentPage = pageNum;
            renderPaginatedTable();
        }
    };

    // ── KK Profiling Form Modal ──
    window.kmInvalidateModalCache = function (recordId) {
        if (recordId) {
            delete modalCache[String(recordId)];
            return;
        }
        Object.keys(modalCache).forEach(function (key) {
            delete modalCache[key];
        });
    };

    window.kmFetchModalPayload = function (recordId) {
        var key = String(recordId || '');
        if (!key) return Promise.resolve(null);
        if (modalCache[key]) return Promise.resolve(modalCache[key]);
        if (modalInflight[key]) return modalInflight[key];

        var urlTemplate = (window.kmConfig && window.kmConfig.questionnaireUrl) || '';
        if (!urlTemplate) return Promise.resolve(null);

        modalInflight[key] = fetch(urlTemplate.replace('__ID__', encodeURIComponent(key)), {
            method: 'GET',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Failed to load questionnaire');
                return res.json();
            })
            .then(function (payload) {
                modalCache[key] = payload;
                return payload;
            })
            .finally(function () {
                delete modalInflight[key];
            });

        return modalInflight[key];
    };

    function prefetchVisibleModalPayloads(pageItems) {
        var run = function () {
            (pageItems || []).forEach(function (item) {
                var id = item && (item.id || item.slug);
                if (id) window.kmFetchModalPayload(id);
            });
        };
        if (window.requestIdleCallback) {
            window.requestIdleCallback(run, { timeout: 1200 });
            return;
        }
        setTimeout(run, 0);
    }

    window.openKKPModal = function(kabataanSlug) {
        var profile = records.find(function(r){ return r.slug === kabataanSlug || String(r.id) === String(kabataanSlug); });
        if (!profile) return;

        if (typeof window.kmExitKKPEditMode === 'function') {
            window.kmExitKKPEditMode();
        }

        var modal = document.getElementById('kmKKPModal');
        var container = document.getElementById('kmKKPViewRoot') || document.getElementById('kmKKPFormContainer');
        if (!modal || !container) return;

        var recordId = profile.id || profile.slug;
        var cached = modalCache[String(recordId)];
        if (cached && cached.html) {
            container.innerHTML = '<div class="kk-qs-scroll-wrapper">' + cached.html + '</div>';
        } else {
            container.innerHTML = '<p class="km-kkp-loading">Loading questionnaire...</p>';
        }
        var editFooter = document.getElementById('kmKKPEditFooter');
        if (editFooter) editFooter.hidden = true;
        modal.classList.add('show');
        modal.classList.remove('is-fullscreen');
        var resizeBtn = document.getElementById('kmKKPFullscreenBtn');
        if (resizeBtn) {
            resizeBtn.classList.remove('is-restore');
            resizeBtn.title = 'Maximize';
            resizeBtn.setAttribute('aria-label', 'Maximize');
        }
        document.body.style.overflow = 'hidden';

        window.kmFetchModalPayload(recordId)
            .then(function(data) {
                var html = (data && data.html) || '<p class="km-kkp-loading">No questionnaire data found.</p>';
                container.innerHTML = '<div class="kk-qs-scroll-wrapper">' + html + '</div>';
            })
            .catch(function() {
                if (!cached) {
                    container.innerHTML = '<p class="km-kkp-loading">Unable to load questionnaire. Please try again.</p>';
                }
            });
    };

    window.closeKKPModal = function() {
        if (typeof window.kmExitKKPEditMode === 'function') {
            window.kmExitKKPEditMode();
        }
        var modal = document.getElementById('kmKKPModal');
        if (modal) {
            modal.classList.remove('show');
            modal.classList.remove('is-fullscreen');
            document.body.style.overflow = '';
        }
        var resizeBtn = document.getElementById('kmKKPFullscreenBtn');
        if (resizeBtn) {
            resizeBtn.classList.remove('is-restore');
            resizeBtn.title = 'Maximize';
            resizeBtn.setAttribute('aria-label', 'Maximize');
        }
    };

    window.toggleKKPFullscreen = function() {
        var modal = document.getElementById('kmKKPModal');
        var resizeBtn = document.getElementById('kmKKPFullscreenBtn');
        if (!modal) return;
        var isFullscreen = modal.classList.toggle('is-fullscreen');
        if (resizeBtn) {
            resizeBtn.classList.toggle('is-restore', isFullscreen);
            resizeBtn.title = isFullscreen ? 'Restore Down' : 'Maximize';
            resizeBtn.setAttribute('aria-label', isFullscreen ? 'Restore Down' : 'Maximize');
        }
    };

    function printKKPHtml(formHTML) {
        if (!formHTML || !String(formHTML).trim()) return;
        var printWindow = window.open('', '', 'height=800,width=900');
        if (!printWindow) {
            alert('Please allow pop-ups to print this questionnaire.');
            return;
        }
        var viewCssHref = window.location.origin + '/modules/kabataan-monitoring/css/kk-questionnaire-view.css';
        printWindow.document.write('<html><head><title>KK Survey Questionnaire</title>');
        printWindow.document.write('<link rel="stylesheet" href="' + viewCssHref + '">');
        printWindow.document.write('<style>@page{size:Letter portrait;margin:.35in;}body{margin:0;} .kk-view-paper{zoom:.88;box-shadow:none;border:none;padding:0;} .kkp-notice-body br{display:none;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(formHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.print();
        };
    }

    window.printKKPById = function(recordId) {
        if (!recordId) return;
        var cached = modalCache[String(recordId)];
        if (cached && cached.html) {
            printKKPHtml(cached.html);
            return;
        }
        window.kmFetchModalPayload(recordId)
            .then(function(data) {
                printKKPHtml((data && data.html) || '');
            })
            .catch(function() {
                alert('Unable to print questionnaire. Please try again.');
            });
    };

    window.printKKPForm = function() {
        var container = document.getElementById('kmKKPViewRoot') || document.getElementById('kmKKPFormContainer');
        if (!container) return;
        printKKPHtml(container.innerHTML);
    };
})();
