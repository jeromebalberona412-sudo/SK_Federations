/**
 * Real-time Validation for KK Survey Questionnaire Edit
 * Modeled after Kabataan KK Profiling form validation
 */
(function (window, document) {
    'use strict';

    const VALID_ROMAN_SUFFIXES = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

    function isValidSuffixText(value) {
        if (!value) return false;
        if (value.length > 5) return false;
        return VALID_ROMAN_SUFFIXES.includes(value.toUpperCase()) || /^[A-Za-z.]+$/.test(value);
    }

    function kkpNormalizeNameValue(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    const KKP_NAME_MAX_CHARS = 150;
    const KKP_NAME_MAX_MSG = '150 maximum characters only.';
    const KKP_NAME_WARNING_THRESHOLD = 50;
    
    // Track confirmed fields for 50+ character confirmation
    const confirmedLongNames = new Set();

    function showLongNameConfirmation(inputElement, fieldId) {
        let modal = document.getElementById('kkpLongNameConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'kkpLongNameConfirmModal';
            modal.className = 'kkp-confirm-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.innerHTML = `
                <div class="kkp-confirm-modal-content">
                    <div class="kkp-confirm-modal-header">
                        <h3>Continue with a longer legal name?</h3>
                        <button type="button" class="kkp-confirm-modal-close" aria-label="Close">&times;</button>
                    </div>
                    <div class="kkp-confirm-modal-body">
                        <p>This name is already 50 characters long.</p>
                        <p>Type <strong>yes</strong> to continue entering more characters (max 150).</p>
                        <label class="kkp-confirm-label" for="kkpLongNameConfirmInput">Confirmation</label>
                        <input type="text" id="kkpLongNameConfirmInput" class="kkp-confirm-input" placeholder="type yes" autocomplete="off" maxlength="8" autocapitalize="none" spellcheck="false">
                        <p class="kkp-confirm-hint" id="kkpLongNameConfirmHint" hidden>Please type “yes” to continue.</p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            const closeBtn = modal.querySelector('.kkp-confirm-modal-close');
            const confirmInput = modal.querySelector('#kkpLongNameConfirmInput');
            const hint = modal.querySelector('#kkpLongNameConfirmHint');

            const closeModal = () => {
                modal.style.display = 'none';
                confirmInput.value = '';
                if (hint) hint.hidden = true;
            };

            const handleCancel = () => {
                const activeFieldId = modal.dataset.targetFieldId || '';
                const activeInput = activeFieldId ? document.getElementById(activeFieldId) : null;
                const target = activeInput || inputElement;
                if (target && !confirmedLongNames.has(activeFieldId)) {
                    target.value = String(target.value || '').substring(0, KKP_NAME_WARNING_THRESHOLD);
                }
                if (target) {
                    target.readOnly = false;
                    target.focus();
                }
                closeModal();
            };

            const handleConfirm = () => {
                const activeFieldId = modal.dataset.targetFieldId || '';
                if (activeFieldId) confirmedLongNames.add(activeFieldId);
                const activeInput = activeFieldId ? document.getElementById(activeFieldId) : null;
                const target = activeInput || inputElement;
                if (target) {
                    target.readOnly = false;
                    target.focus();
                }
                closeModal();
            };

            const isYesTyped = () => String(confirmInput.value || '').trim().toLowerCase() === 'yes';

            closeBtn.addEventListener('click', handleCancel);

            confirmInput.addEventListener('input', () => {
                const typed = String(confirmInput.value || '');
                if (hint) hint.hidden = typed.trim() === '' || isYesTyped();
                if (isYesTyped()) handleConfirm();
            });

            confirmInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (isYesTyped()) handleConfirm();
                    else if (hint) hint.hidden = false;
                }
            });

            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') handleCancel();
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) handleCancel();
            });
        }

        modal.dataset.targetFieldId = fieldId;
        modal.style.display = 'flex';

        setTimeout(() => {
            const confirmInput = modal.querySelector('#kkpLongNameConfirmInput');
            const hint = modal.querySelector('#kkpLongNameConfirmHint');
            if (confirmInput) {
                confirmInput.value = '';
                confirmInput.focus();
            }
            if (hint) hint.hidden = true;
        }, 100);

        inputElement.readOnly = true;
    }

    const KKP_EMAIL_TOTAL_MAX = 64;
    const KKP_EMAIL_LOCAL_MIN = 6;
    const KKP_EMAIL_LOCAL_MAX = 30;

    function kkpSanitizeNameInput(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/[^A-Z.\-\s]/g, '')
            .replace(/^\s+/, '')
            .replace(/\s{2,}/g, ' ');
    }

    function kkpValidateNamePart(value, options = {}) {
        const required = Boolean(options.required);
        const label = options.label || 'Name';
        const touched = Boolean(options.touched);
        const raw = String(value || '');
        const v = kkpNormalizeNameValue(raw);
        const isPureSpaces = raw.length > 0 && v === '';

        if (!v) {
            if (isPureSpaces) {
                return 'Minimum 2 characters required.';
            }
            return required && touched ? `${label} is required.` : null;
        }

        if (v.length < 2) {
            return 'Minimum 2 characters required.';
        }

        if (v.length > KKP_NAME_MAX_CHARS || raw.length > KKP_NAME_MAX_CHARS) {
            return KKP_NAME_MAX_MSG;
        }

        if (!/^[A-Za-z.\-\s]+$/.test(v)) {
            return 'Letters, spaces, periods, and hyphens only.';
        }

        return null;
    }

    function kkpValidateLastName(value, touched) {
        return kkpValidateNamePart(value, { required: true, label: 'Last Name', touched });
    }

    function kkpValidateFirstName(value, touched) {
        return kkpValidateNamePart(value, { required: true, label: 'First Name', touched });
    }

    function kkpValidateMiddleName(value, touched) {
        return kkpValidateNamePart(value, { required: false, label: 'Middle Name', touched });
    }

    function kkpSyncNameMaxIndicator(el) {
        const col = el?.closest('.kkp-name-col') || el?.closest('.kkf-name-col');
        if (!col) return;

        col.querySelectorAll('.kkp-name-max-hint').forEach((node) => node.remove());

        const len = (el.value || '').length;
        if (len < KKP_NAME_MAX_CHARS) {
            return;
        }

        const existingErr = col.querySelector('.kkp-field-error');
        if (existingErr && existingErr.textContent === KKP_NAME_MAX_MSG) {
            return;
        }

        const hint = document.createElement('span');
        hint.className = 'kkp-field-hint kkp-name-max-hint';
        hint.setAttribute('role', 'status');
        hint.textContent = KKP_NAME_MAX_MSG;
        col.appendChild(hint);
    }

    function kkpHasAnySpace(value) {
        return /\s/.test(value || '');
    }

    function kkpEmailLooksComplete(value) {
        const v = String(value || '').trim().toLowerCase();
        if (!v || /\s/.test(v)) {
            return false;
        }
        const atCount = (v.match(/@/g) || []).length;
        if (atCount !== 1) {
            return false;
        }
        const atIndex = v.indexOf('@');
        const localPart = v.slice(0, atIndex);
        const domain = v.slice(atIndex + 1);
        if (!localPart || !domain || !domain.includes('.')) {
            return false;
        }
        return /^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/i.test(localPart)
            && /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i.test(domain);
    }

    function kkpValidateEmail(value, touched, options = {}) {
        const strict = options.strict === true;
        const required = options.required === true;
        const v = (value || '').trim().toLowerCase();

        if (!v) {
            return (required && touched) ? 'E-mail address is required.' : null;
        }

        if (v.length > KKP_EMAIL_TOTAL_MAX) {
            return 'Email must not exceed 64 characters.';
        }

        if (kkpHasAnySpace(v) || (v.match(/@/g) || []).length > 1) {
            return 'Please enter a valid email address.';
        }

        const atIndex = v.indexOf('@');

        if (atIndex >= 0) {
            const localPart = v.slice(0, atIndex);
            if (localPart.length < KKP_EMAIL_LOCAL_MIN) {
                return 'Email username must be at least 6 characters.';
            }
            if (localPart.length > KKP_EMAIL_LOCAL_MAX) {
                return 'Email username must not exceed 30 characters.';
            }
        } else if (strict && v.length < KKP_EMAIL_LOCAL_MIN) {
            return 'Email username must be at least 6 characters.';
        } else if (!strict && v.length > KKP_EMAIL_LOCAL_MAX) {
            return 'Email username must not exceed 30 characters.';
        }

        const complete = kkpEmailLooksComplete(v);
        if (!strict && !complete) {
            return null;
        }

        if ((v.match(/@/g) || []).length !== 1) {
            return 'Please enter a valid email address.';
        }

        const localPart = v.slice(0, atIndex);
        const domain = v.slice(atIndex + 1);

        if (!localPart || !domain) {
            return 'Please enter a valid email address.';
        }
        if (
            localPart.startsWith('.')
            || localPart.endsWith('.')
            || localPart.includes('..')
            || domain.startsWith('.')
            || domain.endsWith('.')
            || domain.includes('..')
            || domain.startsWith('-')
            || domain.endsWith('-')
        ) {
            return 'Please enter a valid email address.';
        }
        if (!/^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/i.test(localPart)) {
            return 'Please enter a valid email address.';
        }
        if (!/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i.test(domain)) {
            return 'Please enter a valid email address.';
        }

        return null;
    }

    function kkpIsObviouslyFakeContact(digits) {
        const raw = String(digits || '').replace(/\D+/g, '');
        if (!raw) return false;

        const local = raw.length === 11 && raw.startsWith('09')
            ? raw
            : (raw.length === 10 && raw.startsWith('9') ? `0${raw}` : null);
        const body = local ? local.slice(2) : raw;

        const isSameDigit = (d) => (
            /^09(\d)\1{8}$/.test(d)
            || /^9(\d)\1{8}$/.test(d)
            || (d.length >= 8 && /^(\d)\1+$/.test(d))
        );

        const isFullSequence = (s) => {
            if (!s || s.length < 8) return false;
            let asc = true;
            let desc = true;
            for (let i = 1; i < s.length; i += 1) {
                const diff = Number(s[i]) - Number(s[i - 1]);
                if (diff !== 1) asc = false;
                if (diff !== -1) desc = false;
            }
            return asc || desc;
        };

        const coversRepeat = (s, blockLen) => {
            if (!s || s.length < 8) return false;
            const repeats = Math.floor(s.length / blockLen);
            if (repeats < 3) return false;
            const block = s.slice(0, blockLen);
            if (!block || /^(.)\1+$/.test(block)) return false;
            const built = block.repeat(repeats);
            return built === s.slice(0, built.length) && built.length >= Math.floor(s.length * 0.85);
        };

        const isRepeatedPattern = (d) => {
            for (let blockLen = 2; blockLen <= 4; blockLen += 1) {
                if (coversRepeat(d, blockLen)) return true;
                if (d.length >= 9 && coversRepeat(d.slice(1), blockLen)) return true;
                if (d.length === 11 && d.startsWith('09') && coversRepeat(d.slice(2), blockLen)) return true;
            }
            return false;
        };

        if (isSameDigit(raw) || (local && isSameDigit(local))) return true;
        if (isFullSequence(raw) || (local && isFullSequence(local.slice(2)))) return true;
        if (isRepeatedPattern(raw) || (local && isRepeatedPattern(local))) return true;

        if (body.length >= 8) {
            const unique = new Set(body.split('')).size;
            if (unique <= 2) return true;
            if (/(\d)\1{5,}/.test(body)) return true;
        }

        return false;
    }

    function kkpValidateContact(value, requireEmpty = false) {
        const v = (value || '').trim();
        if (!v || v === '09') {
            return requireEmpty ? 'Contact number is required.' : null;
        }

        // Strict requirement: Must be exactly 09XXXXXXXXX format (11 digits starting with 09)
        const normalized = v.replace(/\D/g, '');
        
        // Must be exactly 11 digits starting with 09
        if (normalized.length !== 11 || !normalized.startsWith('09')) {
            return 'Please enter a valid Philippine mobile number starting with 09 (09XXXXXXXXX).';
        }

        if (kkpIsObviouslyFakeContact(normalized)) {
            return 'Please enter your actual mobile number.';
        }

        return null;
    }

    function youthAgeGroupFromAge(age) {
        const n = parseInt(age, 10);
        if (n >= 15 && n <= 17) return 'Child Youth (15-17 yrs old)';
        if (n >= 18 && n <= 24) return 'Core Youth (18-24 yrs old)';
        if (n >= 25 && n <= 30) return 'Young Adult (15-30 yrs old)';
        return '';
    }

    function syncYouthAgeGroupFromAge(age, root) {
        const context = root || document;
        const ids = resolveKmFieldIds(context);
        const hidden = context.querySelector
            ? context.querySelector('#' + ids.youthAgeGroup)
            : document.getElementById(ids.youthAgeGroup);
        if (!hidden) return;

        const groupName = youthAgeGroupFromAge(age);
        const block = hidden.closest('.kkf-demo-block, .kkp-demo-block') || context;
        const nameAttr = hidden.id === 'kabataanYouthAgeGroup' ? 'kabataanYouthAgeGroup' : 'kkEditYouthAgeGroup';
        block.querySelectorAll('input[name="' + nameAttr + '"]').forEach((chk) => {
            chk.checked = Boolean(groupName) && chk.value === groupName;
        });
        hidden.value = groupName || '';

        if (groupName) {
            block.querySelectorAll('.kkp-demo-block-error, .kkf-demo-block-error, .kkp-field-error').forEach((node) => node.remove());
        }
    }

    window.setCheckboxGroupByHiddenId = function (hiddenId, value) {
        const hidden = document.getElementById(hiddenId);
        if (!hidden) return;
        hidden.value = value || '';
        const block = hidden.closest('.kkp-demo-block, .kkp-sex-block, .kkf-demo-block, .kkf-sex-block');
        if (!block) return;
        block.querySelectorAll('input.kkp-sq-chk, input.kkf-sq-chk').forEach((checkbox) => {
            checkbox.checked = value !== '' && checkbox.value === value;
        });
    };

    window.syncYouthAgeGroupFromAge = syncYouthAgeGroupFromAge;

    function getFieldErrorHost(el) {
        if (!el) return null;
        return el.closest('.kkp-inline-pair')
            || el.closest('.kkf-inline-pair')
            || el.closest('.kkp-name-col')
            || el.closest('.kkf-name-col')
            || el.closest('.kkp-loc-col')
            || el.closest('.kkf-loc-col')
            || el.closest('.kkp-birthday-field')
            || el.closest('.kkp-age-select-box')
            || el.parentNode;
    }

    function clearFieldError(el) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        el.classList.remove('kkp-input-err');
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        const wrap = host.parentElement;
        if (wrap && (wrap.classList.contains('kkp-inline-pair') || wrap.classList.contains('kkf-inline-pair'))) {
            wrap.querySelectorAll(':scope > .kkp-field-error').forEach((node) => node.remove());
        }
        if (el.closest('.kkp-name-col') || el.closest('.kkf-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
    }

    function showFieldError(el, msg) {
        if (!el) return;
        const host = el.closest('.kkp-inline-pair')
            || el.closest('.kkf-inline-pair')
            || el.closest('.kkp-name-col')
            || el.closest('.kkf-name-col')
            || el.closest('.kkp-loc-col')
            || el.closest('.kkf-loc-col')
            || getFieldErrorHost(el);
        if (!host) return;
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        el.classList.add('kkp-input-err');
        const err = document.createElement('span');
        err.className = 'kkp-field-error';
        err.setAttribute('role', 'alert');
        err.textContent = msg;
        host.appendChild(err);
        if (el.closest('.kkp-name-col') || el.closest('.kkf-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
    }

    function demoBlockError(hiddenId, message, root) {
        const context = root || document;
        const el = context.getElementById ? context.getElementById(hiddenId) : document.getElementById(hiddenId);
        if (!el) return;

        const block = el.closest('.kkp-demo-block') || el.closest('.kkf-demo-block');
        if (!block) return;

        block.querySelectorAll('.kkp-demo-block-error, .kkf-demo-block-error').forEach((node) => node.remove());
        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-demo-block-error';
        err.setAttribute('role', 'alert');
        err.textContent = message;
        block.appendChild(err);
    }

    function personalLeftError(message, root) {
        const context = root || document;
        const sexBlock = context.querySelector('.kkp-sex-block') || context.querySelector('.kkf-sex-block');
        const left = context.querySelector('.kkp-personal-left') || context.querySelector('.kkf-personal-left');
        const host = sexBlock || left;
        if (!host) return;

        const parent = host.parentElement || host;
        parent.querySelectorAll('.kkp-section-error').forEach((node) => node.remove());
        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-section-error';
        err.setAttribute('role', 'alert');
        err.textContent = message;
        if (sexBlock && sexBlock.parentNode) {
            sexBlock.insertAdjacentElement('afterend', err);
        } else {
            host.appendChild(err);
        }
    }

    function calculateAgeFromBirthday(dateStr) {
        if (!dateStr) return null;
        
        // Handle various date formats: YYYY-MM-DD, MM/DD/YYYY, DD/MM/YYYY
        let parsedDate;
        
        // Try YYYY-MM-DD format first (HTML date input format)
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            parsedDate = new Date(dateStr + 'T00:00:00');
        } 
        // Try MM/DD/YYYY format
        else if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
            const parts = dateStr.split('/');
            const month = parseInt(parts[0], 10);
            const day = parseInt(parts[1], 10);
            const year = parseInt(parts[2], 10);
            
            // Validate the date components
            if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900 || year > 2100) {
                return null;
            }
            
            // Check for invalid dates like 02/30
            const testDate = new Date(year, month - 1, day);
            if (testDate.getMonth() !== month - 1 || testDate.getDate() !== day) {
                return null;
            }
            
            parsedDate = new Date(year, month - 1, day);
        }
        // Try DD/MM/YYYY format
        else if (/^\d{1,2}-\d{1,2}-\d{4}$/.test(dateStr)) {
            const parts = dateStr.split('-');
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            const year = parseInt(parts[2], 10);
            
            if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900 || year > 2100) {
                return null;
            }
            
            const testDate = new Date(year, month - 1, day);
            if (testDate.getMonth() !== month - 1 || testDate.getDate() !== day) {
                return null;
            }
            
            parsedDate = new Date(year, month - 1, day);
        }
        else {
            parsedDate = new Date(dateStr);
        }
        
        if (isNaN(parsedDate.getTime())) return null;
        
        const now = new Date();
        let age = now.getFullYear() - parsedDate.getFullYear();
        const monthDiff = now.getMonth() - parsedDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < parsedDate.getDate())) {
            age -= 1;
        }
        return { age, bday: parsedDate, now };
    }

    /**
     * Resolve field IDs for edit vs add (manual entry) forms.
     */
    function resolveKmFieldIds(root) {
        const isAddForm = Boolean(root && root.querySelector('#kabataanLastName'));

        if (isAddForm) {
            return {
                lastName: 'kabataanLastName',
                firstName: 'kabataanFirstName',
                middleName: 'kabataanMiddleName',
                suffix: 'kabataanSuffix',
                customSuffix: 'kabataanCustomSuffix',
                customSuffixWrap: 'kabataanCustomSuffixWrap',
                purok: 'kabataanPurokZone',
                age: 'kabataanAgeInput',
                dob: 'kabataanDob',
                email: 'kabataanEmail',
                contact: 'kabataanContactInput',
                sex: 'kabataanSex',
                civilStatus: 'kabataanCivilStatus',
                youthAgeGroup: 'kabataanYouthAgeGroup',
                education: 'kabataanEducationalBackground',
                youthClassification: 'kabataanYouthClassification',
                workStatus: 'kabataanWorkStatus',
                skVoter: 'kabataanRegisteredSKVoter',
                votingHistory: 'kabataanVotingHistory',
                votingFrequency: 'kabataanVotingFrequency',
                nationalVoter: 'kabataanRegisteredNationalVoter',
                kkAssembly: 'kabataanAttendedKKAssembly',
                votingReason: 'kabataanVotingReason',
                clearBtn: null,
                emailRequired: true,
                clearBtn: null,
            };
        }

        return {
            lastName: 'kkEditLastName',
            firstName: 'kkEditFirstName',
            middleName: 'kkEditMiddleName',
            suffix: 'kkEditSuffix',
            customSuffix: 'kkEditCustomSuffix',
            customSuffixWrap: 'kkEditCustomSuffixWrap',
            purok: 'kkEditPurokZone',
            age: 'kkEditAgeInput',
            dob: 'kkEditDob',
            email: 'kkEditEmail',
            contact: 'kkEditContactInput',
            sex: 'kkEditSex',
            civilStatus: 'kkEditCivilStatus',
            youthAgeGroup: 'kkEditYouthAgeGroup',
            education: 'kkEditEducationalBackground',
            youthClassification: 'kkEditYouthClassification',
            workStatus: 'kkEditWorkStatus',
            skVoter: 'kkEditRegisteredSKVoter',
            votingHistory: 'kkEditVotingHistory',
            votingFrequency: 'kkEditVotingFrequency',
            nationalVoter: 'kkEditRegisteredNationalVoter',
            kkAssembly: 'kkEditAttendedKKAssembly',
            votingReason: 'kkEditVotingReason',
            clearBtn: 'kkEditClearDataBtn',
            emailRequired: true,
        };
    }

    /**
     * Bind real-time input and change listeners for edit form
     */
    function initKmEditValidation(rootElement) {
        const root = rootElement || document.getElementById('kkProfilingEditRoot') || document;
        const ids = resolveKmFieldIds(root);

        const lastNameInput = root.querySelector('#' + ids.lastName);
        const firstNameInput = root.querySelector('#' + ids.firstName);
        const middleNameInput = root.querySelector('#' + ids.middleName);
        const suffixSelect = root.querySelector('#' + ids.suffix);
        const customSuffixInput = root.querySelector('#' + ids.customSuffix);
        const customSuffixWrap = root.querySelector('#' + ids.customSuffixWrap);
        const purokInput = root.querySelector('#' + ids.purok);
        const ageSelect = root.querySelector('#' + ids.age);
        const dobInput = root.querySelector('#' + ids.dob);
        const emailInput = root.querySelector('#' + ids.email);
        const contactInput = root.querySelector('#' + ids.contact);

        // Name fields: sanitize and validate in real-time with 50-char confirmation
        [
            { el: lastNameInput, fn: kkpValidateLastName },
            { el: firstNameInput, fn: kkpValidateFirstName },
            { el: middleNameInput, fn: kkpValidateMiddleName },
        ].forEach(({ el, fn }) => {
            if (!el || el.dataset.kmValBound === '1') return;
            el.dataset.kmValBound = '1';

            el.addEventListener('input', () => {
                const currentLength = el.value.length;
                const fieldId = el.id;
                
                // Check if we need confirmation for 50+ characters
                if (currentLength === KKP_NAME_WARNING_THRESHOLD && !confirmedLongNames.has(fieldId)) {
                    showLongNameConfirmation(el, fieldId);
                }
                
                const sanitized = kkpSanitizeNameInput(el.value);
                if (el.value !== sanitized) {
                    el.value = sanitized;
                }
                const msg = fn(el.value, true);
                if (msg) showFieldError(el, msg);
                else clearFieldError(el);
            });

            el.addEventListener('blur', () => {
                el.value = kkpNormalizeNameValue(el.value);
                const msg = fn(el.value, true);
                if (msg) showFieldError(el, msg);
                else clearFieldError(el);
            });
        });

        // Suffix handling
        if (suffixSelect && customSuffixWrap && customSuffixInput && suffixSelect.dataset.kmValBound !== '1') {
            suffixSelect.dataset.kmValBound = '1';
            const toggleCustom = () => {
                const isOther = suffixSelect.value === 'Others';
                customSuffixWrap.classList.toggle('show', isOther);
                if (!isOther) {
                    customSuffixInput.value = '';
                    clearFieldError(customSuffixInput);
                }
                clearFieldError(suffixSelect);
            };
            suffixSelect.addEventListener('change', toggleCustom);
            toggleCustom();

            customSuffixInput.addEventListener('input', () => {
                let v = (customSuffixInput.value || '').replace(/[^A-Za-z.\s]/g, '');
                v = v.replace(/\s{2,}/g, ' ').trimStart();
                customSuffixInput.value = v.slice(0, 5);
                const trimmed = customSuffixInput.value.trim();
                if (!trimmed) {
                    showFieldError(customSuffixInput, 'Custom suffix is required.');
                } else if (!isValidSuffixText(trimmed)) {
                    showFieldError(customSuffixInput, 'Only text and valid Roman numeral suffixes are allowed.');
                } else {
                    clearFieldError(customSuffixInput);
                }
            });
        }

        // Purok validation (optional for edit form)
        if (purokInput && purokInput.dataset.kmValBound !== '1') {
            purokInput.dataset.kmValBound = '1';
            const validatePurok = () => {
                const v = (purokInput.value || '').trim();
                // Purok is optional in edit form, only validate if provided
                if (v && v.length > 100) {
                    showFieldError(purokInput, 'Purok/Zone must not exceed 100 characters.');
                } else {
                    clearFieldError(purokInput);
                }
            };
            purokInput.addEventListener('input', validatePurok);
            purokInput.addEventListener('change', validatePurok);
            purokInput.addEventListener('blur', validatePurok);
        }

        // Contact input: handle multiple PH mobile formats
        if (contactInput && contactInput.dataset.kmValBound !== '1') {
            contactInput.dataset.kmValBound = '1';

            const validateContact = () => {
                const msg = kkpValidateContact(contactInput.value, false);
                if (msg) showFieldError(contactInput, msg);
                else clearFieldError(contactInput);
            };

            contactInput.addEventListener('input', function () {
                let value = (this.value || '').replace(/\D/g, ''); // Remove all non-digits

                if (!value) {
                    value = '';
                } else if (value.length === 1 && value === '9') {
                    value = '09';
                } else if (!value.startsWith('09')) {
                    if (value.startsWith('9')) {
                        value = '0' + value;
                    } else {
                        value = '09' + value.replace(/^0+/, '');
                    }
                }

                if (value.length > 11) {
                    value = value.slice(0, 11);
                }

                this.value = value;
                validateContact();
            });

            contactInput.addEventListener('keydown', function (e) {
                const digits = (this.value || '').replace(/\D/g, '');
                if ((e.key === 'Backspace' || e.key === 'Delete') && digits.length <= 2) {
                    e.preventDefault();
                    this.value = '';
                    validateContact();
                }
            });
            contactInput.addEventListener('blur', function () {
                // Ensure it's exactly 09XXXXXXXXX format
                let value = (this.value || '').replace(/\D/g, '');
                if (value.length === 10 && value.startsWith('9')) {
                    value = '0' + value;
                }
                this.value = value;
                validateContact();
            });
        }

        // Email input (required in edit form)
        if (emailInput && emailInput.dataset.kmValBound !== '1') {
            emailInput.dataset.kmValBound = '1';
            const validateEmail = (strict = false) => {
                const msg = kkpValidateEmail(emailInput.value, true, { strict, required: true });
                if (msg) showFieldError(emailInput, msg);
                else clearFieldError(emailInput);
            };
            emailInput.addEventListener('input', () => validateEmail(false));
            emailInput.addEventListener('blur', () => validateEmail(true));
        }

        // Birthday & Age synchronization
        if (dobInput && dobInput.dataset.kmValBound !== '1') {
            dobInput.dataset.kmValBound = '1';
            
            // Set dynamic date range for 15-30 age requirement
            const setBirthdayRange = () => {
                const today = new Date();
                // Min date: 30 years ago (oldest allowed)
                const minDate = new Date(today.getFullYear() - 30, today.getMonth(), today.getDate());
                // Max date: 15 years ago (youngest allowed)
                const maxDate = new Date(today.getFullYear() - 15, today.getMonth(), today.getDate());
                
                dobInput.min = minDate.toISOString().split('T')[0];
                dobInput.max = maxDate.toISOString().split('T')[0];
            };
            
            setBirthdayRange();
            
            dobInput.addEventListener('change', () => {
                const info = calculateAgeFromBirthday(dobInput.value);
                if (!info) return;
                const { age, bday, now } = info;
                if (bday > now) {
                    showFieldError(dobInput, 'Birthday cannot be in the future.');
                    return;
                }
                if (age < 15) {
                    showFieldError(dobInput, 'Age must be at least 15 years old.');
                    return;
                }
                if (age > 30) {
                    showFieldError(dobInput, 'Age must not exceed 30 years old.');
                    return;
                }
                clearFieldError(dobInput);
                if (ageSelect) {
                    ageSelect.value = String(age);
                    clearFieldError(ageSelect);
                }
                const youthAgeGroupEl = root.querySelector('#' + ids.youthAgeGroup);
                if (youthAgeGroupEl) {
                    syncYouthAgeGroupFromAge(age, root);
                }
            });
        }

        if (ageSelect && ageSelect.dataset.kmValBound !== '1') {
            ageSelect.dataset.kmValBound = '1';
            ageSelect.addEventListener('change', () => {
                const val = parseInt(ageSelect.value, 10);
                if (!val || val < 15 || val > 30) {
                    showFieldError(ageSelect, 'Age must be 15 to 30 only.');
                    syncYouthAgeGroupFromAge('', root);
                    return;
                }
                clearFieldError(ageSelect);
                syncYouthAgeGroupFromAge(val, root);
                if (dobInput && dobInput.value) {
                    const info = calculateAgeFromBirthday(dobInput.value);
                    if (info && info.age !== val) {
                        showFieldError(dobInput, 'Birthday must match the selected age.');
                    } else if (info) {
                        clearFieldError(dobInput);
                    }
                }
            });
        }

        // Clear section errors on checkbox clicks
        root.querySelectorAll('.kkp-demo-block input.kkp-sq-chk, .kkf-demo-block input.kkf-sq-chk').forEach((chk) => {
            chk.addEventListener('change', () => {
                const block = chk.closest('.kkp-demo-block, .kkf-demo-block');
                if (block) block.querySelectorAll('.kkp-demo-block-error').forEach((e) => e.remove());
            });
        });

        root.querySelectorAll('.kkp-sex-block input.kkp-sq-chk, .kkf-sex-block input.kkf-sq-chk').forEach((chk) => {
            chk.addEventListener('change', () => {
                const sexBlock = chk.closest('.kkp-sex-block, .kkf-sex-block');
                const host = sexBlock?.parentElement || root.querySelector('.kkp-personal-center, .kkp-personal-left, .kkf-personal-left');
                if (host) host.querySelectorAll('.kkp-section-error').forEach((e) => e.remove());
            });
        });

        // Clear Data button functionality
        const clearDataBtn = ids.clearBtn ? root.querySelector('#' + ids.clearBtn) : null;
        if (clearDataBtn && clearDataBtn.dataset.kmValBound !== '1') {
            clearDataBtn.dataset.kmValBound = '1';
            clearDataBtn.addEventListener('click', () => {
                showClearDataConfirmation(root);
            });
        }
    }

    /**
     * Comprehensive form validation before save
     */
    function validateKmEditForm(rootElement) {
        const root = rootElement || document.getElementById('kkProfilingEditRoot') || document;
        const ids = resolveKmFieldIds(root);

        // Clear previous errors
        root.querySelectorAll('.kkp-field-error').forEach((el) => el.remove());
        root.querySelectorAll('.kkp-input-err').forEach((el) => el.classList.remove('kkp-input-err'));

        const errors = [];

        function hiddenVal(id) {
            const el = root.querySelector('#' + id);
            return el ? el.value.trim() : '';
        }

        // 1. Last Name
        const lastName = root.querySelector('#' + ids.lastName);
        const lastNameMsg = kkpValidateLastName(lastName?.value, true);
        if (lastNameMsg) {
            errors.push(lastNameMsg);
            showFieldError(lastName, lastNameMsg);
        }

        // 2. First Name
        const firstName = root.querySelector('#' + ids.firstName);
        const firstNameMsg = kkpValidateFirstName(firstName?.value, true);
        if (firstNameMsg) {
            errors.push(firstNameMsg);
            showFieldError(firstName, firstNameMsg);
        }

        // 3. Middle Name
        const middleName = root.querySelector('#' + ids.middleName);
        const middleNameMsg = kkpValidateMiddleName(middleName?.value, true);
        if (middleNameMsg) {
            errors.push(middleNameMsg);
            showFieldError(middleName, middleNameMsg);
        }

        // 4. Suffix
        const suffix = root.querySelector('#' + ids.suffix);
        const customSuffix = root.querySelector('#' + ids.customSuffix);
        const suffixValue = (suffix?.value || '').trim();
        if (!suffix || !suffixValue) {
            errors.push('Suffix is required.');
            showFieldError(suffix, 'Please select a suffix.');
        } else if (suffixValue === 'Others') {
            const raw = (customSuffix && customSuffix.value ? customSuffix.value : '').trim();
            if (!raw) {
                errors.push('Custom suffix is required.');
                showFieldError(customSuffix, 'Please specify your suffix.');
            } else if (raw.length > 5) {
                errors.push('Suffix must not exceed 5 characters.');
                showFieldError(customSuffix, 'Suffix must not exceed 5 characters.');
            } else if (!isValidSuffixText(raw)) {
                errors.push('Only text and valid Roman numeral suffixes are allowed.');
                showFieldError(customSuffix, 'Only text and valid Roman numeral suffixes are allowed.');
            }
        }

        // 5. Purok/Zone (optional in edit form)
        const purok = root.querySelector('#' + ids.purok);
        if (purok && purok.value.trim() && purok.value.trim().length > 100) {
            errors.push('Purok/Zone must not exceed 100 characters.');
            showFieldError(purok, 'Purok/Zone must not exceed 100 characters.');
        }

        // 6. Sex
        if (!hiddenVal(ids.sex)) {
            errors.push('Sex Assigned by Birth is required.');
            personalLeftError('Please select Sex Assigned by Birth.', root);
        }

        // 7. Age
        const age = root.querySelector('#' + ids.age);
        const ageVal = parseInt(age?.value, 10);
        if (!age || !age.value.trim() || isNaN(ageVal)) {
            errors.push('Age is required.');
            showFieldError(age, 'Age is required.');
        } else if (ageVal < 15 || ageVal > 30) {
            errors.push('Age must be 15 to 30 only.');
            showFieldError(age, 'Age must be 15 to 30 only.');
        }

        // 8. Birthday
        const birthday = root.querySelector('#' + ids.dob);
        if (!birthday || !birthday.value.trim()) {
            errors.push('Birthday is required.');
            showFieldError(birthday, 'Birthday is required.');
        } else {
            const info = calculateAgeFromBirthday(birthday.value);
            if (!info || isNaN(info.bday.getTime())) {
                errors.push('Please enter a valid birthday.');
                showFieldError(birthday, 'Please enter a valid birthday.');
            } else {
                const { age: derivedAge, bday, now } = info;
                if (bday > now) {
                    errors.push('Birthday cannot be in the future.');
                    showFieldError(birthday, 'Birthday cannot be in the future.');
                } else if (derivedAge < 15) {
                    errors.push('Age must be at least 15 years old.');
                    showFieldError(birthday, 'Age must be at least 15 years old.');
                } else if (derivedAge > 30) {
                    errors.push('Age must not exceed 30 years old.');
                    showFieldError(birthday, 'Age must not exceed 30 years old.');
                } else if (age && age.value.trim() && derivedAge !== ageVal) {
                    errors.push('Birthday must match the selected age.');
                    showFieldError(birthday, 'Birthday must match the selected age.');
                }
            }
        }

        // 9. Email
        const email = root.querySelector('#' + ids.email);
        const emailMsg = kkpValidateEmail(email?.value || '', true, { strict: true, required: ids.emailRequired });
        if (emailMsg) {
            errors.push(emailMsg);
            showFieldError(email, emailMsg);
        }

        // 10. Contact # (optional)
        const contact = root.querySelector('#' + ids.contact);
        const contactErr = kkpValidateContact(contact?.value || '', false);
        if (contactErr) {
            errors.push(contactErr);
            showFieldError(contact, contactErr);
        } else {
            // Ensure contact is in 09XXXXXXXXX format before submission
            if (contact && contact.value) {
                const normalized = contact.value.replace(/\D/g, '');
                if (normalized.length === 10 && normalized.startsWith('9')) {
                    contact.value = '0' + normalized;
                }
            }
        }

        // 11. Civil Status
        if (!hiddenVal(ids.civilStatus)) {
            errors.push('Civil Status is required.');
            demoBlockError(ids.civilStatus, 'Please select Civil Status.', root);
        }

        // 12. Youth Age Group
        if (!hiddenVal(ids.youthAgeGroup)) {
            errors.push('Youth Age Group is required.');
            demoBlockError(ids.youthAgeGroup, 'Please select Youth Age Group.', root);
        }

        // 13. Educational Background
        if (!hiddenVal(ids.education)) {
            errors.push('Educational Background is required.');
            demoBlockError(ids.education, 'Please select Educational Background.', root);
        }

        // 14. Youth Classification
        if (!hiddenVal(ids.youthClassification)) {
            errors.push('Youth Classification is required.');
            demoBlockError(ids.youthClassification, 'Please select Youth Classification.', root);
        }

        // 15. Work Status
        if (!hiddenVal(ids.workStatus)) {
            errors.push('Work Status is required.');
            demoBlockError(ids.workStatus, 'Please select Work Status.', root);
        }

        // 16. Registered SK Voter
        if (!hiddenVal(ids.skVoter)) {
            errors.push('Registered SK Voter is required.');
            demoBlockError(ids.skVoter, 'Please select Yes or No.', root);
        }

        // 17. Did you vote last SK
        if (!hiddenVal(ids.votingHistory)) {
            errors.push('Did you vote last SK is required.');
            demoBlockError(ids.votingHistory, 'Please select Yes or No.', root);
        }

        // 18. Registered National Voter
        if (!hiddenVal(ids.nationalVoter)) {
            errors.push('Registered National Voter is required.');
            demoBlockError(ids.nationalVoter, 'Please select Yes or No.', root);
        }

        // 19. KK Assembly (conditional follow-up)
        const kkAssemblyVal = hiddenVal(ids.kkAssembly);
        if (!kkAssemblyVal) {
            errors.push('Have you attended a KK Assembly is required.');
            demoBlockError(ids.kkAssembly, 'Please select Yes or No.', root);
        } else if (kkAssemblyVal === 'Yes' && !hiddenVal(ids.votingFrequency)) {
            errors.push('KK Assembly attendance count is required.');
            demoBlockError(ids.votingFrequency, 'Please select number of times attended.', root);
        } else if (kkAssemblyVal === 'No' && !hiddenVal(ids.votingReason)) {
            errors.push('KK Assembly reason is required.');
            demoBlockError(ids.votingReason, 'Please select a reason.', root);
        }

        // Scroll to first error if any
        if (errors.length > 0) {
            const firstErr = root.querySelector('.kkp-field-error, .kkp-demo-block-error, .kkp-section-error, .kkp-input-err');
            if (firstErr) {
                firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    }

    function showClearDataConfirmation(rootElement) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('kkpClearDataConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'kkpClearDataConfirmModal';
            modal.className = 'kkp-confirm-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.innerHTML = `
                <div class="kkp-confirm-modal-content">
                    <div class="kkp-confirm-modal-header">
                        <h3>Clear all data?</h3>
                        <button type="button" class="kkp-confirm-modal-close" aria-label="Close">&times;</button>
                    </div>
                    <div class="kkp-confirm-modal-body">
                        <p>Are you sure you want to clear all entered data? This action cannot be undone.</p>
                    </div>
                    <div class="kkp-confirm-modal-footer">
                        <button type="button" class="kkp-confirm-btn kkp-confirm-btn-cancel">Cancel</button>
                        <button type="button" class="kkp-confirm-btn kkp-confirm-btn-continue">Clear Data</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Add event listeners
            const closeBtn = modal.querySelector('.kkp-confirm-modal-close');
            const cancelBtn = modal.querySelector('.kkp-confirm-btn-cancel');
            const clearBtn = modal.querySelector('.kkp-confirm-btn-continue');

            const closeModal = () => {
                modal.style.display = 'none';
            };

            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);

            clearBtn.addEventListener('click', () => {
                const root = rootElement || document.getElementById('kkProfilingEditRoot') || document;
                clearKmEditFormData(root);
                closeModal();
            });

            // Close on escape key
            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeModal();
            });
        }

        // Show modal
        modal.style.display = 'flex';
    }

    function clearKmEditFormData(rootElement) {
        const root = rootElement || document.getElementById('kkProfilingEditRoot') || document;

        // Clear all text inputs
        root.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"]').forEach((input) => {
            if (!input.readOnly) {
                input.value = '';
            }
        });

        // Clear all selects
        root.querySelectorAll('select').forEach((select) => {
            if (!select.disabled) {
                select.selectedIndex = 0;
            }
        });

        // Clear all checkboxes
        root.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = false;
        });

        // Clear hidden fields
        root.querySelectorAll('input[type="hidden"]').forEach((hidden) => {
            hidden.value = '';
        });

        // Clear all validation errors
        root.querySelectorAll('.kkp-field-error').forEach((error) => error.remove());
        root.querySelectorAll('.kkp-input-err').forEach((input) => input.classList.remove('kkp-input-err'));

        // Reset confirmation states
        confirmedLongNames.clear();

        const ids = resolveKmFieldIds(root);

        // Trigger custom suffix hide
        const suffixSelect = root.querySelector('#' + ids.suffix);
        const customSuffixWrap = root.querySelector('#' + ids.customSuffixWrap);
        if (suffixSelect && customSuffixWrap) {
            suffixSelect.value = 'None';
            customSuffixWrap.classList.remove('show');
        }
    }

    window.initKmEditValidation = initKmEditValidation;
    window.validateKmEditForm = validateKmEditForm;
    window.initKabataanAddValidation = function (rootElement) {
        initKmEditValidation(rootElement);
    };
    window.validateKabataanAddForm = function (rootElement) {
        return validateKmEditForm(rootElement);
    };
    window.kkpValidateEmail = kkpValidateEmail;
    window.kkpValidateContact = kkpValidateContact;
    window.kkpSanitizeNameInput = kkpSanitizeNameInput;
})(window, document);
