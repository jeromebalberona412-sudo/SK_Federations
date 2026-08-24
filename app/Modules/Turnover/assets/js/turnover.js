(function () {
    'use strict';

    const config = window.turnoverConfig || {};
    let currentStep = 1;
    const maxStep = 3;
    let registrationMode = 'manual';
    let batchState = {
        headers: [],
        parsedRows: [],
        validationErrors: [],
        summaryErrors: [],
        president: null,
        vicePresident: null,
        editDebounceTimer: null,
    };

    const BATCH_HEADER_ALIASES = {
        'first name': 'first_name',
        'middle name': 'middle_name',
        'middle name (optional)': 'middle_name',
        'last name': 'last_name',
        suffix: 'suffix',
        'suffix (none)': 'suffix',
        sex: 'sex',
        gender: 'sex',
        birthdate: 'date_of_birth',
        'birth date': 'date_of_birth',
        'date of birth': 'date_of_birth',
        'birthdate (mm/dd/yyyy)': 'date_of_birth',
        age: 'age',
        'contact number': 'contact_number',
        contact: 'contact_number',
        'federation position': 'federation_position',
        position: 'federation_position',
        barangay: 'barangay',
        'term start': 'term_start',
        'term start date': 'term_start',
        'term start date (mm/dd/yyyy)': 'term_start',
        'term end': 'term_end',
        'term end date': 'term_end',
        'term end date (mm/dd/yyyy)': 'term_end',
        email: 'email',
        'email address': 'email',
    };

    const BATCH_DATE_FIELDS = new Set(['date_of_birth', 'term_start', 'term_end']);
    const SUFFIX_VALUES = new Set(['NONE', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V']);

    const TURNOVER_BATCH_HEADERS = [
        'First Name',
        'Middle Name (optional)',
        'Last Name',
        'Suffix (None)',
        'Sex',
        'Birthdate (MM/DD/YYYY)',
        'Age',
        'Contact Number',
        'Federation Position',
        'Barangay',
        'Term Start Date (MM/DD/YYYY)',
        'Term End Date (MM/DD/YYYY)',
        'Email Address',
    ];

    const TURNOVER_BATCH_COLUMN_WIDTHS = [
        { wch: 14 },
        { wch: 22 },
        { wch: 14 },
        { wch: 14 },
        { wch: 10 },
        { wch: 22 },
        { wch: 8 },
        { wch: 16 },
        { wch: 21.25 },
        { wch: 17.625 },
        { wch: 22 },
        { wch: 32.625 },
        { wch: 35.75 },
    ];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getXlsxLib() {
        return window.XLSX || null;
    }

    function downloadTurnoverBatchTemplate(event) {
        if (event) event.preventDefault();

        if (config.batchTemplateUrl) {
            window.location.href = config.batchTemplateUrl;
            return;
        }

        const XLSX = getXlsxLib();
        if (!XLSX) {
            showToast('Excel library failed to load. Please refresh the page.', 'error');
            return;
        }

        const rows = [TURNOVER_BATCH_HEADERS];
        const worksheet = XLSX.utils.aoa_to_sheet(rows);
        worksheet['!cols'] = TURNOVER_BATCH_COLUMN_WIDTHS;
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Turnover');
        XLSX.writeFile(workbook, 'turnover-officers-batch-template.xlsx');
    }

    function showToast(message, type) {
        const toast = document.getElementById('turnoverToast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = 'turnover-toast turnover-toast--' + (type || 'success');
        toast.hidden = false;
        setTimeout(function () { toast.hidden = true; }, 4000);
    }

    function getCsrfToken() {
        return config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function addYears(dateStr, years) {
        if (!dateStr) return '';
        const parts = dateStr.split('-').map(Number);
        if (parts.length !== 3) return '';
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        d.setFullYear(d.getFullYear() + years);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function clearFieldError(prefix, field) {
        const input = document.querySelector('[name="' + prefix + '[' + field + ']"]');
        const errorEl = document.querySelector('[data-error-for="' + prefix + '_' + field + '"]');
        if (input) input.classList.remove('to-invalid');
        if (errorEl) errorEl.textContent = '';
    }

    function setFieldError(prefix, field, message) {
        const input = document.querySelector('[name="' + prefix + '[' + field + ']"]');
        const errorEl = document.querySelector('[data-error-for="' + prefix + '_' + field + '"]');
        if (input) input.classList.add('to-invalid');
        if (errorEl) errorEl.textContent = message;
    }

    function clearFieldErrors(stepEl) {
        stepEl?.querySelectorAll('.to-field-error').forEach(function (el) { el.textContent = ''; });
        stepEl?.querySelectorAll('.to-field.to-invalid, .to-form-input.to-invalid').forEach(function (el) {
            el.classList.remove('to-invalid');
        });
    }

    function stripSpaces(value) {
        return (value || '').toUpperCase().replace(/\s+/g, '');
    }

    function calcAgeFromDob(dob) {
        if (!dob) return null;
        const birth = new Date(dob + 'T00:00:00');
        if (Number.isNaN(birth.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        return age;
    }

    function getValuesFromStep(stepEl) {
        const prefix = stepEl.dataset.prefix;
        const getVal = function (field) {
            const el = stepEl.querySelector('[name="' + prefix + '[' + field + ']"]');
            return el ? (el.value || '').trim() : '';
        };

        return {
            prefix: prefix,
            first_name: getVal('first_name'),
            middle_name: getVal('middle_name'),
            last_name: getVal('last_name'),
            suffix: getVal('suffix'),
            suffix_other: getVal('suffix_other'),
            sex: getVal('sex'),
            date_of_birth: getVal('date_of_birth'),
            age: getVal('age'),
            email: getVal('email'),
            contact_number: getVal('contact_number'),
            barangay_id: getVal('barangay_id'),
            term_start: getVal('term_start'),
            term_end: getVal('term_end'),
        };
    }

    function normalizeOfficerValues(raw) {
        const values = Object.assign({}, raw);
        values.first_name = (values.first_name || '').toUpperCase();
        values.middle_name = values.middle_name ? stripSpaces(values.middle_name) : '';
        values.last_name = stripSpaces(values.last_name || '');
        values.email = (values.email || '').toLowerCase();
        let contact = (values.contact_number || '').replace(/\D/g, '');
        if (contact && !contact.startsWith('09')) contact = '09' + contact.replace(/^0+/, '');
        values.contact_number = contact.slice(0, 11);
        if (values.sex) {
            const sex = String(values.sex).toLowerCase();
            if (sex === 'm' || sex === 'male') values.sex = 'Male';
            if (sex === 'f' || sex === 'female') values.sex = 'Female';
        }
        return values;
    }

    function validateOfficerField(field, values) {
        const v = normalizeOfficerValues(values);

        switch (field) {
            case 'first_name': {
                const name = v.first_name;
                if (!name) return 'First name is required.';
                if (name.length < 3 || name.length > 50) return 'First name: 3–50 characters.';
                if (!/^(?!\s)[A-Z.\-]+(?: [A-Z.\-]+)?$/i.test(name)) {
                    return 'First name: uppercase, at most one space.';
                }
                return null;
            }
            case 'middle_name': {
                if (!v.middle_name) return null;
                if (v.middle_name.length < 3 || v.middle_name.length > 50) return 'Middle name: 3–50 characters.';
                if (/\s/.test(v.middle_name) || !/^[A-Z.\-']+$/i.test(v.middle_name)) {
                    return 'Middle name: uppercase, no spaces.';
                }
                return null;
            }
            case 'last_name': {
                const last = v.last_name;
                if (!last) return 'Last name is required.';
                if (last.length < 3 || last.length > 50) return 'Last name: 3–50 characters.';
                if (/\s/.test(values.last_name || '') || !/^[A-Z.\-']+$/i.test(last)) {
                    return 'Last name: uppercase, no spaces.';
                }
                return null;
            }
            case 'suffix':
                if (!v.suffix) return 'Please select a suffix.';
                return null;
            case 'suffix_other':
                if (v.suffix !== '__other__') return null;
                if (!v.suffix_other || v.suffix_other.length < 1 || v.suffix_other.length > 10 || /\s/.test(v.suffix_other)) {
                    return 'Other suffix: 1–10 chars, no spaces.';
                }
                return null;
            case 'sex':
                if (!v.sex) return 'Please select sex.';
                return null;
            case 'date_of_birth': {
                if (!v.date_of_birth) return 'Date of birth is required.';
                if (v.date_of_birth < config.dobMin || v.date_of_birth > config.dobMax) {
                    return 'Birthdate must result in age 18–24 only.';
                }
                const age = calcAgeFromDob(v.date_of_birth);
                if (age === null || age < 18 || age > 24) return 'Officer must be 18–24 years old.';
                return null;
            }
            case 'age': {
                const age = calcAgeFromDob(v.date_of_birth);
                if (v.date_of_birth && (age === null || age < 18 || age > 24)) {
                    return 'Age must be 18–24 and match date of birth.';
                }
                return null;
            }
            case 'email': {
                const email = v.email;
                const emailMatch = email.match(/^([a-z0-9._%+-]+)@gmail\.com$/i);
                if (!email) return 'Email is required.';
                if (!emailMatch) return 'Use @gmail.com with 6–30 characters before @.';
                if (emailMatch[1].length < 6 || emailMatch[1].length > 30) {
                    return 'Email local part must be 6–30 characters.';
                }
                return null;
            }
            case 'contact_number':
                if (!/^09\d{9}$/.test(v.contact_number)) {
                    return 'Contact must be 11 digits starting with 09.';
                }
                return null;
            case 'barangay_id':
                if (!v.barangay_id) return 'Please select a barangay.';
                return null;
            case 'term_start': {
                if (!v.term_start) return 'Term start is required.';
                if (v.term_start < config.today) return 'Term start cannot be in the past.';
                return null;
            }
            case 'term_end': {
                const expectedEnd = addYears(v.term_start, 4);
                if (!v.term_end || v.term_end !== expectedEnd) {
                    return 'Term end must be exactly 4 years after term start.';
                }
                return null;
            }
            default:
                return null;
        }
    }

    function validateOfficerValues(values) {
        const fields = [
            'first_name', 'middle_name', 'last_name', 'suffix', 'suffix_other', 'sex',
            'date_of_birth', 'age', 'email', 'contact_number', 'barangay_id', 'term_start', 'term_end',
        ];
        const errors = [];
        fields.forEach(function (field) {
            const message = validateOfficerField(field, values);
            if (message) errors.push({ field: field, message: message });
        });
        return errors;
    }

    function applyFieldValidation(prefix, field, stepEl) {
        const values = getValuesFromStep(stepEl);
        const message = validateOfficerField(field, values);
        if (message) {
            setFieldError(prefix, field, message);
        } else {
            clearFieldError(prefix, field);
        }
        if (field === 'date_of_birth') {
            const ageMessage = validateOfficerField('age', values);
            if (ageMessage) setFieldError(prefix, 'age', ageMessage);
            else clearFieldError(prefix, 'age');
        }
        if (field === 'term_start') {
            const endMessage = validateOfficerField('term_end', values);
            if (endMessage) setFieldError(prefix, 'term_end', endMessage);
            else clearFieldError(prefix, 'term_end');
        }
    }

    function syncTermEnd(stepEl) {
        const startInput = stepEl.querySelector('.to-term-start');
        const endInput = stepEl.querySelector('.to-term-end');
        if (!startInput || !endInput) return;

        const expected = addYears(startInput.value || config.today, 4);
        endInput.value = expected;
        endInput.min = expected;
        endInput.max = expected;
    }

    function validateStep(step) {
        const stepEl = document.querySelector('.turnover-form-step[data-step="' + step + '"]');
        if (!stepEl || step === 3) return true;

        clearFieldErrors(stepEl);
        const values = getValuesFromStep(stepEl);
        const errors = validateOfficerValues(values);
        errors.forEach(function (err) {
            setFieldError(values.prefix, err.field, err.message);
        });
        return errors.length === 0;
    }

    function showStep(step) {
        currentStep = step;
        document.querySelectorAll('.turnover-form-step').forEach(function (el) {
            el.hidden = Number(el.dataset.step) !== step;
        });
        document.querySelectorAll('.turnover-wizard-tab').forEach(function (tab) {
            tab.classList.toggle('active', Number(tab.dataset.step) === step);
        });
        const prevBtn = document.getElementById('turnoverPrevBtn');
        const nextBtn = document.getElementById('turnoverNextBtn');
        const submitBtn = document.getElementById('turnoverSubmitBtn');
        if (prevBtn) prevBtn.hidden = step === 1;
        if (nextBtn) nextBtn.hidden = step === maxStep;
        if (submitBtn) submitBtn.hidden = step !== maxStep;
        if (step === maxStep) buildReviewSummary();
    }

    function buildReviewSummary() {
        const form = document.getElementById('turnoverRegistrationForm');
        const summary = document.getElementById('turnoverReviewSummary');
        if (!form || !summary) return;
        const data = new FormData(form);
        const sections = [
            { key: 'president', label: 'Federation President' },
            { key: 'vice_president', label: 'Federation Vice President' },
        ];
        summary.innerHTML = sections.map(function (section) {
            const prefix = section.key;
            const name = [data.get(prefix + '[first_name]'), data.get(prefix + '[middle_name]'), data.get(prefix + '[last_name]')].filter(Boolean).join(' ');
            return '<div class="turnover-review-block"><h4>' + section.label + '</h4><p><strong>' + name + '</strong></p><p>' + (data.get(prefix + '[email]') || '') + '</p><p>' + (data.get(prefix + '[contact_number]') || '') + '</p><p>Term: ' + (data.get(prefix + '[term_start]') || '') + ' → ' + (data.get(prefix + '[term_end]') || '') + '</p></div>';
        }).join('');
    }

    function initDateFields(stepEl) {
        stepEl.querySelectorAll('.to-dob').forEach(function (dobInput) {
            dobInput.min = config.dobMin;
            dobInput.max = config.dobMax;
            dobInput.addEventListener('change', function () {
                const prefix = stepEl.dataset.prefix;
                const ageInput = stepEl.querySelector('.to-age');
                const age = calcAgeFromDob(dobInput.value);
                if (ageInput) {
                    ageInput.value = age !== null && age >= 18 && age <= 24 ? String(age) : '';
                }
                applyFieldValidation(prefix, 'date_of_birth', stepEl);
            });
        });

        stepEl.querySelectorAll('.to-term-start').forEach(function (startInput) {
            startInput.min = config.today;
            startInput.addEventListener('change', function () {
                if (startInput.value && startInput.value < config.today) {
                    startInput.value = config.today;
                }
                syncTermEnd(stepEl);
                applyFieldValidation(stepEl.dataset.prefix, 'term_start', stepEl);
            });
            syncTermEnd(stepEl);
        });
    }

    function bindRealtimeValidation(stepEl) {
        const prefix = stepEl.dataset.prefix;
        if (!prefix) return;

        const fields = stepEl.querySelectorAll('.to-field');
        fields.forEach(function (input) {
            const field = input.dataset.field;
            if (!field) return;

            const run = function () {
                if (input.classList.contains('input-uppercase') && field !== 'email') {
                    if (field === 'last_name' || field === 'middle_name') {
                        input.value = stripSpaces(input.value);
                    } else if (field === 'first_name') {
                        input.value = input.value.toUpperCase();
                    } else if (field === 'suffix_other') {
                        input.value = input.value.toUpperCase();
                    }
                }
                applyFieldValidation(prefix, field, stepEl);
            };

            input.addEventListener('input', run);
            input.addEventListener('change', run);
            input.addEventListener('blur', run);
        });
    }

    function bindFieldBehaviors() {
        document.querySelectorAll('.turnover-form-step').forEach(function (stepEl) {
            const prefix = stepEl.dataset.prefix;
            if (!prefix) return;

            initDateFields(stepEl);
            bindRealtimeValidation(stepEl);

            stepEl.querySelectorAll('.to-contact').forEach(function (input) {
                input.addEventListener('input', function () {
                    let digits = input.value.replace(/\D/g, '');
                    if (!digits.startsWith('09')) digits = '09' + digits.replace(/^0+/, '');
                    input.value = digits.slice(0, 11);
                });
            });

            const suffixSelect = stepEl.querySelector('[name="' + prefix + '[suffix]"]');
            if (suffixSelect) {
                suffixSelect.addEventListener('change', function () {
                    const otherGroup = stepEl.querySelector('.turnover-suffix-other');
                    if (otherGroup) otherGroup.hidden = suffixSelect.value !== '__other__';
                    applyFieldValidation(prefix, 'suffix', stepEl);
                    applyFieldValidation(prefix, 'suffix_other', stepEl);
                });
            }
        });
    }

    function bindRegistrationMode() {
        const tabs = document.querySelectorAll('.to-reg-mode-tab');
        const manualPane = document.getElementById('turnoverManualPane');
        const batchPanel = document.getElementById('turnoverBatchPanel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                registrationMode = tab.dataset.mode || 'manual';
                tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
                if (manualPane) manualPane.hidden = registrationMode !== 'manual';
                if (batchPanel) batchPanel.hidden = registrationMode !== 'batch';
            });
        });
    }

    function normalizeBatchHeader(header) {
        return String(header || '').trim().toLowerCase();
    }

    function mapBatchHeader(header) {
        const normalized = normalizeBatchHeader(header);
        return BATCH_HEADER_ALIASES[normalized] || null;
    }

    function isRequirementsRow(row) {
        const cells = (row || []).map(function (cell) { return String(cell || '').trim().toLowerCase(); }).filter(Boolean);
        if (cells.length === 0) return false;
        return cells.every(function (cell) { return cell === 'required' || cell === 'optional'; });
    }

    function ensureParsedRowWidth(row) {
        const width = batchState.headers.length;
        return Array.from({ length: width }, function (_v, index) {
            return String((row || [])[index] ?? '').trim();
        });
    }

    function mapRowToOfficer(rowArray) {
        const rowObj = {};
        batchState.headers.forEach(function (header, colIndex) {
            const key = mapBatchHeader(header);
            if (key) rowObj[key] = rowArray[colIndex];
        });
        return rowObj;
    }

    function getBatchRowErrors(errors, rowNumber) {
        return (errors || []).filter(function (item) { return Number(item.row) === rowNumber; });
    }

    function getBatchInvalidFields(errors, rowNumber) {
        return new Set(getBatchRowErrors(errors, rowNumber).map(function (item) { return item.field; }).filter(Boolean));
    }

    function displayBatchCellValue(fieldKey, rawValue) {
        if (!fieldKey || !BATCH_DATE_FIELDS.has(fieldKey)) {
            return String(rawValue ?? '').trim();
        }
        const normalized = parseBatchDate(rawValue, fieldKey);
        if (!normalized) return '';
        const slash = /^(\d{4})-(\d{2})-(\d{2})$/.exec(normalized);
        if (slash) {
            return slash[2] + '/' + slash[3] + '/' + slash[1];
        }
        return normalized;
    }

    function batchExcelSerialToDate(serial) {
        const utcDays = Math.floor(Number(serial) - 25569);
        const date = new Date(utcDays * 86400 * 1000);
        const y = date.getUTCFullYear();
        const m = String(date.getUTCMonth() + 1).padStart(2, '0');
        const d = String(date.getUTCDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function parseBatchDate(value, fieldKey) {
        if (value === null || value === undefined || value === '') return '';
        if (value instanceof Date) {
            const y = value.getFullYear();
            const m = String(value.getMonth() + 1).padStart(2, '0');
            const d = String(value.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }
        if (typeof value === 'number' && BATCH_DATE_FIELDS.has(fieldKey)) {
            return batchExcelSerialToDate(value);
        }
        const raw = String(value).trim();
        const slash = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(raw);
        if (slash) {
            return slash[3] + '-' + slash[1].padStart(2, '0') + '-' + slash[2].padStart(2, '0');
        }
        return raw;
    }

    function resolveBarangayId(name) {
        const target = String(name || '').trim().toLowerCase();
        if (!target) return '';
        const match = (config.barangays || []).find(function (b) {
            return String(b.name || '').trim().toLowerCase() === target;
        });
        return match ? String(match.id) : '';
    }

    function normalizeSuffixValue(value) {
        const raw = String(value || '').trim();
        if (!raw || raw.toLowerCase() === 'none') return 'NONE';
        const upper = raw.toUpperCase();
        if (upper === 'JR' || upper === 'JR.') return 'Jr.';
        if (upper === 'SR' || upper === 'SR.') return 'Sr.';
        if (SUFFIX_VALUES.has(raw)) return raw;
        if (['II', 'III', 'IV', 'V'].includes(upper)) return upper;
        return '__other__';
    }

    function normalizeBatchRow(row) {
        const normalized = {};
        Object.keys(row).forEach(function (key) {
            let value = row[key];
            if (BATCH_DATE_FIELDS.has(key)) {
                value = parseBatchDate(value, key);
            } else {
                value = value === null || value === undefined ? '' : String(value).trim();
            }
            normalized[key] = value;
        });

        normalized.first_name = (normalized.first_name || '').toUpperCase();
        normalized.middle_name = normalized.middle_name ? stripSpaces(normalized.middle_name) : '';
        normalized.last_name = stripSpaces(normalized.last_name || '');
        normalized.email = (normalized.email || '').toLowerCase();

        if (normalized.sex) {
            const sex = normalized.sex.toLowerCase();
            if (sex === 'm' || sex === 'male') normalized.sex = 'Male';
            if (sex === 'f' || sex === 'female') normalized.sex = 'Female';
        }

        let contact = (normalized.contact_number || '').replace(/\D/g, '');
        if (contact && !contact.startsWith('09')) contact = '09' + contact.replace(/^0+/, '');
        normalized.contact_number = contact.slice(0, 11);

        const suffixRaw = normalized.suffix || '';
        const suffixNorm = normalizeSuffixValue(suffixRaw);
        if (suffixNorm === '__other__' && suffixRaw && !SUFFIX_VALUES.has(suffixRaw)) {
            normalized.suffix = '__other__';
            normalized.suffix_other = suffixRaw.toUpperCase();
        } else {
            normalized.suffix = suffixNorm;
            normalized.suffix_other = '';
        }

        normalized.barangay_id = resolveBarangayId(normalized.barangay);
        const age = calcAgeFromDob(normalized.date_of_birth);
        normalized.age = age !== null ? String(age) : (normalized.age || '');
        normalized.term_end = addYears(normalized.term_start, 4);

        const pos = String(normalized.federation_position || '').trim().toLowerCase();
        if (pos === 'president' || pos === 'federation president') {
            normalized.federation_position = 'president';
        } else if (pos === 'vice president' || pos === 'vice-president' || pos === 'vice preident' || pos === 'federation vice president') {
            normalized.federation_position = 'vice_president';
        }

        return normalized;
    }

    function validateBatchRow(row, rowNumber) {
        const data = normalizeBatchRow(row);
        const fieldErrors = [];

        if (!data.federation_position) {
            fieldErrors.push({ row: rowNumber, field: 'federation_position', error: 'Federation Position must be President or Vice President.' });
        }

        if (!data.barangay_id) {
            fieldErrors.push({
                row: rowNumber,
                field: 'barangay',
                error: data.barangay ? 'Barangay "' + data.barangay + '" not found.' : 'Barangay is required.',
            });
        }

        const officerErrors = validateOfficerValues({
            prefix: 'batch',
            first_name: data.first_name,
            middle_name: data.middle_name,
            last_name: data.last_name,
            suffix: data.suffix,
            suffix_other: data.suffix_other,
            sex: data.sex,
            date_of_birth: data.date_of_birth,
            age: data.age,
            email: data.email,
            contact_number: data.contact_number,
            barangay_id: data.barangay_id,
            term_start: data.term_start,
            term_end: data.term_end,
        });

        officerErrors.forEach(function (err) {
            fieldErrors.push({ row: rowNumber, field: err.field, error: err.message });
        });

        return { data: data, fieldErrors: fieldErrors };
    }

    function revalidateTurnoverBatchState() {
        const fieldErrors = [];
        const summaryErrors = [];
        const validated = batchState.parsedRows.map(function (rowArray, index) {
            return validateBatchRow(mapRowToOfficer(rowArray), index + 1);
        });

        validated.forEach(function (item) {
            fieldErrors.push.apply(fieldErrors, item.fieldErrors);
        });

        const president = validated.find(function (v) { return v.data.federation_position === 'president'; });
        const vice = validated.find(function (v) { return v.data.federation_position === 'vice_president'; });

        if (batchState.parsedRows.length > (config.batchMaxRows || 2)) {
            summaryErrors.push('Maximum ' + (config.batchMaxRows || 2) + ' officers allowed (President and Vice President).');
        } else if (batchState.parsedRows.length === (config.batchMaxRows || 2)) {
            if (!president) summaryErrors.push('One row must have Federation Position = President.');
            if (!vice) summaryErrors.push('One row must have Federation Position = Vice President.');
        } else if (batchState.parsedRows.length > 0) {
            summaryErrors.push('Upload exactly 2 rows: one President and one Vice President.');
        }

        if (president && vice && president.data.email && president.data.email === vice.data.email) {
            summaryErrors.push('President and Vice President must use different email addresses.');
            const viceRowIndex = validated.findIndex(function (v) { return v.data.federation_position === 'vice_president'; });
            fieldErrors.push({ row: viceRowIndex + 1, field: 'email', error: 'Must be different from President email.' });
        }

        batchState.validationErrors = fieldErrors;
        batchState.summaryErrors = summaryErrors;
        batchState.president = president ? president.data : null;
        batchState.vicePresident = vice ? vice.data : null;

        const hasErrors = fieldErrors.length > 0 || summaryErrors.length > 0;
        let message = batchState.parsedRows.length + ' row(s) ready for review.';
        if (hasErrors) {
            message = (fieldErrors.length + summaryErrors.length) + ' validation issue(s) found. Edit the highlighted cells below, then submit again.';
        }

        renderBatchEditablePreview(message, hasErrors);

        const submitBtn = document.getElementById('turnoverBatchSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = hasErrors || !batchState.president || !batchState.vicePresident || batchState.parsedRows.length === 0;
        }

        return fieldErrors;
    }

    function syncBatchAgeFromBirthdate(rowIndex) {
        const birthCol = batchState.headers.findIndex(function (h) { return mapBatchHeader(h) === 'date_of_birth'; });
        const ageCol = batchState.headers.findIndex(function (h) { return mapBatchHeader(h) === 'age'; });
        if (birthCol < 0 || ageCol < 0 || !batchState.parsedRows[rowIndex]) return;

        batchState.parsedRows[rowIndex] = ensureParsedRowWidth(batchState.parsedRows[rowIndex]);
        const birthValue = batchState.parsedRows[rowIndex][birthCol];
        const normalizedBirth = parseBatchDate(birthValue, 'date_of_birth');
        const age = calcAgeFromDob(normalizedBirth);
        batchState.parsedRows[rowIndex][ageCol] = age !== null && age >= 18 && age <= 24 ? String(age) : '';
    }

    function syncBatchTermEnd(rowIndex) {
        const startCol = batchState.headers.findIndex(function (h) { return mapBatchHeader(h) === 'term_start'; });
        const endCol = batchState.headers.findIndex(function (h) { return mapBatchHeader(h) === 'term_end'; });
        if (startCol < 0 || endCol < 0 || !batchState.parsedRows[rowIndex]) return;

        batchState.parsedRows[rowIndex] = ensureParsedRowWidth(batchState.parsedRows[rowIndex]);
        const startVal = parseBatchDate(batchState.parsedRows[rowIndex][startCol], 'term_start');
        batchState.parsedRows[rowIndex][endCol] = displayBatchCellValue('term_end', addYears(startVal, 4));
    }

    function handleBatchCellEdit(rowIndex, colIndex, value) {
        if (!batchState.parsedRows[rowIndex]) return;

        batchState.parsedRows[rowIndex] = ensureParsedRowWidth(batchState.parsedRows[rowIndex]);
        batchState.parsedRows[rowIndex][colIndex] = value;

        const fieldKey = mapBatchHeader(batchState.headers[colIndex] || '');
        if (fieldKey === 'date_of_birth') {
            syncBatchAgeFromBirthdate(rowIndex);
        }
        if (fieldKey === 'term_start') {
            syncBatchTermEnd(rowIndex);
        }

        window.clearTimeout(batchState.editDebounceTimer);
        batchState.editDebounceTimer = window.setTimeout(function () {
            revalidateTurnoverBatchState();
        }, 250);
    }

    function renderBatchEditablePreview(message, hasErrors) {
        const preview = document.getElementById('turnoverBatchPreview');
        const errorsEl = document.getElementById('turnoverBatchErrors');
        if (!preview) return;

        if (batchState.parsedRows.length === 0) {
            preview.hidden = !message;
            if (!message) {
                preview.innerHTML = '';
                if (errorsEl) {
                    errorsEl.hidden = true;
                    errorsEl.innerHTML = '';
                }
                return;
            }

            const messageClass = hasErrors ? 'to-batch-row-count to-batch-row-count-error' : 'to-batch-row-count';
            let summaryHtml = '';
            if (batchState.summaryErrors.length > 0) {
                summaryHtml = '<ul class="to-batch-summary-errors">' + batchState.summaryErrors.map(function (err) {
                    return '<li>' + escapeHtml(err) + '</li>';
                }).join('') + '</ul>';
            }

            preview.innerHTML =
                '<p class="' + messageClass + '">' + escapeHtml(message || '') + '</p>' +
                summaryHtml;

            if (errorsEl) {
                errorsEl.hidden = true;
                errorsEl.innerHTML = '';
            }
            return;
        }

        preview.hidden = false;
        const headers = batchState.headers;
        const errors = batchState.validationErrors || [];
        const theadCells = '<th class="to-batch-col-rownum">#</th>' +
            headers.map(function (header) { return '<th>' + escapeHtml(header) + '</th>'; }).join('') +
            '<th class="to-batch-col-status">Status</th>';

        const tbodyRows = batchState.parsedRows.map(function (row, rowIndex) {
            const rowNumber = rowIndex + 1;
            const rowErrors = getBatchRowErrors(errors, rowNumber);
            const invalidFields = getBatchInvalidFields(errors, rowNumber);
            const rowClass = rowErrors.length > 0 ? ' to-batch-row-has-error' : '';

            const cells = headers.map(function (header, colIndex) {
                const fieldKey = mapBatchHeader(header);
                const rawValue = row[colIndex] ?? '';
                const displayValue = displayBatchCellValue(fieldKey, rawValue);
                const invalidClass = invalidFields.has(fieldKey) ? ' to-batch-cell-invalid' : '';
                const placeholder = fieldKey && BATCH_DATE_FIELDS.has(fieldKey) ? 'MM/DD/YYYY' : '';

                return '<td><input type="text" class="to-batch-cell-input' + invalidClass + '" data-row-index="' + rowIndex + '" data-col-index="' + colIndex + '" data-field-key="' + escapeHtml(fieldKey || '') + '" value="' + escapeHtml(displayValue) + '" placeholder="' + escapeHtml(placeholder) + '" aria-label="' + escapeHtml(header) + ' row ' + rowNumber + '"></td>';
            }).join('');

            const statusText = rowErrors.length > 0
                ? escapeHtml(rowErrors[0].error || 'Fix this row')
                : '<span class="to-batch-row-status-ok">OK</span>';

            return '<tr class="to-batch-editable-row' + rowClass + '" data-row-number="' + rowNumber + '">' +
                '<td class="to-batch-col-rownum">' + rowNumber + '</td>' +
                cells +
                '<td class="to-batch-col-status">' + statusText + '</td>' +
                '</tr>';
        }).join('');

        const messageClass = hasErrors ? 'to-batch-row-count to-batch-row-count-error' : 'to-batch-row-count';
        let summaryHtml = '';
        if (batchState.summaryErrors.length > 0) {
            summaryHtml = '<ul class="to-batch-summary-errors">' + batchState.summaryErrors.map(function (err) {
                return '<li>' + escapeHtml(err) + '</li>';
            }).join('') + '</ul>';
        }

        preview.innerHTML =
            '<p class="' + messageClass + '">' + escapeHtml(message || '') + '</p>' +
            summaryHtml +
            '<p class="to-batch-edit-hint">You can edit values directly in the table below. Changes are validated automatically.</p>' +
            '<div class="to-batch-preview-wrap"><table class="to-batch-preview-table to-batch-preview-table-editable"><thead><tr>' + theadCells + '</tr></thead><tbody>' + tbodyRows + '</tbody></table></div>';

        preview.querySelectorAll('.to-batch-cell-input').forEach(function (input) {
            input.addEventListener('input', function () {
                handleBatchCellEdit(Number(input.dataset.rowIndex), Number(input.dataset.colIndex), input.value);
            });
            input.addEventListener('change', function () {
                handleBatchCellEdit(Number(input.dataset.rowIndex), Number(input.dataset.colIndex), input.value);
            });
        });

        if (errorsEl) {
            errorsEl.hidden = true;
            errorsEl.innerHTML = '';
        }
    }

    function parseBatchWorkbook(workbook) {
        const sheetName = workbook.SheetNames[0];
        const sheet = workbook.Sheets[sheetName];
        const matrix = window.XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false, cellDates: true });
        if (!matrix.length) {
            batchState.headers = [];
            batchState.parsedRows = [];
            renderBatchEditablePreview('The file is empty.', true);
            return;
        }

        const headers = matrix[0].map(function (h) { return String(h || '').trim(); });
        if (!headers.some(function (h) { return h !== ''; })) {
            batchState.headers = [];
            batchState.parsedRows = [];
            renderBatchEditablePreview('The file has no column headers. Use the downloaded template.', true);
            return;
        }

        const fieldKeys = headers.map(mapBatchHeader);
        const unknown = headers.filter(function (h, i) { return h && !fieldKeys[i]; });
        if (unknown.length) {
            batchState.headers = [];
            batchState.parsedRows = [];
            renderBatchEditablePreview('Unknown column(s): ' + unknown.join(', '), true);
            return;
        }

        batchState.headers = headers;
        batchState.parsedRows = [];

        for (let i = 1; i < matrix.length; i++) {
            const cells = matrix[i];
            const hasData = cells.some(function (c) { return String(c || '').trim() !== ''; });
            if (!hasData) continue;
            if (isRequirementsRow(cells)) continue;

            batchState.parsedRows.push(ensureParsedRowWidth(cells));
        }

        if (batchState.parsedRows.length === 0) {
            renderBatchEditablePreview('No officer rows found. Add exactly 2 rows (President and Vice President).', true);
            return;
        }

        revalidateTurnoverBatchState();
    }

    function buildBatchFormData() {
        const formData = new FormData();
        const token = document.querySelector('#turnoverRegistrationForm input[name="_token"]');
        if (token) formData.append('_token', token.value);

        function appendOfficer(prefix, row) {
            formData.append(prefix + '[first_name]', row.first_name);
            formData.append(prefix + '[middle_name]', row.middle_name || '');
            formData.append(prefix + '[last_name]', row.last_name);
            formData.append(prefix + '[suffix]', row.suffix);
            if (row.suffix === '__other__') {
                formData.append(prefix + '[suffix_other]', row.suffix_other || '');
            }
            formData.append(prefix + '[sex]', row.sex);
            formData.append(prefix + '[date_of_birth]', row.date_of_birth);
            formData.append(prefix + '[age]', row.age);
            formData.append(prefix + '[email]', row.email);
            formData.append(prefix + '[contact_number]', row.contact_number);
            formData.append(prefix + '[barangay_id]', row.barangay_id);
            formData.append(prefix + '[term_start]', row.term_start);
            formData.append(prefix + '[term_end]', row.term_end);
        }

        appendOfficer('president', batchState.president);
        appendOfficer('vice_president', batchState.vicePresident);
        return formData;
    }

    function bindBatchUpload() {
        const fileInput = document.getElementById('turnoverBatchFileInput');
        const dropzone = document.getElementById('turnoverBatchDropzone');
        const fileNameEl = document.getElementById('turnoverBatchFileName');
        const submitBtn = document.getElementById('turnoverBatchSubmitBtn');
        const templateLink = document.getElementById('turnoverBatchTemplateLink');

        if (!fileInput || !dropzone) return;

        templateLink?.addEventListener('click', downloadTurnoverBatchTemplate);

        function handleFile(file) {
            if (!file) return;
            if (!window.XLSX) {
                showToast('Excel library failed to load. Please refresh the page.', 'error');
                return;
            }
            fileNameEl.textContent = file.name;
            batchState.headers = [];
            batchState.parsedRows = [];
            batchState.validationErrors = [];
            batchState.summaryErrors = [];
            batchState.president = null;
            batchState.vicePresident = null;

            const reader = new FileReader();
            reader.onload = function (event) {
                try {
                    const data = new Uint8Array(event.target.result);
                    const workbook = window.XLSX.read(data, { type: 'array', raw: false, cellDates: true });
                    parseBatchWorkbook(workbook);
                } catch (err) {
                    batchState.parsedRows = [];
                    renderBatchEditablePreview('Unable to read Excel file. Please upload a valid .xlsx or .xls file.', true);
                    showToast('Unable to read Excel file.', 'error');
                }
            };
            reader.readAsArrayBuffer(file);
        }

        fileInput.addEventListener('change', function () {
            handleFile(fileInput.files[0]);
        });

        function openFilePicker() {
            if (fileInput.disabled) return;
            fileInput.click();
        }

        dropzone.addEventListener('click', function () {
            openFilePicker();
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openFilePicker();
            }
        });

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('drag-over');
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('drag-over');
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
            const file = e.dataTransfer?.files?.[0];
            if (file) handleFile(file);
        });

        submitBtn?.addEventListener('click', async function () {
            revalidateTurnoverBatchState();
            if (batchState.validationErrors.length || batchState.summaryErrors.length || !batchState.president || !batchState.vicePresident) {
                showToast('Please fix batch validation errors first.', 'error');
                return;
            }

            const formData = buildBatchFormData();
            try {
                if (typeof window.showLoading === 'function') {
                    window.showLoading('Submitting Batch Registration', 'Please wait...');
                }
                const response = await fetch(config.registerUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                    body: formData,
                });
                const payload = await response.json();
                if (!response.ok) {
                    if (payload.errors) {
                        const list = Object.entries(payload.errors).map(function (entry) {
                            return entry[1][0];
                        });
                        batchState.summaryErrors = list;
                        renderBatchEditablePreview('Server validation failed. Fix the highlighted cells below.', true);
                    }
                    throw payload;
                }
                showToast(payload.message || 'Batch registration submitted.', 'success');
                setTimeout(function () { window.location.reload(); }, 1200);
            } catch (error) {
                const message = error?.message || (error?.errors ? 'Please fix the highlighted fields.' : 'Batch registration failed.');
                showToast(message, 'error');
            } finally {
                if (typeof window.hideLoading === 'function') window.hideLoading();
            }
        });
    }

    function bindWizard() {
        document.querySelectorAll('.turnover-wizard-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const target = Number(tab.dataset.step);
                if (target > currentStep) {
                    for (let s = currentStep; s < target; s++) {
                        if (!validateStep(s)) { showStep(s); return; }
                    }
                }
                showStep(target);
            });
        });

        document.getElementById('turnoverPrevBtn')?.addEventListener('click', function () {
            if (currentStep > 1) showStep(currentStep - 1);
        });

        document.getElementById('turnoverNextBtn')?.addEventListener('click', function () {
            if (!validateStep(currentStep)) return;
            if (currentStep < maxStep) showStep(currentStep + 1);
        });

        document.getElementById('turnoverRegistrationForm')?.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!validateStep(1) || !validateStep(2)) return;

            const form = event.target;
            const formData = new FormData(form);
            try {
                if (typeof window.showLoading === 'function') {
                    window.showLoading('Submitting Registration', 'Please wait while we register the incoming officers...');
                }
                const response = await fetch(config.registerUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                    body: formData,
                });
                const payload = await response.json();
                if (!response.ok) {
                    if (payload.errors) {
                        Object.entries(payload.errors).forEach(function ([key, msgs]) {
                            const parts = key.split('.');
                            if (parts.length === 2) setFieldError(parts[0], parts[1], msgs[0]);
                        });
                    }
                    throw payload;
                }
                showToast(payload.message || 'Registration submitted.', 'success');
                setTimeout(function () { window.location.reload(); }, 1200);
            } catch (error) {
                const message = error?.message || (error?.errors ? 'Please fix the highlighted fields.' : 'Registration failed.');
                showToast(message, 'error');
            } finally {
                if (typeof window.hideLoading === 'function') window.hideLoading();
            }
        });
    }

    function bindCompleteModal() {
        const modal = document.getElementById('completeTurnoverModal');
        const openBtn = document.getElementById('openCompleteTurnoverModal');
        const confirmInput = document.getElementById('completeTurnoverConfirm');
        const confirmBtn = document.getElementById('confirmCompleteTurnover');

        function closeModal() { if (modal) modal.hidden = true; }

        openBtn?.addEventListener('click', function () { if (modal) modal.hidden = false; });
        modal?.querySelectorAll('[data-close-modal]').forEach(function (el) { el.addEventListener('click', closeModal); });

        confirmInput?.addEventListener('input', function () {
            if (confirmBtn) confirmBtn.disabled = confirmInput.value.trim() !== 'Confirm';
        });

        confirmBtn?.addEventListener('click', async function () {
            if (!config.completeUrl) return;
            try {
                if (typeof window.showLoading === 'function') {
                    window.showLoading('Completing Turnover', 'Transferring administrative access...');
                }
                const response = await fetch(config.completeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ confirmation: confirmInput.value.trim() }),
                });
                const payload = await response.json();
                if (!response.ok) throw payload;
                showToast(payload.message || 'Turnover completed.', 'success');
                setTimeout(function () { window.location.href = payload.redirect || '/dashboard'; }, 1200);
            } catch (error) {
                showToast(error?.message || 'Turnover completion failed.', 'error');
            } finally {
                if (typeof window.hideLoading === 'function') window.hideLoading();
            }
        });
    }

    function bindStartTurnover() {
        document.getElementById('startTurnoverBtn')?.addEventListener('click', async function () {
            try {
                if (typeof window.showLoading === 'function') window.showLoading('Starting Turnover', 'Please wait...');
                const response = await fetch(config.startUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok) throw payload;
                showToast(payload.message || 'Turnover started.', 'success');
                setTimeout(function () { window.location.reload(); }, 1000);
            } catch (error) {
                showToast(error?.message || 'Unable to start turnover.', 'error');
            } finally {
                if (typeof window.hideLoading === 'function') window.hideLoading();
            }
        });
    }

    function bindOverlay() {
        const overlay = document.getElementById('federationTurnoverOverlay');
        const modal = document.getElementById('federationTurnoverModal');
        const closeBtn = document.getElementById('toModalCloseBtn');
        const toggleSizeBtn = document.getElementById('toModalToggleSizeBtn');
        const toggleSizeIcon = document.getElementById('toModalToggleSizeIcon');
        let isFullscreen = false;

        if (!overlay || !config.showModal) return;

        overlay.classList.add('to-overlay--visible');

        closeBtn?.addEventListener('click', function () {
            if (config.canClose && !config.portalLocked) {
                overlay.classList.remove('to-overlay--visible');
            }
        });

        toggleSizeBtn?.addEventListener('click', function () {
            isFullscreen = !isFullscreen;
            modal?.classList.toggle('to-modal--fullscreen', isFullscreen);

            if (toggleSizeIcon) {
                toggleSizeIcon.className = isFullscreen ? 'fas fa-compress' : 'fas fa-expand';
            }

            const label = isFullscreen ? 'Restore Down' : 'Maximize';
            toggleSizeBtn.title = label;
            toggleSizeBtn.setAttribute('aria-label', label);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!config.showModal) return;
        bindOverlay();
        bindFieldBehaviors();
        bindRegistrationMode();
        bindBatchUpload();
        bindWizard();
        bindCompleteModal();
        bindStartTurnover();
        showStep(1);
    });
})();
