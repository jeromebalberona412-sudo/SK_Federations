{{-- Shared Maximize / Restore Down + Close. Same icons on every account modal. --}}
<div class="modal-controls">
    <button type="button"
            class="modal-win-btn modal-win-btn-maximize"
            id="{{ $resizeId }}"
            onclick="{{ $resizeFn }}()"
            title="Maximize"
            aria-label="Maximize">
        <svg class="modal-win-icon-max" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <rect x="2.5" y="2.5" width="11" height="11" rx="0.5"></rect>
        </svg>
        <svg class="modal-win-icon-restore" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path d="M5 3.5h7.5V11"></path>
            <rect x="2.5" y="5.5" width="8" height="8" rx="0.5"></rect>
        </svg>
    </button>
    <button type="button"
            class="modal-win-btn modal-win-btn-close"
            onclick="{{ $closeFn }}()"
            title="Close"
            aria-label="Close">&times;</button>
</div>
