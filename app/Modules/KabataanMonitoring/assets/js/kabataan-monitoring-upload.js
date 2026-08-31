// Kabataan Monitoring Upload JavaScript
document.addEventListener('DOMContentLoaded', () => {
    initializeKabataanUploadUI();
});

function initializeKabataanUploadUI() {
    const addKkBtn = document.getElementById('km-add-kk-record-btn');
    const addModal = document.getElementById('kabataanAddModal');
    const manualPane = document.getElementById('kabataanPanelManual');
    const manualFooter = document.getElementById('kabataanManualFooter');
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

    function openAddKkModal() {
        if (!addModal) return;
        setAddModalMaximized(false);
        addModal.style.display = 'flex';
        if (manualPane) manualPane.hidden = false;
        if (manualFooter) manualFooter.hidden = false;

        const title = document.getElementById('kabataanAddModalTitle');
        if (title) title.textContent = 'Add KK Record';

        initializeKabataanForm();
        if (typeof window.initKabataanAddValidation === 'function') {
            window.initKabataanAddValidation(manualPane);
        }
    }

    if (addKkBtn) {
        addKkBtn.addEventListener('click', () => openAddKkModal());
    }

    const clearAllBtn = document.getElementById('kabataanClearAllBtn');
    const clearAllModal = document.getElementById('kabataanClearAllModal');
    const clearAllConfirmBtn = document.getElementById('kabataanClearAllConfirmBtn');

    function openClearAllModal() {
        if (clearAllModal) clearAllModal.style.display = 'flex';
    }

    function closeClearAllModal() {
        if (clearAllModal) clearAllModal.style.display = 'none';
    }

    clearAllBtn?.addEventListener('click', openClearAllModal);
    clearAllConfirmBtn?.addEventListener('click', () => {
        clearManualKabataanForm();
        closeClearAllModal();
    });
    clearAllModal?.addEventListener('click', (e) => {
        if (e.target === clearAllModal) closeClearAllModal();
    });
    clearAllModal?.querySelectorAll('[data-clear-all-close]').forEach((btn) => {
        btn.addEventListener('click', closeClearAllModal);
    });

    ['kabataanLastName', 'kabataanFirstName', 'kabataanMiddleName', 'kabataanSuffix', 'kabataanCustomSuffix'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', syncKabataanSignatureName);
        el.addEventListener('change', syncKabataanSignatureName);
    });

    // Close modal
    document.querySelectorAll('[data-add-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeAddModal();
        });
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            handleManualSave();
        });
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
            if (typeof window.kmReloadRecords === 'function') {
                window.kmReloadRecords({ force: true });
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
            saveBtn.textContent = 'Save Kabataan';
        }
    }
}

