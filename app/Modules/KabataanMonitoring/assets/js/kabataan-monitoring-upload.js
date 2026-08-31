// Kabataan Monitoring Upload JavaScript
document.addEventListener('DOMContentLoaded', () => {
    initializeKabataanUploadUI();
});

function initializeKabataanUploadUI() {
    const addKkBtn = document.getElementById('km-add-kk-record-btn');
    const addModal = document.getElementById('kabataanAddModal');
    const manualPane = document.getElementById('kabataanPanelManual');
    const batchPane = document.getElementById('kabataanPanelBatch');
    const manualFooter = document.getElementById('kabataanManualFooter');
    const batchFooter = document.getElementById('kabataanBatchFooter');
    const tabManual = document.getElementById('kmTabManual');
    const tabBatch = document.getElementById('kmTabBatch');
    const fileInput = document.getElementById('kabataanFileInput');
    const selectFileBtn = document.getElementById('kabataanSelectFileBtn');
    const fileInfo = document.getElementById('kabataanFileInfo');
    const fileName = document.getElementById('kabataanFileName');
    const removeFileBtn = document.getElementById('kabataanRemoveFileBtn');
    const uploadBtn = document.getElementById('kabataanBatchUploadBtn');
    const downloadTemplateBtn = document.getElementById('km-download-template-btn');
    const saveBtn = document.getElementById('kabataanSaveBtn');
    const maximizeBtn = document.getElementById('kabataanAddMaximizeBtn');
    const restoreBtn = document.getElementById('kabataanAddRestoreBtn');

    function setAddModalMaximized(maximized) {
        if (!addModal) return;
        addModal.classList.toggle('modal-maximized', maximized);
        if (maximizeBtn) maximizeBtn.hidden = maximized;
        if (restoreBtn) restoreBtn.hidden = !maximized;
    }

    if (maximizeBtn) {
        maximizeBtn.addEventListener('click', () => setAddModalMaximized(true));
    }

    if (restoreBtn) {
        restoreBtn.addEventListener('click', () => setAddModalMaximized(false));
    }

    function switchAddMode(mode) {
        const isBatch = mode === 'batch';

        if (manualPane) manualPane.hidden = isBatch;
        if (batchPane) batchPane.hidden = !isBatch;
        if (manualFooter) manualFooter.hidden = isBatch;
        if (batchFooter) batchFooter.hidden = !isBatch;

        if (tabManual) {
            tabManual.classList.toggle('active', !isBatch);
            tabManual.setAttribute('aria-selected', !isBatch ? 'true' : 'false');
        }
        if (tabBatch) {
            tabBatch.classList.toggle('active', isBatch);
            tabBatch.setAttribute('aria-selected', isBatch ? 'true' : 'false');
        }
    }

    function openAddKkModal(mode) {
        if (!addModal) return;
        setAddModalMaximized(false);
        addModal.style.display = 'flex';
        switchAddMode(mode || 'manual');

        const title = document.getElementById('kabataanAddModalTitle');
        if (title) title.textContent = 'Add KK Record';

        if (mode !== 'batch') {
            initializeKabataanForm();
            if (typeof window.initKabataanAddValidation === 'function') {
                window.initKabataanAddValidation(manualPane);
            }
        }
    }

    if (addKkBtn) {
        addKkBtn.addEventListener('click', () => openAddKkModal('manual'));
    }

    if (tabManual) {
        tabManual.addEventListener('click', () => {
            switchAddMode('manual');
            initializeKabataanForm();
            if (typeof window.initKabataanAddValidation === 'function') {
                window.initKabataanAddValidation(manualPane);
            }
        });
    }

    if (tabBatch) {
        tabBatch.addEventListener('click', () => switchAddMode('batch'));
    }

    // Close modal
    document.querySelectorAll('[data-add-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeAddModal();
        });
    });

    // File selection
    if (selectFileBtn && fileInput) {
        selectFileBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
    }

    // Remove file
    if (removeFileBtn) {
        removeFileBtn.addEventListener('click', () => {
            if (fileInput) fileInput.value = '';
            if (fileInfo) fileInfo.style.display = 'none';
            if (uploadBtn) uploadBtn.disabled = true;
            hidePreview();
        });
    }

    // Download template
    if (downloadTemplateBtn) {
        downloadTemplateBtn.addEventListener('click', () => {
            downloadTemplate();
        });
    }

    // Batch upload
    if (uploadBtn) {
        uploadBtn.addEventListener('click', () => {
            handleBatchUpload();
        });
    }

    // Manual save
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            handleManualSave();
        });
    }

    // Drag and drop
    const dropzone = document.getElementById('kabataanUploadDropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--km-blue)';
        });

        dropzone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropzone.style.borderColor = '#cbd5e1';
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.style.borderColor = '#cbd5e1';
            
            if (e.dataTransfer.files.length > 0) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });
    }
}

