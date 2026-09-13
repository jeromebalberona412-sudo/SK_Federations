<div id="skTourRoot" class="sk-tour-root" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Interactive Guided Tour">
    {{-- Dark Backdrop Overlay --}}
    <div class="sk-tour-backdrop" id="skTourBackdrop"></div>

    {{-- Highlight Spotlight Box with Glow and Outline --}}
    <div class="sk-tour-spotlight" id="skTourSpotlight"></div>

    {{-- Floating Tooltip / Card --}}
    <div class="sk-tour-card" id="skTourCard" role="alertdialog">
        <div class="sk-tour-card-header">
            <span class="sk-tour-badge" id="skTourBadge">Step 1 of 15</span>
            <button type="button" class="sk-tour-close-btn" id="skTourCloseBtn" title="Close tutorial" aria-label="Close tutorial" hidden style="display: none;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sk-tour-card-body">
            <div class="sk-tour-icon-wrap" id="skTourIconWrap">
                <i class="fas fa-question-circle" id="skTourStepIcon"></i>
            </div>
            <div class="sk-tour-text-wrap">
                <h3 class="sk-tour-title" id="skTourTitle">Dashboard</h3>
                <p class="sk-tour-description" id="skTourDescription">
                    This is your main overview page. View important statistics, activities, notifications, and quick information about the SK Federation.
                </p>
            </div>
        </div>

        <div class="sk-tour-card-footer">
            <button type="button" class="sk-tour-btn sk-tour-btn--skip" id="skTourSkipBtn" hidden style="display: none;">
                Skip Tutorial
            </button>
            <div class="sk-tour-actions-right">
                <button type="button" class="sk-tour-btn sk-tour-btn--back" id="skTourBackBtn" style="display: none;">
                    <i class="fas fa-arrow-left"></i> <span>Back</span>
                </button>
                <button type="button" class="sk-tour-btn sk-tour-btn--next" id="skTourNextBtn">
                    <span id="skTourNextLabel">Next</span> <i class="fas fa-arrow-right" id="skTourNextIcon"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Skip Confirmation Dialog --}}
    <div class="sk-tour-confirm-overlay" id="skTourConfirmOverlay" style="display: none;">
        <div class="sk-tour-confirm-box">
            <div class="sk-tour-confirm-icon">
                <i class="fas fa-compass"></i>
            </div>
            <h4>Skip tutorial?</h4>
            <p>You can restart this interactive tour anytime using the <strong>question mark (?)</strong> button in the header or in your Account menu.</p>
            <div class="sk-tour-confirm-actions">
                <button type="button" class="sk-tour-confirm-btn sk-tour-confirm-btn--cancel" id="skTourConfirmCancelBtn">
                    Continue Tutorial
                </button>
                <button type="button" class="sk-tour-confirm-btn sk-tour-confirm-btn--confirm" id="skTourConfirmSkipBtn">
                    Skip
                </button>
            </div>
        </div>
    </div>
</div>