function getKabataanFormData() {
    const suffixSelect = document.getElementById('kabataanSuffix')?.value || 'None';
    const customSuffix = document.getElementById('kabataanCustomSuffix')?.value?.trim() || '';
    const suffix = suffixSelect === 'Others' ? (customSuffix || 'Others') : suffixSelect;

    return {
        last_name: document.getElementById('kabataanLastName')?.value || '',
        first_name: document.getElementById('kabataanFirstName')?.value || '',
        middle_name: document.getElementById('kabataanMiddleName')?.value || '',
        suffix: suffix,
        email: document.getElementById('kabataanEmail')?.value || '',
        contact_number: document.getElementById('kabataanContactInput')?.value || '',
        age: document.getElementById('kabataanAgeInput')?.value || '',
        birthday: document.getElementById('kabataanDob')?.value || '',
        sex: document.getElementById('kabataanSex')?.value || '',
        region: document.getElementById('kabataanRegion')?.value || '',
        province: document.getElementById('kabataanProvince')?.value || '',
        city: document.getElementById('kabataanCity')?.value || '',
        barangay: document.getElementById('kabataanBarangay')?.value || window.kmBarangay || '',
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
        facebook: null,
        group_chat: null,
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
    const dateInput = document.getElementById('kabataanDate');
    if (dateInput) {
        dateInput.value = new Date().toLocaleDateString('en-US');
    }

    const regionInput = document.getElementById('kabataanRegion');
    const provinceInput = document.getElementById('kabataanProvince');
    const cityInput = document.getElementById('kabataanCity');
    const barangayInput = document.getElementById('kabataanBarangay');

    if (regionInput) regionInput.value = 'Region IV-A (CALABARZON)';
    if (provinceInput) provinceInput.value = 'Laguna';
    if (cityInput) cityInput.value = 'Santa Cruz';
    if (barangayInput && window.kmBarangay) {
        barangayInput.value = window.kmBarangay;
    }

    const respondentInput = document.getElementById('kabataanRespondentNumber');
    if (respondentInput) respondentInput.value = 'Auto-assigned on save';

    const contactInput = document.getElementById('kabataanContactInput');
    if (contactInput && !String(contactInput.value || '').trim()) {
        contactInput.value = '09';
    }

    syncKabataanSignatureName();
    if (typeof window.kkfSyncAssemblyFollowUp === 'function') {
        window.kkfSyncAssemblyFollowUp();
    }
}

function syncKabataanSignatureName() {
    const nameEl = document.getElementById('kabataanSignatureName');
    if (!nameEl) return;
    const first = document.getElementById('kabataanFirstName')?.value?.trim() || '';
    const middle = document.getElementById('kabataanMiddleName')?.value?.trim() || '';
    const last = document.getElementById('kabataanLastName')?.value?.trim() || '';
    const suffixSelect = document.getElementById('kabataanSuffix')?.value || 'None';
    const customSuffix = document.getElementById('kabataanCustomSuffix')?.value?.trim() || '';
    const suffix = suffixSelect === 'Others' ? customSuffix : (suffixSelect === 'None' ? '' : suffixSelect);
    nameEl.value = [first, middle, last, suffix].filter(Boolean).join(' ');
    if (typeof window.kabataanSyncSignatureActions === 'function') {
        window.kabataanSyncSignatureActions();
    }
}

function clearManualKabataanForm() {
    const form = document.getElementById('kabataanPanelManual');
    if (!form) return;

    form.querySelectorAll('input, select').forEach((input) => {
        if (input.type === 'checkbox') {
            input.checked = false;
            if (input.closest('.kkp-youth-age-group-readonly')) {
                input.disabled = true;
            } else if (input.closest('.kkp-assembly-followup')) {
                input.disabled = true;
            } else {
                input.disabled = false;
            }
        } else if (input.type === 'hidden') {
            input.value = '';
        } else if (!input.readOnly) {
            if (input.tagName === 'SELECT') {
                if (input.id === 'kabataanSuffix') {
                    input.value = 'None';
                } else {
                    input.selectedIndex = 0;
                }
            } else {
                input.value = '';
            }
        }
    });

    const contactAfterClear = document.getElementById('kabataanContactInput');
    if (contactAfterClear) contactAfterClear.value = '09';

    form.querySelectorAll('.kkp-field-error, .kkp-demo-block-error, .kkp-section-error').forEach((error) => error.remove());
    form.querySelectorAll('.kkp-input-err').forEach((input) => input.classList.remove('kkp-input-err'));

    const customSuffixWrap = document.getElementById('kabataanCustomSuffixWrap');
    if (customSuffixWrap) customSuffixWrap.classList.remove('show');

    const sigOverlay = document.getElementById('kabataanSignatureOverlay');
    const sigPreview = document.getElementById('kabataanSignaturePreview');
    const sigClear = document.getElementById('kabataanSignatureClearSaved');
    if (sigOverlay) sigOverlay.style.display = 'none';
    if (sigPreview) sigPreview.removeAttribute('src');
    if (sigClear) sigClear.hidden = true;
    const sigHidden = document.getElementById('kabataanSignature');
    if (sigHidden) sigHidden.value = '';
    if (typeof window.kabataanSyncSignatureActions === 'function') {
        window.kabataanSyncSignatureActions();
    }

    initializeKabataanForm();
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

    clearManualKabataanForm();
}

// Global functions for KK form interactions
function kkfSetAssemblyFollowupState(cell, enabled) {
    if (!cell) return;
    cell.classList.toggle('kkp-assembly-followup--inactive', !enabled);
    cell.classList.toggle('kkp-assembly-followup--active', enabled);
    cell.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        checkbox.disabled = !enabled;
        if (!enabled) checkbox.checked = false;
    });
    const hidden = cell.querySelector('input[type="hidden"]');
    if (hidden && !enabled) hidden.value = '';
}

