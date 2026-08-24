@php
    $sharedData = [
        'data-account-id' => $account->id,
        'data-first-name' => $firstName ?? '',
        'data-last-name' => $lastName ?? '',
        'data-middle-name' => $middleName !== '' ? mb_strtoupper($middleName, 'UTF-8') : '',
        'data-suffix' => $profile?->suffix ?? '',
        'data-sex' => $profile?->sex ?? '',
        'data-date-of-birth' => $profile?->date_of_birth?->toDateString() ?? '',
        'data-age' => $profile?->age ?? '',
        'data-contact-number' => $profile?->contact_number ?? '',
        'data-email' => $account->email ?? '',
        'data-position' => $profile?->position ?? '',
        'data-federation-position' => $profile?->federation_position ?? '',
        'data-account-role' => $account->role ?? '',
        'data-barangay-id' => $account->barangay_id ?? '',
        'data-barangay-name' => $account->barangay?->name ?? '',
        'data-municipality' => $profile?->municipality ?? '',
        'data-province' => $profile?->province ?? '',
        'data-region' => $profile?->region ?? '',
        'data-status' => $account->status ?? '',
        'data-term-status' => $term?->status ?? 'ACTIVE',
        'data-term-start' => $term?->term_start?->toDateString() ?? '',
        'data-term-end' => $term?->term_end?->toDateString() ?? '',
    ];
@endphp

<div class="account-actions-menu">
    <button type="button"
            class="account-actions-trigger"
            aria-label="Account actions for {{ $displayName }}"
            aria-haspopup="true"
            aria-expanded="false">
        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
    </button>

    <div class="account-actions-dropdown" role="menu">
        <button type="button"
                class="account-actions-item account-actions-item-view btn-view-account"
                role="menuitem"
                @foreach($sharedData as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach
                data-email-verified-at="{{ $account->email_verified_at?->format('m/d/Y h:i A') ?? '' }}">
            <i class="fas fa-eye" aria-hidden="true"></i>
            <span>View Details</span>
        </button>
        @if(empty($readOnlyActions))
        <button type="button"
                class="account-actions-item account-actions-item-edit btn-edit-account"
                role="menuitem"
                @foreach($sharedData as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>
            <i class="fas fa-pen" aria-hidden="true"></i>
            <span>{{ ($hideDelete ?? false) ? 'Assign Federation Position' : 'Edit Member' }}</span>
        </button>
        @if(empty($hideDelete))
        <button type="button"
                class="account-actions-item account-actions-item-danger btn-delete-account"
                role="menuitem"
                data-account-id="{{ $account->id }}"
                data-display-name="{{ $displayName }}">
            <i class="fas fa-trash" aria-hidden="true"></i>
            <span>Delete Account</span>
        </button>
        @endif
        @else
        <div class="account-actions-item account-actions-item-muted" role="presentation">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>Incoming Officer (read-only)</span>
        </div>
        @endif
    </div>
</div>
