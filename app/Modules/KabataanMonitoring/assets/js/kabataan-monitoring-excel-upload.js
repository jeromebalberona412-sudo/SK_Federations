document.addEventListener('DOMContentLoaded', () => {
    const config = window.kmConfig || {};
    const modal = document.getElementById('kabataanBatchUploadModal');
    const openButton = document.getElementById('km-open-excel-upload');
    const closeButtons = modal?.querySelectorAll('[data-batch-modal-close]') || [];
    const dropzone = document.getElementById('kabataanUploadDropzone');
    const fileInput = document.getElementById('kabataanFileInput');
    const selectButton = document.getElementById('kabataanSelectFileBtn');
    const removeButton = document.getElementById('kabataanRemoveFileBtn');
    const fileInfo = document.getElementById('kabataanFileInfo');
    const fileName = document.getElementById('kabataanFileName');
    const templateButton = document.getElementById('km-download-template-btn');
    const importButton = document.getElementById('kabataanBatchUploadBtn');
    const feedback = document.getElementById('km-upload-feedback');
    const errorsList = document.getElementById('km-upload-errors');
    const preview = document.getElementById('kmExcelPreview');
    const previewHead = document.getElementById('kmExcelPreviewHead');
    const previewBody = document.getElementById('kmExcelPreviewBody');
    const previewMeta = document.getElementById('kmExcelPreviewMeta');
    const previewPagination = document.getElementById('kmExcelPreviewPagination');
    const previewPageLabel = document.getElementById('kmExcelPreviewPageLabel');
    const maximizeButton = document.getElementById('kmBatchUploadMaximizeBtn');
    const restoreButton = document.getElementById('kmBatchUploadRestoreBtn');
    const modalBox = modal?.querySelector('.modal-box');
    const maxFileSize = 25 * 1024 * 1024;
    const maxPreviewRows = 100;
    const maxImportRows = 5000;
    const fieldHeaders = {
        region: ['region'],
        province: ['province'],
        city: ['citymunicipality', 'city', 'municipality'],
        barangay: ['barangay'],
        purok_zone: ['purokzone', 'purok', 'zone'],
        last_name: ['lastname'],
        first_name: ['firstname'],
        middle_name: ['middlename'],
        suffix: ['suffix'],
        sex: ['sexassignedatbirth', 'sex', 'gender'],
        age: ['age'],
        birthday: ['birthday', 'birthdate', 'dob'],
        contact_number: ['contactnumber', 'contact'],
        civil_status: ['civilstatus'],
        youth_classification: ['youthclassification'],
        youth_age_group: ['youthagegroup'],
        education: ['educationalbackground', 'education'],
        work_status: ['workstatus'],
        sk_voter: ['registeredskvoter', 'registeredvoter'],
        sk_voted: ['votedlastsk', 'votedlastelection'],
        kk_assembly: ['attendedkkassembly'],
        kk_times: ['ifyeshowmanytimes', 'howmanytimes'],
        kk_reason: ['ifnowhy', 'kkreason', 'reason'],
    };
    const dropdownOptions = {
        suffix: ['None', 'Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V', 'Others'],
        sex: ['Male', 'Female'],
        civil_status: ['Single', 'Married', 'Widowed', 'Divorced', 'Separated', 'Annulled', 'Unknown', 'Live-in'],
        youth_classification: [
            'In School Youth', 'Out of School Youth', 'Working Youth', 'Youth w/ Specific Needs',
            'Person w/ Disability', 'Children in Conflict w/ Law', 'Indigenous People',
        ],
        youth_age_group: ['Child Youth (15-17 yrs old)', 'Core Youth (18-24 yrs old)', 'Young Adult (15-30 yrs old)'],
        education: [
            'Elementary Level', 'Elementary Grad', 'High School Level', 'High School Grad', 'Vocational Grad',
            'College Level', 'College Grad', 'Masters Level', 'Masters Grad', 'Doctorate Level', 'Doctorate Graduate',
        ],
        work_status: ['Employed', 'Unemployed', 'Self-Employed', 'Currently looking for a Job', 'Not Interested Looking for a Job'],
        sk_voter: ['Yes', 'No'],
        sk_voted: ['Yes', 'No'],
        kk_assembly: ['Yes', 'No'],
        kk_times: ['1-2 Times', '3-4 Times', '5 and above'],
        kk_reason: ['There was no KK Assembly Meeting', 'Not interested to Attend'],
    };
    let selectedFile = null;
    let importing = false;
    let previewReady = false;
    let xlsxPromise = null;
    let worksheetRows = [];
    let worksheetHeaders = [];
    let previewPage = 0;
    const editedRows = new Map();

    if (!modal || !openButton || !fileInput || !importButton) return;

    function showFeedback(message, type = 'info', errors = []) {
        if (feedback) {
            feedback.textContent = message;
            feedback.className = `km-upload-feedback is-${type}`;
            feedback.hidden = !message;
        }
        if (errorsList) {
            errorsList.replaceChildren();
            errors.slice(0, 100).forEach((error) => {
                const item = document.createElement('li');
                item.textContent = error;
                errorsList.append(item);
            });
            errorsList.hidden = errors.length === 0;
        }
    }

    function clearSelection(preserveFeedback = false) {
        selectedFile = null;
        previewReady = false;
        worksheetRows = [];
        worksheetHeaders = [];
        previewPage = 0;
        editedRows.clear();
        fileInput.value = '';
        if (fileInfo) fileInfo.hidden = true;
        if (fileName) fileName.textContent = '';
        if (preview) preview.hidden = true;
        previewHead?.replaceChildren();
        previewBody?.replaceChildren();
        if (previewMeta) previewMeta.textContent = '';
        importButton.disabled = true;
        if (!preserveFeedback) showFeedback('');
    }

    function setMaximized(maximized) {
        modal.classList.toggle('modal-maximized', maximized);
        modalBox?.classList.toggle('modal-maximized', maximized);
        if (maximizeButton) maximizeButton.hidden = maximized;
        if (restoreButton) restoreButton.hidden = !maximized;
    }

    function ensureXlsx() {
        if (window.XLSX) return Promise.resolve(window.XLSX);
        if (!xlsxPromise) {
            xlsxPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
                script.async = true;
                script.onload = () => {
                    if (window.XLSX) {
                        resolve(window.XLSX);
                    } else {
                        xlsxPromise = null;
                        reject(new Error('The spreadsheet preview library did not load.'));
                    }
                };
                script.onerror = () => {
                    xlsxPromise = null;
                    script.remove();
                    reject(new Error('Could not load the spreadsheet preview library. Check your connection and try again.'));
                };
                document.head.append(script);
            });
        }
        return xlsxPromise;
    }

    function normalizeHeader(value) {
        return String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    }

    function fieldForColumn(header) {
        const normalized = normalizeHeader(header);
        return Object.keys(fieldHeaders).find((field) => fieldHeaders[field].includes(normalized)) || null;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cellText(value) {
        if (value instanceof Date && !Number.isNaN(value.getTime())) {
            return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
        }
        return String(value ?? '').trim();
    }

    function selectOptions(field, current) {
        let options = dropdownOptions[field] || [];
        if (field === 'purok_zone') {
            options = Array.isArray(window.kmPurokZones) ? window.kmPurokZones.map(String) : [];
        }
        const value = cellText(current);
        const values = value && !options.some((option) => option.toLowerCase() === value.toLowerCase())
            ? [value, ...options]
            : options;
        return `<select class="km-excel-preview-control" data-field="${field}" aria-label="${escapeHtml(field.replaceAll('_', ' '))}">`
            + '<option value="">—</option>'
            + values.map((option) => `<option value="${escapeHtml(option)}"${option === value ? ' selected' : ''}>${escapeHtml(option)}</option>`).join('')
            + '</select>';
    }

    function renderPreview() {
        if (!preview || !previewHead || !previewBody || !previewMeta) return;
        const header = worksheetHeaders.map((value, index) => {
            const text = String(value ?? '').trim();
            return text || `Column ${index + 1}`;
        });
        const lastNameColumn = header.findIndex((label) => fieldForColumn(label) === 'last_name');
        const firstNameColumn = header.findIndex((label) => fieldForColumn(label) === 'first_name');
        const records = worksheetRows.slice(1)
            .map((row, index) => ({ row: Array.isArray(row) ? row : [], number: index + 2 }))
            .filter(({ row }) => {
                if (lastNameColumn < 0 || firstNameColumn < 0) {
                    return row.some((value) => cellText(value) !== '');
                }
                return cellText(row[lastNameColumn]) !== '' || cellText(row[firstNameColumn]) !== '';
            });
        const pageCount = Math.max(1, Math.ceil(records.length / maxPreviewRows));
        previewPage = Math.min(previewPage, pageCount - 1);
        const pageStart = previewPage * maxPreviewRows;
        const visibleRows = records.slice(pageStart, pageStart + maxPreviewRows);

        if (header.length === 0 || records.length === 0) {
            throw new Error('No column headers or youth records were found on the first worksheet.');
        }

        previewHead.replaceChildren();
        previewBody.replaceChildren();
        const headingRow = document.createElement('tr');
        const numberHeading = document.createElement('th');
        numberHeading.scope = 'col';
        numberHeading.textContent = '#';
        headingRow.append(numberHeading);
        header.forEach((label, index) => {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = label;
            headingRow.append(cell);
        });
        previewHead.append(headingRow);

        visibleRows.forEach(({ row, number }) => {
            const tableRow = document.createElement('tr');
            const rowNumber = document.createElement('td');
            rowNumber.className = 'km-excel-preview-row-number';
            rowNumber.textContent = String(number - 1);
            tableRow.dataset.excelRow = String(number);
            tableRow.append(rowNumber);
            header.forEach((label, columnIndex) => {
                const cell = document.createElement('td');
                const field = fieldForColumn(label);
                const value = row[columnIndex] ?? '';
                const displayValue = field ? (editedRows.get(number)?.[field] ?? value) : value;
                if (field && dropdownOptions[field]) {
                    cell.innerHTML = selectOptions(field, displayValue);
                } else if (field === 'purok_zone' && Array.isArray(window.kmPurokZones) && window.kmPurokZones.length > 0) {
                    cell.innerHTML = selectOptions(field, displayValue);
                } else if (field) {
                    const type = field === 'age' ? 'number' : 'text';
                    const constraints = field === 'age' ? ' min="15" max="30" inputmode="numeric"' : '';
                    cell.innerHTML = `<input class="km-excel-preview-control" type="${type}" data-field="${field}" value="${escapeHtml(cellText(displayValue))}"${constraints} aria-label="${escapeHtml(label)}">`;
                } else {
                    cell.textContent = cellText(value);
                }
                tableRow.append(cell);
            });
            previewBody.append(tableRow);
        });

        if (previewPagination) {
            previewPagination.hidden = pageCount <= 1;
            const previousButton = previewPagination.querySelector('[data-preview-page-step="-1"]');
            const nextButton = previewPagination.querySelector('[data-preview-page-step="1"]');
            if (previousButton) previousButton.disabled = previewPage === 0;
            if (nextButton) nextButton.disabled = previewPage >= pageCount - 1;
        }
        if (previewPageLabel) previewPageLabel.textContent = `Page ${previewPage + 1} of ${pageCount}`;
        const firstVisible = records.length ? pageStart + 1 : 0;
        const lastVisible = Math.min(pageStart + visibleRows.length, records.length);
        previewMeta.textContent = `Showing ${firstVisible}–${lastVisible} of ${records.length.toLocaleString()} records. Editable preview; all rows are validated on the server.`;
        preview.hidden = false;
    }

    function saveEditedCell(control) {
        const row = control.closest('tr[data-excel-row]');
        const field = control.dataset.field;
        if (!row || !field) return;
        const excelRow = Number(row.dataset.excelRow);
        if (!Number.isInteger(excelRow) || excelRow < 2) return;

        const data = editedRows.get(excelRow) || {};
        data[field] = control.value.trim();
        editedRows.set(excelRow, data);
    }

    async function selectFile(file) {
        if (!file) return;
        const extension = file.name.split('.').pop()?.toLowerCase();
        if (!['xlsx', 'xls', 'csv'].includes(extension)) {
            clearSelection();
            showFeedback('Select an .xlsx, .xls, or .csv file.', 'error');
            return;
        }
        if (file.size > maxFileSize) {
            clearSelection();
            showFeedback('The file is larger than the 25 MB limit.', 'error');
            return;
        }

        selectedFile = file;
        previewReady = false;
        if (fileName) fileName.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        if (fileInfo) fileInfo.hidden = false;
        importButton.disabled = true;
        showFeedback('Reading the first worksheet to prepare a preview…', 'info');

        try {
            const XLSX = await ensureXlsx();
            if (selectedFile !== file) return;
            const contents = await file.arrayBuffer();
            const workbook = XLSX.read(contents, { type: 'array', raw: false, cellDates: true });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            if (!firstSheet) throw new Error('The workbook does not contain a readable worksheet.');
            const worksheetRange = firstSheet['!ref']
                ? XLSX.utils.decode_range(firstSheet['!ref'])
                : null;
            if (!worksheetRange) throw new Error('The first worksheet is empty.');
            if (worksheetRange.e.r > maxImportRows) {
                throw new Error(`The spreadsheet exceeds the ${maxImportRows.toLocaleString()} data-row limit.`);
            }
            const sheetRows = XLSX.utils.sheet_to_json(firstSheet, {
                header: 1,
                defval: '',
                raw: false,
                blankrows: true,
                range: {
                    s: { r: 0, c: 0 },
                    e: { r: worksheetRange.e.r, c: Math.min(worksheetRange.e.c, 22) },
                },
            });
            if (selectedFile !== file) return;
            worksheetRows = sheetRows;
            worksheetHeaders = (sheetRows[0] || []).slice(0, 23);
            previewPage = 0;
            renderPreview();
            previewReady = true;
            importButton.disabled = importing;
            showFeedback('Preview ready. Valid rows will be imported as approved; duplicate and invalid rows are reported.', 'info');
        } catch (error) {
            if (selectedFile !== file) return;
            if (preview) preview.hidden = true;
            showFeedback(error instanceof Error ? error.message : 'Could not read the selected spreadsheet.', 'error');
        }
    }

    function closeModal() {
        setMaximized(false);
        modal.hidden = true;
    }

    openButton.addEventListener('click', () => {
        setMaximized(false);
        modal.hidden = false;
        clearSelection();
        modal.querySelector('[data-batch-modal-close]')?.focus();
    });

    maximizeButton?.addEventListener('click', () => setMaximized(true));
    restoreButton?.addEventListener('click', () => setMaximized(false));
    closeButtons.forEach((button) => button.addEventListener('click', () => {
        closeModal();
        openButton.focus();
    }));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
            openButton.focus();
        }
    });
    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
            openButton.focus();
        }
    });

    selectButton?.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => selectFile(fileInput.files?.[0]));
    removeButton?.addEventListener('click', clearSelection);
    templateButton?.addEventListener('click', () => {
        if (config.templateUrl) window.location.assign(config.templateUrl);
    });

    dropzone?.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        fileInput.click();
    });
    dropzone?.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        selectFile(event.dataTransfer?.files?.[0]);
    });
    previewBody?.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement) saveEditedCell(event.target);
    });
    previewBody?.addEventListener('change', (event) => {
        if (event.target instanceof HTMLSelectElement || event.target instanceof HTMLInputElement) {
            saveEditedCell(event.target);
        }
    });
    previewPagination?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-preview-page-step]');
        if (!button || button.disabled) return;
        previewPage += Number(button.dataset.previewPageStep);
        renderPreview();
    });

    importButton.addEventListener('click', async () => {
        if (!selectedFile || !previewReady || importing) return;

        importing = true;
        importButton.disabled = true;
        importButton.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Validating…';
        showFeedback('Validating spreadsheet rows and checking duplicates…', 'info');

        const body = new FormData();
        body.append('file', selectedFile);
        body.append('barangay', String(window.kmBarangay || ''));
        body.append('edited_rows', JSON.stringify(
            Array.from(editedRows, ([number, data]) => ({ number, data }))
        ));

        try {
            const response = await fetch(config.batchUploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': String(config.csrfToken || ''),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body,
            });
            const payload = await response.json();
            const importErrors = Array.isArray(payload?.data?.errors)
                ? payload.data.errors
                : Object.values(payload?.errors || {}).flat();

            if (!response.ok || payload?.success !== true) {
                showFeedback(payload?.message || 'The import could not be completed.', 'error', importErrors);
                return;
            }

            const summary = payload.data || {};
            const hasWarnings = Number(summary.duplicates || 0) > 0 || Number(summary.rejected || 0) > 0;
            showFeedback(payload.message || 'Monitoring upload processed.', hasWarnings ? 'warning' : 'success', importErrors);
            clearSelection(true);
            await window.kmReloadRecords?.({ force: true });
        } catch (error) {
            showFeedback(error instanceof Error ? error.message : 'The upload failed. Please retry.', 'error');
        } finally {
            importing = false;
            importButton.disabled = !selectedFile || !previewReady;
            importButton.innerHTML = '<i class="fas fa-file-import" aria-hidden="true"></i> Import Monitoring Records';
        }
    });
});
