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
            return (required && touched && strict) ? 'Email is required.' : null;
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

        if (!/^09\d{9}$/.test(v)) {
            return 'Please enter a valid Philippine mobile number.';
        }

        if (kkpIsObviouslyFakeContact(v)) {
            return 'Please enter your actual mobile number.';
        }

        return null;
    }

    function youthAgeGroupFromAge(age) {
        const n = parseInt(age, 10);
        if (n >= 15 && n <= 17) return 'Child Youth (15-17 yrs old)';
        if (n >= 18 && n <= 24) return 'Core Youth (18-24 yrs old)';
        if (n >= 25 && n <= 30) return 'Young Adult (25-30 yrs old)';
        return '';
    }

    function getFieldErrorHost(el) {
        if (!el) return null;
        return el.closest('.kkp-inline-pair')
            || el.closest('.kkp-name-col')
            || el.closest('.kkf-name-col')
            || el.closest('.kkp-loc-col')
            || el.closest('.kkf-loc-col')
            || el.parentNode;
    }

    function clearFieldError(el) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        el.classList.remove('kkp-input-err');
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        if (el.closest('.kkp-name-col') || el.closest('.kkf-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
    }

    function showFieldError(el, msg) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        el.classList.add('kkp-input-err');
        const err = document.createElement('span');
        err.className = 'kkp-field-error';
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

        block.querySelectorAll('.kkp-demo-block-error').forEach((node) => node.remove());
        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-demo-block-error';
        err.setAttribute('role', 'alert');
        err.textContent = message;
        block.appendChild(err);
    }

    function personalLeftError(message, root) {
        const context = root || document;
        const left = context.querySelector('.kkp-personal-left') || context.querySelector('.kkf-personal-left');
        if (!left) return;

        left.querySelectorAll('.kkp-section-error').forEach((node) => node.remove());
        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-section-error';
        err.textContent = message;
        left.appendChild(err);
    }

    function calculateAgeFromBirthday(dateStr) {
        if (!dateStr) return null;
        const bday = new Date(dateStr + 'T00:00:00');
        if (isNaN(bday.getTime())) return null;
        const now = new Date();
        let age = now.getFullYear() - bday.getFullYear();
        const monthDiff = now.getMonth() - bday.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < bday.getDate())) {
            age -= 1;
        }
        return { age, bday, now };
    }

    /**
     * Bind real-time input and change listeners for edit form
     */
    function initKmEditValidation(rootElement) {
        const root = rootElement || document.getElementById('kkProfilingEditRoot') || document;

        const lastNameInput = root.querySelector('#kkEditLastName');
        const firstNameInput = root.querySelector('#kkEditFirstName');
        const middleNameInput = root.querySelector('#kkEditMiddleName');
        const suffixSelect = root.querySelector('#kkEditSuffix');
        const customSuffixInput = root.querySelector('#kkEditCustomSuffix');
        const customSuffixWrap = root.querySelector('#kkEditCustomSuffixWrap');
        const purokInput = root.querySelector('#kkEditPurokZone');
        const ageSelect = root.querySelector('#kkEditAgeInput');
        const dobInput = root.querySelector('#kkEditDob');
        const emailInput = root.querySelector('#kkEditEmail');
        const contactInput = root.querySelector('#kkEditContactInput');

        // Name fields: sanitize and validate in real-time
        [
            { el: lastNameInput, fn: kkpValidateLastName },
            { el: firstNameInput, fn: kkpValidateFirstName },
            { el: middleNameInput, fn: kkpValidateMiddleName },
        ].forEach(({ el, fn }) => {
            if (!el || el.dataset.kmValBound === '1') return;
            el.dataset.kmValBound = '1';

            el.addEventListener('input', () => {
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

        // Purok validation
        if (purokInput && purokInput.dataset.kmValBound !== '1') {
            purokInput.dataset.kmValBound = '1';
            const validatePurok = () => {
                const v = (purokInput.value || '').trim();
                if (!v) showFieldError(purokInput, 'Purok/Zone is required.');
                else clearFieldError(purokInput);
            };
            purokInput.addEventListener('input', validatePurok);
            purokInput.addEventListener('change', validatePurok);
            purokInput.addEventListener('blur', validatePurok);
        }

        // Contact input: 09 prefix & real-time checking
        if (contactInput && contactInput.dataset.kmValBound !== '1') {
            contactInput.dataset.kmValBound = '1';
            if (!contactInput.value) contactInput.value = '09';

            contactInput.addEventListener('focus', function () {
                if (!this.value) this.value = '09';
            });

            const validateContact = () => {
                const msg = kkpValidateContact(contactInput.value, false);
                if (msg) showFieldError(contactInput, msg);
                else clearFieldError(contactInput);
            };

            contactInput.addEventListener('input', function () {
                let value = (this.value || '').replace(/\D/g, '');
                if (!value.startsWith('09')) {
                    value = value.startsWith('9') ? `0${value}` : `09${value.replace(/^0+/, '')}`;
                }
                this.value = value.slice(0, 11);
                validateContact();
            });

            contactInput.addEventListener('blur', validateContact);
        }

        // Email input
        if (emailInput && emailInput.dataset.kmValBound !== '1') {
            emailInput.dataset.kmValBound = '1';
            const validateEmail = (strict = false) => {
                const msg = kkpValidateEmail(emailInput.value, true, { strict });
                if (msg) showFieldError(emailInput, msg);
                else clearFieldError(emailInput);
            };
            emailInput.addEventListener('input', () => validateEmail(false));
            emailInput.addEventListener('blur', () => validateEmail(true));
        }

        // Birthday & Age synchronization
        if (dobInput && dobInput.dataset.kmValBound !== '1') {
            dobInput.dataset.kmValBound = '1';
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
                const youthAgeGroupEl = root.querySelector('#kkEditYouthAgeGroup');
                if (youthAgeGroupEl && typeof window.setCheckboxGroupByHiddenId === 'function') {
                    window.setCheckboxGroupByHiddenId('kkEditYouthAgeGroup', youthAgeGroupFromAge(age));
                }
            });
        }

        if (ageSelect && ageSelect.dataset.kmValBound !== '1') {
            ageSelect.dataset.kmValBound = '1';
            ageSelect.addEventListener('change', () => {
                const val = parseInt(ageSelect.value, 10);
                if (!val || val < 15 || val > 30) {
                    showFieldError(ageSelect, 'Age must be 15 to 30 only.');
                    return;
                }
                clearFieldError(ageSelect);
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
                const left = root.querySelector('.kkp-personal-left, .kkf-personal-left');
                if (left) left.querySelectorAll('.kkp-section-error').forEach((e) => e.remove());
            });
        });
    }

    /**
     * Comprehensive form validation before save
     */
    function validateKmEditForm(rootElement) {
        const root = rootElement || document.getElementById('kkProfilingEditRoot') || document;

        // Clear previous errors
        root.querySelectorAll('.kkp-field-error').forEach((el) => el.remove());
        root.querySelectorAll('.kkp-input-err').forEach((el) => el.classList.remove('kkp-input-err'));

        const errors = [];

        function hiddenVal(id) {
            const el = root.querySelector('#' + id);
            return el ? el.value.trim() : '';
        }

        // 1. Last Name
        const lastName = root.querySelector('#kkEditLastName');
        const lastNameMsg = kkpValidateLastName(lastName?.value, true);
        if (lastNameMsg) {
            errors.push(lastNameMsg);
            showFieldError(lastName, lastNameMsg);
        }

        // 2. First Name
        const firstName = root.querySelector('#kkEditFirstName');
        const firstNameMsg = kkpValidateFirstName(firstName?.value, true);
        if (firstNameMsg) {
            errors.push(firstNameMsg);
            showFieldError(firstName, firstNameMsg);
        }

        // 3. Middle Name
        const middleName = root.querySelector('#kkEditMiddleName');
        const middleNameMsg = kkpValidateMiddleName(middleName?.value, true);
        if (middleNameMsg) {
            errors.push(middleNameMsg);
            showFieldError(middleName, middleNameMsg);
        }

        // 4. Suffix
        const suffix = root.querySelector('#kkEditSuffix');
        const customSuffix = root.querySelector('#kkEditCustomSuffix');
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

        // 5. Purok/Zone
        const purok = root.querySelector('#kkEditPurokZone');
        if (!purok || !purok.value.trim()) {
            errors.push('Purok/Zone is required.');
            showFieldError(purok, 'Purok/Zone is required.');
        }

        // 6. Sex
        if (!hiddenVal('kkEditSex')) {
            errors.push('Sex Assigned by Birth is required.');
            personalLeftError('Please select Sex Assigned by Birth.', root);
        }

        // 7. Age
        const age = root.querySelector('#kkEditAgeInput');
        const ageVal = parseInt(age?.value, 10);
        if (!age || !age.value.trim() || isNaN(ageVal)) {
            errors.push('Age is required.');
            showFieldError(age, 'Age is required.');
        } else if (ageVal < 15 || ageVal > 30) {
            errors.push('Age must be 15 to 30 only.');
            showFieldError(age, 'Age must be 15 to 30 only.');
        }

        // 8. Birthday
        const birthday = root.querySelector('#kkEditDob');
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
        const email = root.querySelector('#kkEditEmail');
        if (email && email.value.trim()) {
            const emailMsg = kkpValidateEmail(email.value, true, { strict: true });
            if (emailMsg) {
                errors.push(emailMsg);
                showFieldError(email, emailMsg);
            }
        }

        // 10. Contact #
        const contact = root.querySelector('#kkEditContactInput');
        const contactErr = kkpValidateContact(contact?.value || '', true);
        if (contactErr) {
            errors.push(contactErr);
            showFieldError(contact, contactErr);
        }

        // 11. Civil Status
        if (!hiddenVal('kkEditCivilStatus')) {
            errors.push('Civil Status is required.');
            demoBlockError('kkEditCivilStatus', 'Please select Civil Status.', root);
        }

        // 12. Youth Age Group
        if (!hiddenVal('kkEditYouthAgeGroup')) {
            errors.push('Youth Age Group is required.');
            demoBlockError('kkEditYouthAgeGroup', 'Please select Youth Age Group.', root);
        }

        // 13. Educational Background
        if (!hiddenVal('kkEditEducationalBackground')) {
            errors.push('Educational Background is required.');
            demoBlockError('kkEditEducationalBackground', 'Please select Educational Background.', root);
        }

        // 14. Youth Classification
        if (!hiddenVal('kkEditYouthClassification')) {
            errors.push('Youth Classification is required.');
            demoBlockError('kkEditYouthClassification', 'Please select Youth Classification.', root);
        }

        // 15. Work Status
        if (!hiddenVal('kkEditWorkStatus')) {
            errors.push('Work Status is required.');
            demoBlockError('kkEditWorkStatus', 'Please select Work Status.', root);
        }

        // 16. Registered SK Voter
        if (!hiddenVal('kkEditRegisteredSKVoter')) {
            errors.push('Registered SK Voter is required.');
            demoBlockError('kkEditRegisteredSKVoter', 'Please select Yes or No.', root);
        }

        // 17. Did you vote last SK
        if (!hiddenVal('kkEditVotingHistory')) {
            errors.push('Did you vote last SK is required.');
            demoBlockError('kkEditVotingHistory', 'Please select Yes or No.', root);
        }

        // 18. Registered National Voter
        if (!hiddenVal('kkEditRegisteredNationalVoter')) {
            errors.push('Registered National Voter is required.');
            demoBlockError('kkEditRegisteredNationalVoter', 'Please select Yes or No.', root);
        }

        // 19. KK Assembly (conditional follow-up)
        const kkAssemblyVal = hiddenVal('kkEditAttendedKKAssembly');
        if (!kkAssemblyVal) {
            errors.push('Have you attended a KK Assembly is required.');
            demoBlockError('kkEditAttendedKKAssembly', 'Please select Yes or No.', root);
        } else if (kkAssemblyVal === 'Yes' && !hiddenVal('kkEditVotingFrequency')) {
            errors.push('KK Assembly attendance count is required.');
            demoBlockError('kkEditVotingFrequency', 'Please select number of times attended.', root);
        } else if (kkAssemblyVal === 'No' && !hiddenVal('kkEditVotingReason')) {
            errors.push('KK Assembly reason is required.');
            demoBlockError('kkEditVotingReason', 'Please select a reason.', root);
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

    window.initKmEditValidation = initKmEditValidation;
    window.validateKmEditForm = validateKmEditForm;
    window.kkpValidateEmail = kkpValidateEmail;
    window.kkpValidateContact = kkpValidateContact;
    window.kkpSanitizeNameInput = kkpSanitizeNameInput;
})(window, document);
