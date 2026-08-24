(function () {
    'use strict';

    var sortMenu = null;
    var sortMenuAnchor = null;

    var FORM_2A_HEADERS = [
        'REGION',
        'PROVINCE',
        'CITY/MUNICIPALITY',
        'BARANGAY',
        'NAME',
        'AGE',
        'BIRTHDAY',
        'SEX ASSIGNED AT BIRTH',
        'CIVIL STATUS',
        'YOUTH CLASSIFICATION',
        'YOUTH AGE GROUP',
        'CONTACT NUMBER',
        'HOME ADDRESS',
        'HIGHEST EDUCATIONAL ATTAINMENT',
        'WORK STATUS',
        'REGISTERED VOTER?',
        'VOTED LAST ELECTION?',
        'ATTENDED KK  ASSEMBLY?',
        'IF YES, HOW MANY TIMES?',
    ];

    var FORM_2A_SUBHEADERS = ['', '', '', '', '', '', 'MONTH/DAY/YEAR', '', '', '', '', '', '', '', '', '', '', '', ''];

    function closeKmSortMenu() {
        if (sortMenu) sortMenu.hidden = true;
        sortMenuAnchor = null;
        document.querySelectorAll('.km-sort-btn').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    function updateSortHeaderState(key, dir) {
        document.querySelectorAll('.km-th-sortable').forEach(function (th) {
            th.classList.remove('is-sorted-asc', 'is-sorted-desc');
            th.setAttribute('aria-sort', 'none');
        });
        var th = document.querySelector('.km-th-sortable[data-sort-key="' + key + '"]');
        if (th && dir) {
            th.classList.add(dir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
        }
    }

    function sortOptionsForKey(key) {
        if (key === 'respondent') {
            return [
                { dir: 'asc', label: 'Sort ascending', hint: '1 → 9', icon: '↑' },
                { dir: 'desc', label: 'Sort descending', hint: '9 → 1', icon: '↓' },
            ];
        }
        return [
            { dir: 'asc', label: 'Sort ascending', hint: 'A → Z', icon: '↑' },
            { dir: 'desc', label: 'Sort descending', hint: 'Z → A', icon: '↓' },
        ];
    }

    function openKmSortMenu(anchor, key) {
        if (!sortMenu || !anchor) return;
        sortMenuAnchor = anchor;
        var th = anchor.closest('.km-th-sortable');
        var currentDir = th?.classList.contains('is-sorted-asc') ? 'asc'
            : th?.classList.contains('is-sorted-desc') ? 'desc' : null;

        sortMenu.innerHTML = '';
        sortOptionsForKey(key).forEach(function (opt) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'km-sort-option' + (currentDir === opt.dir ? ' is-active' : '');
            btn.setAttribute('role', 'menuitem');
            btn.innerHTML = '<span class="km-sort-option-icon">' + opt.icon + '</span><span class="km-sort-option-text"><span class="km-sort-option-label">' + opt.label + '</span><span class="km-sort-option-hint">' + opt.hint + '</span></span>';
            btn.addEventListener('click', function () {
                if (typeof window.kmApplySort === 'function') {
                    window.kmApplySort(key, opt.dir);
                }
                updateSortHeaderState(key, opt.dir);
                closeKmSortMenu();
            });
            sortMenu.appendChild(btn);
        });

        var rect = anchor.getBoundingClientRect();
        sortMenu.hidden = false;
        sortMenu.style.top = (rect.bottom + 4) + 'px';
        sortMenu.style.left = rect.left + 'px';
        anchor.setAttribute('aria-expanded', 'true');
    }

    function csvEscape(value) {
        var text = String(value ?? '');
        if (/[",\n]/.test(text)) {
            return '"' + text.replace(/"/g, '""') + '"';
        }
        return text;
    }

    function exportFileName(ext) {
        var barangay = String(window.kmBarangay || 'kabataan-monitoring').replace(/[^\w\- ]+/g, '').trim() || 'kabataan';
        return barangay.toUpperCase().replace(/\s+/g, ' ') + '.' + ext;
    }

    function downloadBlob(content, mime, filename) {
        var blob = new Blob([content], { type: mime });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function rowToForm2A(row) {
        return [
            row.region,
            row.province,
            row.city,
            row.barangay,
            row.fullName,
            row.age,
            row.birthday,
            row.sex,
            row.civilStatus,
            row.youthClassification,
            row.youthAgeGroup,
            row.contactNumber,
            row.homeAddress,
            row.education,
            row.workStatus,
            row.registeredVoter,
            row.votedLastElection,
            row.kkAssembly,
            row.kkTimes,
        ];
    }

    function selectedDateRange() {
        return {
            start: document.getElementById('kmExportStartDate')?.value || '',
            end: document.getElementById('kmExportEndDate')?.value || '',
        };
    }

    function exportRows(format) {
        if (typeof window.kmGetExportRows !== 'function') return;
        var range = selectedDateRange();
        if (range.start && range.end && range.start > range.end) {
            alert('Start date cannot be later than end date.');
            return;
        }

        var rows = window.kmGetExportRows(range.start, range.end);
        if (!rows.length) {
            alert('No records match the selected date range.');
            return;
        }

        var data = rows.map(rowToForm2A);
        var sheetRows = [FORM_2A_HEADERS, FORM_2A_SUBHEADERS].concat(data);

        if (format === 'csv') {
            var csv = sheetRows.map(function (line) {
                return line.map(csvEscape).join(',');
            }).join('\r\n');
            downloadBlob('\uFEFF' + csv, 'text/csv;charset=utf-8;', exportFileName('csv'));
            closeExportModal();
        }
    }

    function openExportModal() {
        var modal = document.getElementById('kmExportModal');
        if (modal) modal.classList.add('show');
    }

    function closeExportModal() {
        var modal = document.getElementById('kmExportModal');
        if (modal) modal.classList.remove('show');
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.kmPageMode !== 'barangay-detail') return;

        sortMenu = document.getElementById('kmSortMenu');

        document.querySelectorAll('.km-th-sortable .km-sort-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.stopPropagation();
                var th = btn.closest('.km-th-sortable');
                if (!th) return;
                if (sortMenuAnchor === btn && sortMenu && !sortMenu.hidden) {
                    closeKmSortMenu();
                    return;
                }
                closeKmSortMenu();
                openKmSortMenu(btn, th.dataset.sortKey);
            });
        });

        document.addEventListener('click', function (event) {
            if (sortMenu && !sortMenu.hidden && !sortMenu.contains(event.target) && !sortMenuAnchor?.contains(event.target)) {
                closeKmSortMenu();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeKmSortMenu();
                closeExportModal();
            }
        });

        document.getElementById('km-export-csv-btn')?.addEventListener('click', openExportModal);
        document.getElementById('kmExportConfirmCsvBtn')?.addEventListener('click', function () {
            exportRows('csv');
        });
        document.querySelectorAll('[data-km-export-close]').forEach(function (btn) {
            btn.addEventListener('click', closeExportModal);
        });
    });
})();
