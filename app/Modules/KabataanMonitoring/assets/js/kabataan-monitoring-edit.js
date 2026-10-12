(function () {
    'use strict';

    var editingId = null;
    var saving = false;
    var originalEmail = '';

    function getEditField(id) {
        return document.getElementById(id);
    }

    function normalizeCompare(value) {
        return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function valuesMatch(stored, candidate) {
        var left = normalizeCompare(stored);
        var right = normalizeCompare(candidate);
        if (left !== '' && left === right) {
            return true;
        }
        if (left !== '' && right !== '') {
            if (left.indexOf('young adult') !== -1 && right.indexOf('young adult') !== -1) return true;
            if ((left === 'person w/ disability' || left === 'person with disability' || left === 'pwd') && (right === 'person w/ disability' || right === 'youth w/ specific needs')) return true;
            if ((left === 'children in conflict w/ law' || left === 'children in conflict with the law' || left === 'cicl') && (right === 'children in conflict w/ law' || right === 'youth w/ specific needs')) return true;
            if ((left === 'indigenous people' || left === 'ip') && (right === 'indigenous people' || right === 'youth w/ specific needs')) return true;
            if (left.indexOf('high school') !== -1 && right.indexOf('high school') !== -1) {
                if (left.indexOf('level') !== -1 && right.indexOf('level') !== -1) return true;
                if (left.indexOf('grad') !== -1 && right.indexOf('grad') !== -1) return true;
            }
            if ((left.indexOf('no kk assembly') !== -1 || left.indexOf('meeting') !== -1) && right.indexOf('no kk assembly') !== -1) return true;
            if (left.indexOf('not interested') !== -1 && right.indexOf('not interested') !== -1) return true;
        }
        return false;
    }

    function formatMiddleNameDisplay(value) {
        var normalized = String(value || '').trim();
        if (!normalized || normalized === '—' || normalized.toLowerCase() === 'none') {
            return 'None';
        }
        return normalized;
    }

    function normalizeMiddleNameForSave(value) {
        var normalized = String(value || '').trim();
        if (!normalized || normalized.toLowerCase() === 'none') {
            return '';
        }
        return normalized;
    }

    function normalizeYesNoAnswer(value) {
        var text = String(value || '').trim().toLowerCase();
        if (text === 'yes' || text === '1' || text === 'true') return 'Yes';
        if (text === 'no' || text === '0' || text === 'false') return 'No';
        return String(value || '').trim();
    }

    function youthAgeGroupFromAge(age) {
        var n = parseInt(age, 10);
        if (n >= 15 && n <= 17) return 'Child Youth (15-17 yrs old)';
        if (n >= 18 && n <= 24) return 'Core Youth (18-24 yrs old)';
        if (n >= 25 && n <= 30) return 'Young Adult (15-30 yrs old)';
        return '';
    }

    function formatBirthdayForInput(value) {
        if (!value || value === '—') return '';
        var text = String(value).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;
        var slash = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(text);
        if (slash) {
            return slash[3] + '-' + String(slash[1]).padStart(2, '0') + '-' + String(slash[2]).padStart(2, '0');
        }
        return '';
    }

    window.kkEditSingleCheck = function (el, hiddenId) {
        document.querySelectorAll('input[type="checkbox"][name="' + el.name + '"]').forEach(function (chk) {
            if (chk !== el) chk.checked = false;
        });
        var hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = el.checked ? el.value : '';
        if (hiddenId === 'kkEditAttendedKKAssembly') {
            syncEditAssemblyFollowUp();
        }
    };

    function setCheckboxGroupByHiddenId(hiddenId, value) {
        var hidden = getEditField(hiddenId);
        if (!hidden) return;
        hidden.value = value || '';
        var block = hidden.closest('.kkp-demo-block, .kkp-sex-block, .kkp-footer-chat');
        if (!block) return;
        block.querySelectorAll('input.kkp-sq-chk').forEach(function (checkbox) {
            checkbox.checked = value !== '' && valuesMatch(value, checkbox.value);
        });
    }

    function resolveSuffixForEdit(request) {
        var known = ['None', 'Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V'];
        var suffix = String(request.suffix || '').trim();
        var custom = String(request.customSuffix || '').trim();
        if (!suffix || suffix.toLowerCase() === 'none') {
            return { select: 'None', custom: '' };
        }
        if (known.indexOf(suffix) !== -1) {
            return { select: suffix, custom: '' };
        }
        return { select: 'Others', custom: custom || suffix };
    }

    function syncEditCustomSuffixVisibility() {
        var suffixEl = getEditField('kkEditSuffix');
        var wrap = getEditField('kkEditCustomSuffixWrap');
        var customEl = getEditField('kkEditCustomSuffix');
        if (!suffixEl || !wrap) return;
        var isOthers = suffixEl.value === 'Others';
        wrap.classList.toggle('show', isOthers);
        if (customEl) {
            customEl.required = isOthers;
            if (!isOthers) customEl.value = '';
        }
    }

    function setEditAssemblyFollowupState(cell, enabled) {
        if (!cell) return;
        cell.classList.toggle('kkp-assembly-followup--inactive', !enabled);
        cell.classList.toggle('kkp-assembly-followup--active', enabled);
        cell.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.disabled = !enabled;
            if (!enabled) checkbox.checked = false;
        });
        var hidden = cell.querySelector('input[type="hidden"]');
        if (hidden && !enabled) hidden.value = '';
    }

    function syncEditAssemblyFollowUp() {
        var assemblyVal = getEditField('kkEditAttendedKKAssembly')?.value || '';
        var yesCell = document.getElementById('kkEditAssemblyYesCell');
        var noCell = document.getElementById('kkEditAssemblyNoCell');
        var arrowYes = document.querySelector('#kkEditAssemblyQuestion .kkp-assembly-arrow--yes');
        var arrowNo = document.querySelector('#kkEditAssemblyQuestion .kkp-assembly-arrow--no');

        if (assemblyVal === 'Yes') {
            setEditAssemblyFollowupState(yesCell, true);
            setEditAssemblyFollowupState(noCell, false);
            arrowYes?.classList.add('kkp-assembly-arrow--on');
            arrowNo?.classList.remove('kkp-assembly-arrow--on');
            return;
        }
        if (assemblyVal === 'No') {
            setEditAssemblyFollowupState(yesCell, false);
            setEditAssemblyFollowupState(noCell, true);
            arrowYes?.classList.remove('kkp-assembly-arrow--on');
            arrowNo?.classList.add('kkp-assembly-arrow--on');
            return;
        }
        setEditAssemblyFollowupState(yesCell, false);
        setEditAssemblyFollowupState(noCell, false);
        arrowYes?.classList.remove('kkp-assembly-arrow--on');
        arrowNo?.classList.remove('kkp-assembly-arrow--on');
    }

    function normalizeKkReason(value) {
        var text = String(value || '').trim();
        if (!text) return '';
        var lower = text.toLowerCase();
        if (lower.indexOf('no kk assembly') !== -1) return 'There was no KK Assembly Meeting';
        if (lower.indexOf('not interested') !== -1) return 'Not interested to Attend';
        return text;
    }

    function populateSignature(request) {
        var nameEl = getEditField('kkEditSignatureName');
        var preview = getEditField('kkEditSignaturePreview');
        var overlay = getEditField('kkEditSignatureOverlay');
        if (nameEl) nameEl.value = request.signatureName || '';
        if (!preview || !overlay) return;
        var signature = request.signature || '';
        if (String(signature).indexOf('data:image') === 0 || String(signature).indexOf('http') === 0) {
            preview.src = signature;
            overlay.style.display = 'flex';
        } else {
            preview.removeAttribute('src');
            overlay.style.display = 'none';
        }
    }

    function populateKkEditForm(request) {
        var logo = getEditField('kkEditBarangayLogo');
        if (logo && request.barangayLogoUrl) logo.src = request.barangayLogoUrl;

        var textMap = {
            kkEditRespondentNumber: request.respondentNumber || '—',
            kkEditDate: request.date || '—',
            kkEditLastName: request.lastName || '',
            kkEditFirstName: request.firstName || '',
            kkEditMiddleName: formatMiddleNameDisplay(request.middleName),
            kkEditRegion: request.region || 'Region IV-A (CALABARZON)',
            kkEditProvince: request.province || 'Laguna',
            kkEditCity: request.city || 'Santa Cruz',
            kkEditBarangay: request.barangay || '',
            kkEditPurokZone: request.purokZone || '',
            kkEditAgeInput: request.age || '',
            kkEditDob: formatBirthdayForInput(request.birthday),
            kkEditEmail: request.emailAddress && request.emailAddress !== 'No email' ? request.emailAddress : '',
            kkEditContactInput: request.contactNumber || '',
            kkEditFacebookAccount: request.facebookAccount || '',
        };

        Object.keys(textMap).forEach(function (id) {
            var el = getEditField(id);
            if (el) el.value = textMap[id];
        });

        var resolved = resolveSuffixForEdit(request);
        var suffixEl = getEditField('kkEditSuffix');
        var customEl = getEditField('kkEditCustomSuffix');
        if (suffixEl) suffixEl.value = resolved.select;
        if (customEl) customEl.value = resolved.custom;
        syncEditCustomSuffixVisibility();

        setCheckboxGroupByHiddenId('kkEditSex', request.sex || '');
        setCheckboxGroupByHiddenId('kkEditCivilStatus', request.civilStatus || '');
        setCheckboxGroupByHiddenId('kkEditEducationalBackground', request.educationalBackground || '');
        setCheckboxGroupByHiddenId('kkEditYouthClassification', request.youthClassification || '');
        setCheckboxGroupByHiddenId('kkEditWorkStatus', request.workStatus || '');
        setCheckboxGroupByHiddenId('kkEditRegisteredSKVoter', request.registeredSKVoter || '');
        setCheckboxGroupByHiddenId('kkEditRegisteredNationalVoter', request.registeredNationalVoter || '');
        setCheckboxGroupByHiddenId('kkEditVotingHistory', request.votingHistory || '');
        setCheckboxGroupByHiddenId('kkEditAttendedKKAssembly', request.attendedKKAssembly || '');
        setCheckboxGroupByHiddenId('kkEditVotingFrequency', request.kkTimes || '');
        setCheckboxGroupByHiddenId('kkEditVotingReason', normalizeKkReason(request.kkReason));
        setCheckboxGroupByHiddenId('kkEditWillingToJoinGroupChat', normalizeYesNoAnswer(request.willingToJoinGroupChat));
        setCheckboxGroupByHiddenId('kkEditYouthAgeGroup', youthAgeGroupFromAge(request.age) || request.youthAgeGroup || '');
        syncEditAssemblyFollowUp();
        populateSignature(request);
        renderMonitoringDocument(request.monitoringDocument, editingId);
        originalEmail = normalizeCompare(request.emailAddress && request.emailAddress !== 'No email' ? request.emailAddress : '');
    }

    function formatDocumentSize(bytes) {
        var size = Math.max(0, Number(bytes) || 0);
        return size >= 1024 * 1024
            ? (size / (1024 * 1024)).toFixed(1) + ' MB'
            : Math.max(1, Math.round(size / 1024)) + ' KB';
    }

    function renderMonitoringDocument(documentInfo, recordId) {
        var meta = getEditField('kmMonitoringDocumentMeta');
        var links = getEditField('kmMonitoringDocumentLinks');
        var viewLink = getEditField('kmMonitoringDocumentView');
        var downloadLink = getEditField('kmMonitoringDocumentDownload');
        if (!meta || !links || !viewLink || !downloadLink) return;

        var document = documentInfo && typeof documentInfo === 'object' ? documentInfo : null;
        if (!document || !recordId) {
            meta.textContent = 'No PDF has been attached to this record.';
            links.hidden = true;
            viewLink.hidden = true;
            downloadLink.hidden = true;
            return;
        }

        var config = window.kmConfig || {};
        var viewUrl = document.view_url || String(config.documentUrl || '').replace('__ID__', encodeURIComponent(String(recordId)));
        var downloadUrl = document.download_url || String(config.documentDownloadUrl || '').replace('__ID__', encodeURIComponent(String(recordId)));
        var fileName = String(document.filename || 'Monitoring document.pdf');
        meta.textContent = fileName + ' · ' + formatDocumentSize(document.size || 0)
            + (document.uploaded_at ? ' · Uploaded ' + String(document.uploaded_at) : '');
        viewLink.href = viewUrl;
        downloadLink.href = downloadUrl;
        links.hidden = false;
        viewLink.hidden = !viewUrl;
        downloadLink.hidden = !downloadUrl;
    }

    function setMonitoringDocumentStatus(message, isError) {
        var status = getEditField('kmMonitoringDocumentStatus');
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('is-error', Boolean(isError));
        status.hidden = !message;
    }

    function uploadMonitoringDocument() {
        var fileInput = getEditField('kmMonitoringDocumentInput');
        var uploadButton = getEditField('kmMonitoringDocumentUploadBtn');
        var progress = getEditField('kmMonitoringDocumentProgress');
        var file = fileInput?.files?.[0];
        var uploadUrl = String(window.kmConfig?.documentUploadUrl || '');

        if (!editingId || !file || !uploadButton || uploadButton.dataset.uploading === '1') return;
        if (file.size > 10 * 1024 * 1024) {
            setMonitoringDocumentStatus('The PDF exceeds the 10 MB limit.', true);
            return;
        }
        if (!file.name.toLowerCase().endsWith('.pdf')) {
            setMonitoringDocumentStatus('Choose a PDF file.', true);
            return;
        }
        if (!uploadUrl) {
            setMonitoringDocumentStatus('The PDF upload endpoint is unavailable. Refresh the page and try again.', true);
            return;
        }

        var request = new XMLHttpRequest();
        var body = new FormData();
        body.append('file', file);
        uploadButton.dataset.uploading = '1';
        uploadButton.disabled = true;
        fileInput.disabled = true;
        if (progress) {
            progress.value = 0;
            progress.hidden = false;
        }
        setMonitoringDocumentStatus('Uploading and validating PDF…', false);

        request.upload.addEventListener('progress', function (event) {
            if (!progress || !event.lengthComputable) return;
            progress.value = Math.round((event.loaded / event.total) * 100);
        });
        request.addEventListener('load', function () {
            var payload = {};
            try {
                payload = JSON.parse(request.responseText || '{}');
            } catch (_error) {
                payload = {};
            }
            if (request.status < 200 || request.status >= 300 || payload.success !== true) {
                var errors = Object.values(payload.errors || {}).flat();
                setMonitoringDocumentStatus(errors[0] || payload.message || 'The PDF could not be uploaded.', true);
                return;
            }
            renderMonitoringDocument(payload.data, editingId);
            setMonitoringDocumentStatus(payload.message || 'PDF uploaded successfully.', false);
            if (typeof window.kmInvalidateModalCache === 'function') {
                window.kmInvalidateModalCache(editingId);
            }
            if (fileInput) fileInput.value = '';
        });
        request.addEventListener('error', function () {
            setMonitoringDocumentStatus('A network error interrupted the upload. Please retry.', true);
        });
        request.addEventListener('loadend', function () {
            uploadButton.dataset.uploading = '0';
            uploadButton.disabled = false;
            fileInput.disabled = false;
            if (progress) progress.hidden = true;
        });
        request.open('POST', uploadUrl.replace('__ID__', encodeURIComponent(String(editingId))));
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('X-CSRF-TOKEN', String(window.kmConfig?.csrfToken || ''));
        request.withCredentials = true;
        request.send(body);
    }

    function collectKkProfilingEditPayload() {
        var age = getEditField('kkEditAgeInput')?.value?.trim() || '';
        var assembly = getEditField('kkEditAttendedKKAssembly')?.value || '';
        var suffixSelect = getEditField('kkEditSuffix')?.value?.trim() || 'None';
        var customSuffix = getEditField('kkEditCustomSuffix')?.value?.trim() || '';
        var suffix = suffixSelect === 'Others' ? (customSuffix || 'None') : suffixSelect;

        return {
            last_name: getEditField('kkEditLastName')?.value?.trim() || '',
            first_name: getEditField('kkEditFirstName')?.value?.trim() || '',
            middle_name: normalizeMiddleNameForSave(getEditField('kkEditMiddleName')?.value),
            suffix: suffix,
            custom_suffix: suffixSelect === 'Others' ? customSuffix : '',
            age: age,
            birthday: getEditField('kkEditDob')?.value?.trim() || '',
            email: getEditField('kkEditEmail')?.value?.trim() || '',
            contact_number: getEditField('kkEditContactInput')?.value?.trim() || '',
            purok_zone: getEditField('kkEditPurokZone')?.value?.trim() || '',
            sex: getEditField('kkEditSex')?.value || '',
            civil_status: getEditField('kkEditCivilStatus')?.value || '',
            youth_classification: getEditField('kkEditYouthClassification')?.value || '',
            youth_age_group: youthAgeGroupFromAge(age) || getEditField('kkEditYouthAgeGroup')?.value || '',
            work_status: getEditField('kkEditWorkStatus')?.value || '',
            education: getEditField('kkEditEducationalBackground')?.value || '',
            sk_voter: getEditField('kkEditRegisteredSKVoter')?.value || '',
            national_voter: getEditField('kkEditRegisteredNationalVoter')?.value || '',
            sk_voted: getEditField('kkEditVotingHistory')?.value || '',
            kk_assembly: assembly,
            kk_times: assembly === 'Yes' ? (getEditField('kkEditVotingFrequency')?.value || '') : '',
            kk_reason: assembly === 'No' ? (getEditField('kkEditVotingReason')?.value || '') : '',
            facebook: getEditField('kkEditFacebookAccount')?.value?.trim() || '',
            facebook_profile_url: getEditField('kkEditFacebookAccount')?.value?.trim() || '',
            group_chat: normalizeYesNoAnswer(getEditField('kkEditWillingToJoinGroupChat')?.value),
        };
    }

    function setEditMode(isEdit) {
        var viewRoot = document.getElementById('kmKKPViewRoot');
        var editRoot = document.getElementById('kkProfilingEditRoot');
        var editFooter = document.getElementById('kmKKPEditFooter');
        var documentUpload = document.getElementById('kmMonitoringDocumentUpload');
        var title = document.querySelector('#kmKKPModal h2');
        if (viewRoot) viewRoot.hidden = isEdit;
        if (editRoot) editRoot.hidden = !isEdit;
        if (editFooter) editFooter.hidden = !isEdit;
        if (documentUpload) documentUpload.hidden = !isEdit;
        if (title) {
            title.innerHTML = isEdit
                ? '<i class="fas fa-pen"></i> Edit KK Survey Questionnaire'
                : '<i class="fas fa-file-alt"></i> KK Survey Questionnaire';
        }
        if (isEdit && typeof window.initKmEditValidation === 'function') {
            window.initKmEditValidation(editRoot);
        }
        if (typeof window.kmScheduleKkFormScale === 'function') {
            window.kmScheduleKkFormScale();
        }
    }

    function bindEditControls() {
        var documentUploadButton = getEditField('kmMonitoringDocumentUploadBtn');
        if (documentUploadButton && documentUploadButton.dataset.kmBound !== '1') {
            documentUploadButton.dataset.kmBound = '1';
            documentUploadButton.addEventListener('click', uploadMonitoringDocument);
        }
        var suffixEl = getEditField('kkEditSuffix');
        if (suffixEl && suffixEl.dataset.kmBound !== '1') {
            suffixEl.dataset.kmBound = '1';
            suffixEl.addEventListener('change', syncEditCustomSuffixVisibility);
        }
        var ageInput = getEditField('kkEditAgeInput');
        if (ageInput && ageInput.dataset.kmBound !== '1') {
            ageInput.dataset.kmBound = '1';
            ageInput.addEventListener('change', function () {
                setCheckboxGroupByHiddenId('kkEditYouthAgeGroup', youthAgeGroupFromAge(ageInput.value));
            });
        }
        var cancelBtn = document.getElementById('kmKKPEditCancelBtn');
        if (cancelBtn && cancelBtn.dataset.kmBound !== '1') {
            cancelBtn.dataset.kmBound = '1';
            cancelBtn.addEventListener('click', function () {
                setEditMode(false);
                window.closeKKPModal();
                editingId = null;
            });
        }
        var saveBtn = document.getElementById('kmKKPEditSaveBtn');
        if (saveBtn && saveBtn.dataset.kmBound !== '1') {
            saveBtn.dataset.kmBound = '1';
            saveBtn.addEventListener('click', openEditConfirmModal);
        }
        bindEditConfirmModal();
    }

    function syncEditConfirmState() {
        var input = document.getElementById('kmEditConfirmInput');
        var hint = document.getElementById('kmEditConfirmHint');
        var button = document.getElementById('kmEditConfirmBtn');
        if (!input || !button) return;
        var matched = input.value.trim().toLowerCase() === 'yes';
        button.disabled = !matched;
        if (hint) hint.hidden = matched || input.value.trim() === '';
    }

    function openEditConfirmModal() {
        if (!editingId || saving) return;
        var editRoot = document.getElementById('kkProfilingEditRoot');
        if (typeof window.validateKmEditForm === 'function') {
            if (!window.validateKmEditForm(editRoot)) {
                return;
            }
        }
        var payload = collectKkProfilingEditPayload();
        if (!payload.last_name || !payload.first_name) {
            alert('First name and last name are required.');
            return;
        }

        var message = document.getElementById('kmEditConfirmMessage');
        var nextEmail = normalizeCompare(payload.email);
        if (message) {
            if (nextEmail && nextEmail !== originalEmail) {
                message.innerHTML = 'Are you sure you want to edit this KK Profiling record? The email will be changed to <strong>' + escapeEditHtml(payload.email) + '</strong> and an activation email will be sent. Type <strong>yes</strong> to confirm.';
            } else {
                message.innerHTML = 'Are you sure you want to edit this KK Profiling record? Type <strong>yes</strong> to confirm.';
            }
        }

        var modal = document.getElementById('kmEditConfirmModal');
        var input = document.getElementById('kmEditConfirmInput');
        if (input) input.value = '';
        syncEditConfirmState();
        if (modal) modal.classList.add('show');
        input?.focus();
    }

    function closeEditConfirmModal() {
        var modal = document.getElementById('kmEditConfirmModal');
        if (modal) modal.classList.remove('show');
        var input = document.getElementById('kmEditConfirmInput');
        if (input) input.value = '';
        syncEditConfirmState();
    }

    function escapeEditHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function bindEditConfirmModal() {
        var input = document.getElementById('kmEditConfirmInput');
        if (input && input.dataset.kmBound !== '1') {
            input.dataset.kmBound = '1';
            input.addEventListener('input', syncEditConfirmState);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    saveEdit();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeEditConfirmModal();
                }
            });
        }
        var confirmBtn = document.getElementById('kmEditConfirmBtn');
        if (confirmBtn && confirmBtn.dataset.kmBound !== '1') {
            confirmBtn.dataset.kmBound = '1';
            confirmBtn.addEventListener('click', saveEdit);
        }
        document.querySelectorAll('[data-km-edit-confirm-close]').forEach(function (btn) {
            if (btn.dataset.kmBound === '1') return;
            btn.dataset.kmBound = '1';
            btn.addEventListener('click', closeEditConfirmModal);
        });
    }

    async function saveEdit() {
        if (!editingId || saving) return;
        if (document.getElementById('kmEditConfirmInput')?.value.trim().toLowerCase() !== 'yes') return;

        var payload = collectKkProfilingEditPayload();
        if (!payload.last_name || !payload.first_name) {
            alert('First name and last name are required.');
            return;
        }

        var button = document.getElementById('kmEditConfirmBtn');
        var saveBtn = document.getElementById('kmKKPEditSaveBtn');
        var urlTemplate = (window.kmConfig && window.kmConfig.updateUrl) || '';
        var url = urlTemplate.replace('__ID__', encodeURIComponent(String(editingId)));

        saving = true;
        if (button) {
            button.disabled = true;
            button.textContent = 'Saving...';
        }
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        try {
            var response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (window.kmConfig && window.kmConfig.csrfToken) || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            var data = await response.json().catch(function () { return {}; });
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Failed to save KK Profiling record.');
            }
            closeEditConfirmModal();
            setEditMode(false);
            window.closeKKPModal();
            if (typeof window.kmInvalidateModalCache === 'function') {
                window.kmInvalidateModalCache(editingId);
            }
            editingId = null;
            originalEmail = '';
            if (data.message) {
                alert(data.message);
            }
            if (typeof window.kmReloadRecords === 'function') {
                await window.kmReloadRecords();
            }
        } catch (error) {
            alert(error.message || 'Failed to save KK Profiling record.');
        } finally {
            saving = false;
            if (button) {
                button.disabled = false;
                button.textContent = 'Confirm';
            }
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        }
    }

    window.openKKPEditModal = async function (recordId) {
        var modal = document.getElementById('kmKKPModal');
        var viewRoot = document.getElementById('kmKKPViewRoot');
        if (!modal) return;

        editingId = recordId;
        renderMonitoringDocument(null, null);
        setMonitoringDocumentStatus('', false);
        bindEditControls();
        setEditMode(true);
        if (viewRoot) viewRoot.innerHTML = '';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        try {
            var payload = null;
            if (typeof window.kmFetchModalPayload === 'function') {
                payload = await window.kmFetchModalPayload(recordId);
            }
            if (!payload || !payload.data) {
                var editUrlTemplate = (window.kmConfig && window.kmConfig.editUrl) || '';
                var response = await fetch(editUrlTemplate.replace('__ID__', encodeURIComponent(String(recordId))), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                payload = await response.json().catch(function () { return {}; });
                if (!response.ok || !payload.data) {
                    throw new Error(payload.message || 'Failed to load record for editing.');
                }
            }
            populateKkEditForm(payload.data);
            renderMonitoringDocument(payload.data.monitoringDocument, recordId);
        } catch (error) {
            alert(error.message || 'Failed to load record for editing.');
            setEditMode(false);
            window.closeKKPModal();
            editingId = null;
        }
    };

    window.kmExitKKPEditMode = function () {
        closeEditConfirmModal();
        setEditMode(false);
        editingId = null;
        originalEmail = '';
        editingId = null;
    };
})();
