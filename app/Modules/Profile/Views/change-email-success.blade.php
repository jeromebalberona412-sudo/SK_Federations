@extends('profile::layouts.account-auth')

@section('title', 'Email Changed')
@section('card-class', 'ce-success-card sk-fed-compact-card')

@push('styles')
    @vite(['app/Modules/Profile/assets/css/change-email.css'])
@endpush

@section('content')
    <div class="ce-success-shell" role="status" aria-live="polite">
        <div class="ce-success-check" aria-hidden="true">
            <span class="ce-success-check-circle">
                <span class="ce-success-checkmark"></span>
            </span>
        </div>

        <div class="card-header">
            <h2 class="card-title">Email changed successfully</h2>
            <p class="card-subtitle">
                @if (!empty($newEmail))
                    Your account email is now <strong>{{ $newEmail }}</strong>.
                @else
                    Your account email has been updated.
                @endif
                You have been signed out for security.
            </p>
        </div>

        <a href="{{ route('login') }}" class="ce-btn-resend ce-success-login-btn">
            Please login your new email account
        </a>
    </div>
@endsection
