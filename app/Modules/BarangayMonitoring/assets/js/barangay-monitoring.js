'use strict';

let bmStatus = 'all';
let bmSearch = '';
let bmBarangay = 'all';

function bmApplyFilters() {
    const cards = document.querySelectorAll('.bm-list-item');
    const empty = document.getElementById('bm-empty');
    let visible = 0;

    cards.forEach((card) => {
        const status = card.getAttribute('data-status') || '';
        const name = card.getAttribute('data-name') || '';
        const barangay = card.getAttribute('data-barangay') || '';

        const statusMatch = bmStatus === 'all' || bmStatus === status;
        const barangayMatch = bmBarangay === 'all' || barangay === bmBarangay.toLowerCase();
        const searchMatch = !bmSearch || name.includes(bmSearch);
        const show = statusMatch && barangayMatch && searchMatch;

        card.hidden = !show;
        if (show) {
            visible += 1;
        }
    });

    if (empty) {
        empty.hidden = visible > 0;
    }
}

window.bmFilterBarangays = function () {
    const statusSelect = document.getElementById('bmFilterStatus');
    const barangaySelect = document.getElementById('bmFilterBarangay');

    bmStatus = statusSelect?.value || 'all';
    bmBarangay = barangaySelect?.value || 'all';
    bmApplyFilters();
};

function bmSetStatus(status, el) {
    bmStatus = status;
    document.querySelectorAll('.bm-chip[data-status]').forEach((chip) => chip.classList.remove('active'));
    el?.classList.add('active');
    bmApplyFilters();
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('bm-search');
    if (search) {
        search.addEventListener('input', (event) => {
            bmSearch = String(event.target.value || '').trim().toLowerCase();
            bmApplyFilters();
        });
    }

    bmFilterBarangays();
});