function handleFileSelect(file) {
    const fileInfo = document.getElementById('kabataanFileInfo');
    const fileName = document.getElementById('kabataanFileName');
    const uploadBtn = document.getElementById('kabataanBatchUploadBtn');

    if (fileInfo) fileInfo.style.display = 'flex';
    if (fileName) fileName.textContent = file.name;
    if (uploadBtn) uploadBtn.disabled = false;

    // Show preview for Excel files
    if (file.name.endsWith('.xlsx') || file.name.endsWith('.xls')) {
        showExcelPreview(file);
    }
}

function showExcelPreview(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        const data = new Uint8Array(e.target.result);
        try {
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
            
            if (jsonData.length > 0) {
                displayPreviewData(jsonData);
            }
        } catch (error) {
            console.error('Error reading Excel file:', error);
        }
    };
    reader.readAsArrayBuffer(file);
}

function displayPreviewData(data) {
    const previewSection = document.getElementById('kabataanUploadPreview');
    const previewBody = document.getElementById('kabataanPreviewBody');
    
    if (previewSection) previewSection.style.display = 'block';
    
    if (previewBody) {
        previewBody.innerHTML = '';
        
        // Skip header row, show first 10 rows
        const previewRows = data.slice(1, 11);
        
        previewRows.forEach(row => {
            const tr = document.createElement('tr');
            
            // Show first 6 columns
            for (let i = 0; i < Math.min(6, row.length); i++) {
                const td = document.createElement('td');
                td.textContent = row[i] || '';
                tr.appendChild(td);
            }
            
            previewBody.appendChild(tr);
        });
    }
}

function hidePreview() {
    const previewSection = document.getElementById('kabataanUploadPreview');
    if (previewSection) previewSection.style.display = 'none';
}

function downloadTemplate() {
    // Create a simple template with headers
    const headers = ['Last Name', 'First Name', 'Middle Name', 'Suffix', 'Email', 'Contact Number', 
                    'Age', 'Birthday', 'Sex', 'Purok/Zone', 'Civil Status', 'Youth Classification', 
                    'Youth Age Group', 'Work Status', 'Education', 'SK Voter', 'National Voter', 
                    'SK Voted', 'KK Assembly', 'KK Times', 'KK Reason', 'Facebook', 'Group Chat'];
    
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet([headers]);
    XLSX.utils.book_append_sheet(wb, ws, 'Template');
    XLSX.writeFile(wb, 'kabataan_batch_template.xlsx');
}

