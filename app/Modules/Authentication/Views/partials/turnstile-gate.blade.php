@php
    $turnstileService = $turnstileService ?? app(\App\Modules\Authentication\Services\TurnstileService::class);
    $turnstileSubtitle = $turnstileSubtitle ?? 'Complete the security check to continue.';
@endphp

@if($turnstileService->isConfigured())
    {{-- Critical fallback: hide modal + size SVGs even if Vite CSS fails to load --}}
    <style id="turnstile-gate-critical">
        #turnstile-modal:not(.turnstile-modal-visible){display:none!important;visibility:hidden!important;pointer-events:none!important;}
        #turnstile-modal.turnstile-modal-visible{display:flex!important;visibility:visible!important;pointer-events:auto!important;position:fixed;inset:0;z-index:10050;align-items:center;justify-content:center;padding:1rem;box-sizing:border-box;}
        .turnstile-modal-icon{width:40px;height:40px;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
        .turnstile-modal-icon svg{width:22px!important;height:22px!important;max-width:22px!important;max-height:22px!important;display:block;}
        .turnstile-close-btn svg{width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;display:block;}
    </style>

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>

    <div id="turnstile-modal" class="turnstile-modal" role="dialog" aria-modal="true" aria-label="Human verification" hidden>
        <div id="turnstile-modal-backdrop" class="turnstile-modal-backdrop"></div>
        <div class="turnstile-modal-card">
            <div class="turnstile-modal-header">
                <div class="turnstile-modal-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0
                                 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332
                                 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="turnstile-modal-copy">
                    <h2 class="turnstile-modal-title">Verify you're human</h2>
                    <p class="turnstile-modal-subtitle">{{ $turnstileSubtitle }}</p>
                </div>
                <button id="turnstile-close-btn" class="turnstile-close-btn" type="button" aria-label="Cancel verification">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414
                                 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293
                                 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                              clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <div class="turnstile-modal-body">
                <div id="turnstile-container"></div>
            </div>
            <div class="turnstile-modal-footer">
                <button id="turnstile-cancel-btn" type="button" class="turnstile-cancel-link">
                    Cancel and go back
                </button>
            </div>
        </div>
    </div>

    <div id="turnstile-gate-config"
         hidden
         data-enabled="1"
         data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
@endif
