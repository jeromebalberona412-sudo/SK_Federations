{{-- Out-of-range age confirmation — safety fallback when a 25+ birthdate reaches the form --}}
<div
    id="accountsAgeConfirmModal"
    class="accounts-age-confirm-overlay"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="accountsAgeConfirmTitle"
    aria-describedby="accountsAgeConfirmMessage"
>
    <div class="accounts-age-confirm-backdrop" id="accountsAgeConfirmBackdrop"></div>
    <div class="accounts-age-confirm-card" role="document">
        <div class="accounts-age-confirm-header">
            <h2 class="accounts-age-confirm-title" id="accountsAgeConfirmTitle">Are you sure?</h2>
            <button type="button" class="accounts-age-confirm-close" id="accountsAgeConfirmCloseBtn" aria-label="Close">&times;</button>
        </div>

        <div class="accounts-age-confirm-body">
            <p class="accounts-age-confirm-message" id="accountsAgeConfirmMessage">
                The selected birthdate indicates that this SK Official is
                {{ \App\Modules\Accounts\Support\SkOfficialTermDates::SK_MAX_AGE + 1 }} years old or older.
            </p>
            <p class="accounts-age-confirm-range">
                The allowed age range is
                <strong>{{ \App\Modules\Accounts\Support\SkOfficialTermDates::SK_MIN_AGE }}–{{ \App\Modules\Accounts\Support\SkOfficialTermDates::SK_MAX_AGE }}</strong>
                years old as of
                {{ \App\Modules\Accounts\Support\SkOfficialTermDates::eligibilityReference()->format('F j, Y') }}.
            </p>
            <p class="accounts-age-confirm-note">
                Continuing submits the record for server-side review. It will be rejected
                unless the birthdate is unchanged on an existing account.
            </p>
        </div>

        <div class="accounts-age-confirm-footer">
            <button type="button" class="btn-secondary-modern" id="accountsAgeConfirmCancelBtn">Cancel</button>
            <button type="button" class="btn-submit-light" id="accountsAgeConfirmContinueBtn">Continue</button>
        </div>
    </div>
</div>