function kkfSyncAssemblyFollowUp() {
    const assemblyVal = document.getElementById('kabataanAttendedKKAssembly')?.value || '';
    const yesCell = document.getElementById('kabataanAssemblyYesCell');
    const noCell = document.getElementById('kabataanAssemblyNoCell');
    const arrowYes = document.querySelector('#kabataanAssemblyQuestion .kkp-assembly-arrow--yes');
    const arrowNo = document.querySelector('#kabataanAssemblyQuestion .kkp-assembly-arrow--no');
    const flowYes = document.querySelector('.kabataan-voter-wrap .kkp-assembly-flow-path--yes');
    const flowNo = document.querySelector('.kabataan-voter-wrap .kkp-assembly-flow-path--no');

    if (assemblyVal === 'Yes') {
        kkfSetAssemblyFollowupState(yesCell, true);
        kkfSetAssemblyFollowupState(noCell, false);
        arrowYes?.classList.add('kkp-assembly-arrow--on');
        arrowNo?.classList.remove('kkp-assembly-arrow--on');
        flowYes?.classList.add('kkp-assembly-flow-path--on');
        flowNo?.classList.remove('kkp-assembly-flow-path--on');
        return;
    }
    if (assemblyVal === 'No') {
        kkfSetAssemblyFollowupState(yesCell, false);
        kkfSetAssemblyFollowupState(noCell, true);
        arrowYes?.classList.remove('kkp-assembly-arrow--on');
        arrowNo?.classList.add('kkp-assembly-arrow--on');
        flowYes?.classList.remove('kkp-assembly-flow-path--on');
        flowNo?.classList.add('kkp-assembly-flow-path--on');
        return;
    }
    kkfSetAssemblyFollowupState(yesCell, false);
    kkfSetAssemblyFollowupState(noCell, false);
    arrowYes?.classList.remove('kkp-assembly-arrow--on');
    arrowNo?.classList.remove('kkp-assembly-arrow--on');
    flowYes?.classList.remove('kkp-assembly-flow-path--on');
    flowNo?.classList.remove('kkp-assembly-flow-path--on');
}

window.kkfSingleCheck = function(el, hiddenId) {
    const group = document.querySelectorAll('input[type="checkbox"][name="' + el.name + '"]');
    group.forEach(function(chk) {
        if (chk !== el) chk.checked = false;
    });
    const hidden = document.getElementById(hiddenId);
    if (hidden) hidden.value = el.checked ? el.value : '';
    if (hiddenId === 'kabataanAttendedKKAssembly') {
        kkfSyncAssemblyFollowUp();
    }
};

window.kkfHandleAssembly = function(checkbox) {
    if (!checkbox.checked) return;
    if (checkbox.value === 'Yes') {
        document.querySelectorAll('input[name="kabataanVotingReason"]').forEach((cb) => { cb.checked = false; });
        const reasonHidden = document.getElementById('kabataanVotingReason');
        if (reasonHidden) reasonHidden.value = '';
    } else {
        document.querySelectorAll('input[name="kabataanVotingFrequency"]').forEach((cb) => { cb.checked = false; });
        const timesHidden = document.getElementById('kabataanVotingFrequency');
        if (timesHidden) timesHidden.value = '';
    }
    kkfSyncAssemblyFollowUp();
};

window.kkfSyncAssemblyFollowUp = kkfSyncAssemblyFollowUp;

/**
 * Signature pad for Add KK Record (Manual)
 */
