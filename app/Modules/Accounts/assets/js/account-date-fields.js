(function () {
    function formatLocalDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function parseLocalDateString(dateStr) {
        const value = String(dateStr || '').trim();
        let match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (match) {
            const year = Number(match[1]);
            const month = Number(match[2]);
            const day = Number(match[3]);
            const date = new Date(year, month - 1, day);
            if (Number.isNaN(date.getTime())) return null;
            if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
            return date;
        }

        match = /^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/.exec(value);
        if (!match) {
            return null;
        }

        let year = Number(match[3]);
        if (String(match[3]).length === 2) {
            year += year >= 70 ? 1900 : 2000;
        }

        const month = Number(match[1]);
        const day = Number(match[2]);
        const date = new Date(year, month - 1, day);
        if (Number.isNaN(date.getTime())) return null;
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
        return date;
    }

    function normalizeDateInputValue(value) {
        const parsed = parseLocalDateString(value);
        return parsed ? formatLocalDate(parsed) : '';
    }

    function formatDateForTextInput(value) {
        const iso = normalizeDateInputValue(value);
        if (!iso) return '';
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
        return match ? `${match[2]}/${match[3]}/${match[1]}` : '';
    }

    function isStrictUsDateFormat(value) {
        return /^\d{2}\/\d{2}\/\d{4}$/.test(String(value || '').trim());
    }

    function calculateAge(dateOfBirthValue) {
        if (!dateOfBirthValue) return '';
        const dob = parseLocalDateString(dateOfBirthValue);
        if (!dob) return '';
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        return age >= 0 ? String(age) : '';
    }

    function applyUsDateTextInput(input) {
        if (!input) return;
        input.maxLength = 10;
        input.addEventListener('beforeinput', (event) => {
            if (event.inputType.startsWith('delete') || event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                return;
            }

            const data = event.data ?? '';
            if (data !== '' && !/^[0-9/]+$/.test(data)) {
                event.preventDefault();
                return;
            }

            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            const nextValue = input.value.slice(0, start) + data + input.value.slice(end);
            if (nextValue.length > 10 || !/^(\d{0,2})(\/(\d{0,2})(\/(\d{0,4})?)?)?$/.test(nextValue)) {
                event.preventDefault();
            }
        });
    }

    window.AccountDateFieldUtils = {
        formatLocalDate,
        parseLocalDateString,
        normalizeDateInputValue,
        formatDateForTextInput,
        isStrictUsDateFormat,
        calculateAge,
        applyUsDateTextInput,
    };
})();
