{{-- Longer legal name confirmation — Federations Accounts --}}
<div
    id="accountsLongNameModal"
    class="accounts-long-name-overlay"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="accountsLongNameTitle"
>
    <div class="accounts-long-name-backdrop" id="accountsLongNameBackdrop"></div>
    <div class="accounts-long-name-card" role="document">
        <div class="accounts-long-name-header">
            <h2 class="accounts-long-name-title" id="accountsLongNameTitle">Longer legal name</h2>
            <button type="button" class="accounts-long-name-close" id="accountsLongNameCloseBtn" aria-label="Close">&times;</button>
        </div>

        <div class="accounts-long-name-body">
            <p class="accounts-long-name-lead">
                This name field has more than <strong>50</strong> characters.
                Confirm if you want to continue up to the maximum of <strong>150</strong> characters.
            </p>

            <div class="accounts-long-name-confirm-box">
                <label class="accounts-long-name-confirm-label" for="accountsLongNameConfirmInput">
                    Type <span class="accounts-long-name-keyword">yes</span> to continue
                </label>
                <input
                    type="text"
                    id="accountsLongNameConfirmInput"
                    class="accounts-long-name-confirm-input"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="yes"
                    maxlength="8"
                >
                <p class="accounts-long-name-confirm-hint" id="accountsLongNameConfirmHint" hidden>
                    Please type <strong>yes</strong> exactly to continue.
                </p>
            </div>
        </div>

        <div class="accounts-long-name-footer">
            <button type="button" class="btn-secondary-modern" id="accountsLongNameCancelBtn">Cancel</button>
        </div>
    </div>
</div>