(function initializeKabataanSignaturePad() {
    const overlay = document.getElementById('kabataanSignaturePadOverlay');
    const triggerBtn = document.getElementById('kabataanSignatureTrigger');
    const clearSavedBtn = document.getElementById('kabataanSignatureClearSaved');
    const closeBtn = document.getElementById('kabataanSignaturePadClose');
    const clearBtn = document.getElementById('kabataanSignaturePadClear');
    const saveBtn = document.getElementById('kabataanSignaturePadSave');
    const canvas = document.getElementById('kabataanSignaturePadCanvas');
    const placeholder = document.getElementById('kabataanSignatureCanvasPlaceholder');
    const signatureInput = document.getElementById('kabataanSignature');
    const signaturePreview = document.getElementById('kabataanSignaturePreview');
    const signatureOverlayEl = document.getElementById('kabataanSignatureOverlay');
    const statusEl = document.getElementById('kabataanSignatureStatus');
    const padStatusEl = document.getElementById('kabataanSignaturePadStatus');
    const uploadInput = document.getElementById('kabataanSignatureUploadInput');
    const nameInput = document.getElementById('kabataanSignatureName');

    if (!canvas || !overlay || !triggerBtn) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasSignature = false;

    const MSG_INVALID = 'Please upload a valid image.';
    const MSG_FORMAT_UX = 'Please upload a PNG, JPG, or JPEG image with a white background.';
    const MSG_TOO_LARGE = 'Image size must not exceed 2 MB.';
    const MSG_NON_WHITE = 'Please upload an image with a plain white background.';
    const MSG_BLANK = 'Please upload an image containing visible text or a signature.';
    const MAX_BYTES = 2 * 1024 * 1024;
    const NEAR_WHITE = 250;
    const BG_RATIO = 0.90;
    const MIN_INK_RATIO = 0.0015;

    function setPadStatus(message, isValid) {
        if (!padStatusEl) return;
        if (!message) {
            padStatusEl.hidden = true;
            padStatusEl.textContent = '';
            padStatusEl.classList.remove('is-invalid');
            return;
        }
        padStatusEl.hidden = false;
        padStatusEl.textContent = message;
        padStatusEl.classList.toggle('is-invalid', !isValid);
    }

    function clearFormSignatureStatus() {
        if (!statusEl) return;
        statusEl.hidden = true;
        statusEl.textContent = '';
        statusEl.classList.remove('is-invalid');
    }

    function syncSignatureActions() {
        const hasSaved = Boolean(signatureInput?.value);
        if (clearSavedBtn) clearSavedBtn.hidden = !hasSaved;
        if (triggerBtn && nameInput) {
            const canSign = nameInput.value.trim().length > 0;
            triggerBtn.disabled = !canSign;
            triggerBtn.style.display = 'inline-flex';
            triggerBtn.setAttribute('aria-disabled', canSign ? 'false' : 'true');
        }
    }

    function applySavedSignature(dataUrl) {
        if (!signatureInput) return;
        clearFormSignatureStatus();
        signatureInput.value = dataUrl;
        if (signaturePreview && signatureOverlayEl) {
            signaturePreview.src = dataUrl;
            signatureOverlayEl.style.display = 'flex';
        }
        syncSignatureActions();
    }

    function clearSavedSignature() {
        if (signatureInput) signatureInput.value = '';
        if (signaturePreview) signaturePreview.removeAttribute('src');
        if (signatureOverlayEl) signatureOverlayEl.style.display = 'none';
        clearFormSignatureStatus();
        syncSignatureActions();
    }

    function setupCanvas() {
        const rect = canvas.getBoundingClientRect();
        const width = Math.max(Math.floor(rect.width) || 480, 280);
        const height = Math.max(Math.floor(rect.height) || 180, 140);
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    function showPlaceholder() {
        if (placeholder && !hasSignature) placeholder.style.display = 'block';
    }

    function hidePlaceholder() {
        if (placeholder) placeholder.style.display = 'none';
    }

    function clearCanvas() {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        hasSignature = false;
        showPlaceholder();
    }

    function openSignaturePad() {
        if (triggerBtn.disabled) return;
        overlay.style.display = 'flex';
        setPadStatus('', true);
        setupCanvas();
        if (signatureInput?.value) {
            const img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                hasSignature = true;
                hidePlaceholder();
            };
            img.src = signatureInput.value;
        } else {
            clearCanvas();
        }
    }

    function closeSignaturePad() {
        overlay.style.display = 'none';
        clearCanvas();
        setPadStatus('', true);
        if (uploadInput) uploadInput.value = '';
    }

    function getPosition(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function startDrawing(e) {
        isDrawing = true;
        hasSignature = true;
        const pos = getPosition(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        hidePlaceholder();
        setPadStatus('', true);
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getPosition(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function stopDrawing() {
        isDrawing = false;
    }

    function saveSignature() {
        if (!hasSignature) {
            setPadStatus('Please provide a signature before saving.', false);
            return;
        }
        applySavedSignature(canvas.toDataURL('image/png'));
        closeSignaturePad();
    }

    function isNearWhitePixel(r, g, b, a) {
        if (a < 32) return false;
        return r >= NEAR_WHITE && g >= NEAR_WHITE && b >= NEAR_WHITE;
    }

    function analyzeImageData(imageData, width, height) {
        const data = imageData.data;
        const step = width * height > 800000 ? 2 : 1;
        let sampleTotal = 0;
        let sampleWhite = 0;
        let ink = 0;
        for (let y = 0; y < height; y += step) {
            for (let x = 0; x < width; x += step) {
                const i = (y * width + x) * 4;
                sampleTotal++;
                if (isNearWhitePixel(data[i], data[i + 1], data[i + 2], data[i + 3])) sampleWhite++;
                else ink++;
            }
        }
        return {
            bgRatio: sampleTotal ? sampleWhite / sampleTotal : 0,
            inkRatio: sampleTotal ? ink / sampleTotal : 0,
        };
    }

    function drawUploadedImage(dataUrl) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = function () {
                setupCanvas();
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                const scale = Math.min(canvas.width / img.naturalWidth, canvas.height / img.naturalHeight, 1);
                const dw = img.naturalWidth * scale;
                const dh = img.naturalHeight * scale;
                const dx = (canvas.width - dw) / 2;
                const dy = (canvas.height - dh) / 2;
                ctx.drawImage(img, dx, dy, dw, dh);
                hasSignature = true;
                hidePlaceholder();
                resolve();
            };
            img.onerror = function () {
                reject(new Error(MSG_INVALID));
            };
            img.src = dataUrl;
        });
    }

    function validateUploadedSignatureFile(file) {
        return new Promise((resolve) => {
            if (!file) {
                resolve({ ok: false, error: MSG_INVALID });
                return;
            }
            const name = String(file.name || '').toLowerCase();
            const type = String(file.type || '').toLowerCase();
            const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            const allowedExt = /\.(png|jpe?g)$/i.test(name);
            if (type && !allowedTypes.includes(type)) {
                resolve({ ok: false, error: MSG_FORMAT_UX });
                return;
            }
            if (!type && !allowedExt) {
                resolve({ ok: false, error: MSG_FORMAT_UX });
                return;
            }
            if (file.size > MAX_BYTES) {
                resolve({ ok: false, error: MSG_TOO_LARGE });
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function () {
                try {
                    if (img.naturalWidth < 40 || img.naturalHeight < 20) {
                        URL.revokeObjectURL(objectUrl);
                        resolve({ ok: false, error: MSG_INVALID });
                        return;
                    }
                    const out = document.createElement('canvas');
                    out.width = img.naturalWidth;
                    out.height = img.naturalHeight;
                    const octx = out.getContext('2d', { willReadFrequently: true });
                    octx.drawImage(img, 0, 0);
                    const imageData = octx.getImageData(0, 0, out.width, out.height);
                    const analysis = analyzeImageData(imageData, out.width, out.height);
                    URL.revokeObjectURL(objectUrl);
                    if (analysis.bgRatio < BG_RATIO) {
                        resolve({ ok: false, error: MSG_NON_WHITE });
                        return;
                    }
                    if (analysis.inkRatio < MIN_INK_RATIO) {
                        resolve({ ok: false, error: MSG_BLANK });
                        return;
                    }
                    const exportCanvas = document.createElement('canvas');
                    exportCanvas.width = out.width;
                    exportCanvas.height = out.height;
                    const ectx = exportCanvas.getContext('2d');
                    ectx.fillStyle = '#ffffff';
                    ectx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
                    ectx.drawImage(out, 0, 0);
                    resolve({ ok: true, dataUrl: exportCanvas.toDataURL('image/png') });
                } catch (_e) {
                    URL.revokeObjectURL(objectUrl);
                    resolve({ ok: false, error: MSG_INVALID });
                }
            };
            img.onerror = function () {
                URL.revokeObjectURL(objectUrl);
                resolve({ ok: false, error: MSG_INVALID });
            };
            img.src = objectUrl;
        });
    }

    async function handleSignatureUpload(event) {
        const file = event.target?.files?.[0];
        if (!file) return;
        setPadStatus('Validating image…', true);
        const result = await validateUploadedSignatureFile(file);
        if (!result.ok) {
            setPadStatus(result.error || MSG_FORMAT_UX, false);
            if (uploadInput) uploadInput.value = '';
            return;
        }
        try {
            await drawUploadedImage(result.dataUrl);
            setPadStatus('Valid image selected. Click Save Signature to apply.', true);
        } catch (_err) {
            setPadStatus(MSG_INVALID, false);
            clearCanvas();
        } finally {
            if (uploadInput) uploadInput.value = '';
        }
    }

    triggerBtn.addEventListener('click', openSignaturePad);
    if (clearSavedBtn) clearSavedBtn.addEventListener('click', clearSavedSignature);
    if (closeBtn) closeBtn.addEventListener('click', closeSignaturePad);
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            clearCanvas();
            setPadStatus('', true);
        });
    }
    if (saveBtn) saveBtn.addEventListener('click', saveSignature);
    if (uploadInput) uploadInput.addEventListener('change', handleSignatureUpload);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeSignaturePad();
    });

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);
    canvas.addEventListener('touchstart', function (e) {
        e.preventDefault();
        startDrawing(e);
    }, { passive: false });
    canvas.addEventListener('touchmove', function (e) {
        e.preventDefault();
        draw(e);
    }, { passive: false });
    canvas.addEventListener('touchend', function (e) {
        e.preventDefault();
        stopDrawing();
    }, { passive: false });

    window.addEventListener('resize', function () {
        if (overlay.style.display === 'flex') setupCanvas();
    });

    syncSignatureActions();
    window.kabataanSyncSignatureActions = syncSignatureActions;
    window.kabataanClearSavedSignature = clearSavedSignature;
})();