async function handleBatchUpload() {
    const fileInput = document.getElementById('kabataanFileInput');
    const uploadBtn = document.getElementById('kabataanBatchUploadBtn');
    
    if (!fileInput || !fileInput.files.length) {
        alert('Please select a file to upload');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';
    }

    try {
        const response = await fetch(window.kmConfig.batchUploadUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.kmConfig.csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const result = await response.json();

        if (response.ok) {
            alert('Batch upload completed successfully!');
            closeAddModal();
            // Refresh the data
            if (typeof refreshKabataanData === 'function') {
                refreshKabataanData();
            }
        } else {
            alert('Upload failed: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('Upload failed: ' + error.message);
    } finally {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload Records';
        }
    }
}

async function handleManualSave() {
    const formRoot = document.getElementById('kabataanPanelManual');
    const formData = getKabataanFormData();
    const saveBtn = document.getElementById('kabataanSaveBtn');

    if (typeof window.validateKabataanAddForm === 'function') {
        if (!window.validateKabataanAddForm(formRoot)) {
            return;
        }
    } else if (!validateKabataanForm(formData)) {
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }

    try {
        const response = await fetch(window.kmConfig.storeUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.kmConfig.csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(formData),
        });

        const result = await response.json();

        if (response.ok) {
            alert('Kabataan record saved successfully!');
            closeAddModal();
            // Refresh the data
            if (typeof refreshKabataanData === 'function') {
                refreshKabataanData();
            }
        } else {
            alert('Save failed: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Save error:', error);
        alert('Save failed: ' + error.message);
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Record';
        }
    }
}

function getKabataanFormData() {
    return {
        last_name: document.getElementById('kabataanLastName')?.value || '',
        first_name: document.getElementById('kabataanFirstName')?.value || '',
        middle_name: document.getElementById('kabataanMiddleName')?.value || '',
        suffix: document.getElementById('kabataanSuffix')?.value || '',
        email: document.getElementById('kabataanEmail')?.value || '',
        contact_number: document.getElementById('kabataanContactInput')?.value || '',
        age: document.getElementById('kabataanAgeInput')?.value || '',
        birthday: document.getElementById('kabataanDob')?.value || '',
        sex: document.getElementById('kabataanSex')?.value || '',
        purok_zone: document.getElementById('kabataanPurokZone')?.value || '',
        civil_status: document.getElementById('kabataanCivilStatus')?.value || '',
        youth_classification: document.getElementById('kabataanYouthClassification')?.value || '',
        youth_age_group: document.getElementById('kabataanYouthAgeGroup')?.value || '',
        work_status: document.getElementById('kabataanWorkStatus')?.value || '',
        education: document.getElementById('kabataanEducationalBackground')?.value || '',
        sk_voter: document.getElementById('kabataanRegisteredSKVoter')?.value || '',
        national_voter: document.getElementById('kabataanRegisteredNationalVoter')?.value || '',
        sk_voted: document.getElementById('kabataanVotingHistory')?.value || '',
        kk_assembly: document.getElementById('kabataanAttendedKKAssembly')?.value || '',
        kk_times: document.getElementById('kabataanVotingFrequency')?.value || '',
        kk_reason: document.getElementById('kabataanVotingReason')?.value || '',
        facebook: document.getElementById('kabataanFacebookAccount')?.value || '',
        group_chat: document.getElementById('kabataanWillingToJoinGroupChat')?.value || '',
        signature: document.getElementById('kabataanSignature')?.value || '',
    };
}

function validateKabataanForm(data) {
    if (!data.last_name || !data.first_name) {
        alert('Last Name and First Name are required');
        return false;
    }
    return true;
}

function initializeKabataanForm() {
    // Set default date
    const dateInput = document.getElementById('kabataanDate');
    if (dateInput) {
        dateInput.value = new Date().toLocaleDateString('en-US');
    }

    // Set default location
    const regionInput = document.getElementById('kabataanRegion');
    const provinceInput = document.getElementById('kabataanProvince');
    const cityInput = document.getElementById('kabataanCity');
    const barangayInput = document.getElementById('kabataanBarangay');

    if (regionInput && !regionInput.value) regionInput.value = 'Region IV-A (CALABARZON)';
    if (provinceInput && !provinceInput.value) provinceInput.value = 'Laguna';
    if (cityInput && !cityInput.value) cityInput.value = 'Santa Cruz';
    if (barangayInput && window.kmBarangay && !barangayInput.value) {
        barangayInput.value = window.kmBarangay;
    }
}

function closeAddModal() {
    const addModal = document.getElementById('kabataanAddModal');
    if (addModal) {
        addModal.style.display = 'none';
        addModal.classList.remove('modal-maximized');
        const maximizeBtn = document.getElementById('kabataanAddMaximizeBtn');
        const restoreBtn = document.getElementById('kabataanAddRestoreBtn');
        if (maximizeBtn) maximizeBtn.hidden = false;
        if (restoreBtn) restoreBtn.hidden = true;
    }

    resetBatchUploadPanel();

    const form = document.getElementById('kabataanPanelManual');
    if (form) {
        form.querySelectorAll('input, select').forEach((input) => {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else if (!input.readOnly) {
                input.value = '';
            }
        });
        form.querySelectorAll('.kkp-field-error').forEach((error) => error.remove());
        form.querySelectorAll('.kkp-input-err').forEach((input) => input.classList.remove('kkp-input-err'));
    }

    resetAddModalToManual();
}

function resetBatchUploadPanel() {
    const fileInput = document.getElementById('kabataanFileInput');
    const fileInfo = document.getElementById('kabataanFileInfo');
    const uploadBtn = document.getElementById('kabataanBatchUploadBtn');

    if (fileInput) fileInput.value = '';
    if (fileInfo) fileInfo.style.display = 'none';
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Upload Records';
    }
    hidePreview();
}

function resetAddModalToManual() {
    const manualPane = document.getElementById('kabataanPanelManual');
    const batchPane = document.getElementById('kabataanPanelBatch');
    const manualFooter = document.getElementById('kabataanManualFooter');
    const batchFooter = document.getElementById('kabataanBatchFooter');
    const tabManual = document.getElementById('kmTabManual');
    const tabBatch = document.getElementById('kmTabBatch');

    if (manualPane) manualPane.hidden = false;
    if (batchPane) batchPane.hidden = true;
    if (manualFooter) manualFooter.hidden = false;
    if (batchFooter) batchFooter.hidden = true;
    if (tabManual) {
        tabManual.classList.add('active');
        tabManual.setAttribute('aria-selected', 'true');
    }
    if (tabBatch) {
        tabBatch.classList.remove('active');
        tabBatch.setAttribute('aria-selected', 'false');
    }
}

// Global functions for KK form interactions
window.kkfSingleCheck = function(el, hiddenId) {
    const group = document.querySelectorAll('input[type="checkbox"][name="' + el.name + '"]');
    group.forEach(function(chk) {
        if (chk !== el) chk.checked = false;
    });
    const hidden = document.getElementById(hiddenId);
    if (hidden) hidden.value = el.checked ? el.value : '';
};
