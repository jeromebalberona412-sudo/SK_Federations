@extends('profile::layouts.account-auth')

@section('title', 'Change Password')
@section('card-class', 'cp-change-card')

@php
    $pwMin = (int) config('sk_fed_auth.password_reset.password.min_length', 8);
    $pwMax = (int) config('sk_fed_auth.password_reset.password.max_length', 64);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/profile/css/change-password.css') }}?v={{ @filemtime(app_path('Modules/Profile/assets/css/change-password.css')) ?: time() }}">
@endpush

@push('scripts')
    <script src="{{ url('/modules/profile/js/change-password.js') }}?v={{ time() }}"></script>
@endpush

@section('content')
    <div class="form-header cp-form-header">
        <h2>Change Password</h2>
        <p>Set a new password for your account</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form class="login-form" id="change-password-form" action="{{ route('password.change.update') }}" method="POST" data-password-min-length="{{ $pwMin }}" data-password-max-length="{{ $pwMax }}" novalidate>
        @csrf

        <div class="form-group">
            <label for="cpEmail">
                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                </svg>
                Account Email
            </label>
            <input type="email" id="cpEmail" name="email" class="form-control" placeholder="Enter your account email" autocomplete="email" maxlength="255" value="{{ old('email', $user->email ?? '') }}" required>
            <div class="invalid-feedback d-block" id="cpEmailClientError" hidden></div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">
                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                New Password
            </label>
            <div class="password-input-container">
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password" autocomplete="new-password" minlength="{{ $pwMin }}" maxlength="{{ $pwMax }}" required>
                <button type="button" class="pw-toggle-btn" data-target="password" aria-label="Show password" tabindex="-1">
                    <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                        <path d="M1 1l22 22"/>
                    </svg>
                </button>
            </div>
            <ul class="password-rules" id="passwordRules" aria-live="polite" hidden>
                <li id="rule-length">At least {{ $pwMin }} characters</li>
                <li id="rule-lowercase">At least one lowercase letter</li>
                <li id="rule-uppercase">At least one uppercase letter</li>
                <li id="rule-number">At least one number</li>
                <li id="rule-special">At least one special character</li>
            </ul>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">
                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                Confirm New Password
            </label>
            <div class="password-input-container">
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Re-enter new password" autocomplete="new-password" minlength="{{ $pwMin }}" maxlength="{{ $pwMax }}" required>
                <button type="button" class="pw-toggle-btn" data-target="password_confirmation" aria-label="Show password" tabindex="-1">
                    <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                        <path d="M1 1l22 22"/>
                    </svg>
                </button>
            </div>
            <div class="invalid-feedback d-block" id="passwordConfirmClientError" hidden></div>
            @error('password_confirmation')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="login-btn btn btn-primary w-100" id="cpSubmitBtn">
            <span id="cpBtnText">Send Email Verification</span>
        </button>
    </form>

    <div class="form-footer">
        <a href="{{ route('profile') }}" class="back-link">← Back to Profile</a>
    </div>
@endsection
