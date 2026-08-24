@if (app(\App\Modules\Authentication\Services\TurnstileService::class)->isConfigured())
    <div class="form-group">
        <div class="cf-turnstile" data-sitekey="{{ (string) config('services.turnstile.site_key') }}"></div>
        <div
            class="invalid-feedback d-block"
            id="turnstile-error"
            @if ($errors->has('cf-turnstile-response'))
                style="display: block;"
            @else
                style="display: none;"
            @endif
        >
            {{ $errors->first('cf-turnstile-response') }}
        </div>
    </div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
