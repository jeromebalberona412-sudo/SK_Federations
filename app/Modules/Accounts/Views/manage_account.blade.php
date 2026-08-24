@extends('layout::app')

@section('title', 'Manage Account')

@php
    $isOfficials = ($accountType ?? 'sk_federation') === 'sk_officials';
    $pageTitle = $isOfficials ? 'Manage SK Officials Account' : 'Manage SK Federation Account';
    $pageSubtitle = $isOfficials
        ? 'Create or manage SK Officials member accounts'
        : 'Assign federation positions to SK Chairpersons from each barangay';
    $addLabel = $isOfficials ? 'Add SK Official' : 'Add Federation Member';
    $positionFilterLabel = $isOfficials ? 'Position' : 'Federation Position';
    $tableColspan = $isOfficials ? 8 : 6;
    $accountCssVersion = @filemtime(app_path('Modules/Accounts/assets/css/account.css')) ?: time();
    $accountJsVersion = @filemtime(app_path('Modules/Accounts/assets/js/account.js')) ?: time();
    $batchTemplateType = $isOfficials ? 'officials' : 'federation';
    $batchRole = $isOfficials ? 'sk_official' : 'sk_fed';
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/accounts/css/account.css') }}?v={{ $accountCssVersion }}">
@endpush

@section('content')
<div class="main-content-modern accounts-page container-fluid" id="mainContent"
     x-data="accountsPage()"
     x-init="init()"
     data-account-type="{{ $accountType ?? 'sk_federation' }}"
     data-batch-role="{{ $batchRole }}"
     data-batch-template-type="{{ $batchTemplateType }}"
     @unless($isOfficials)
     data-taken-federation-positions="{{ json_encode(array_values($takenFederationPositions ?? [])) }}"
     @endunless>

    <div class="manage-account-container">
        <div class="page-header-modern-with-button">
            <div class="page-header-top">
                <h1 class="page-title-modern" id="pageTitle">{{ $pageTitle }}</h1>
                <p class="page-subtitle-modern" id="pageSubtitle">{{ $pageSubtitle }}</p>
            </div>
            <div class="page-header-filters">
                <form method="get" action="#" class="accounts-filter-form" id="accountsFilterForm" novalidate>
                    <div class="accounts-filter-grid">
                        <div class="filter-dropdown-container">
                            <select id="barangayFilter" class="filter-dropdown form-select" name="barangay_id" aria-label="Filter by barangay">
                                <option value="">All Barangays</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay->id }}" {{ request('barangay_id') == $barangay->id ? 'selected' : '' }}>{{ $barangay->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-dropdown-container">
                            <select id="positionFilter" class="filter-dropdown form-select" name="position" aria-label="Filter by position">
                                <option value="">All Positions</option>
                                @foreach($positionOptions ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ request('position') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="search-container">
                            <div class="search-input-wrap">
                                <input type="text" id="searchInput" name="search" class="search-input form-control" value="{{ request('search') }}" placeholder="Search accounts..." aria-label="Search accounts">
                                <button type="button" class="search-btn" id="searchBtn" aria-label="Search">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.35-4.35"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        @if($isOfficials)
                        <div class="header-action-buttons">
                            <button type="button" class="btn-primary-modern btn-green" id="addAccountBtn" onclick="openAddAccountModal()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                                <span id="addButtonText">{{ $addLabel }}</span>
                            </button>
                        </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if($isOfficials)
        <div class="accounts-table-toolbar"
             x-show="selectedCount > 0"
             x-transition:enter="float-delete-enter"
             x-transition:leave="float-delete-leave"
             style="display:none;">
            <button type="button" class="btn-float-delete" @click="openBulkDelete()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span x-text="'Delete ' + selectedCount + ' row' + (selectedCount === 1 ? '' : 's')"></span>
            </button>
        </div>
        @endif

        <div class="table-card-modern">
            <div class="table-responsive">
                <table class="accounts-table" id="accountsTable">
                    <thead>
                        <tr>
                            @if($isOfficials)
                            <th class="th-checkbox">
                                <input type="checkbox" class="account-checkbox account-checkbox-header"
                                       :checked="selectAll" @change="toggleSelectAll($event.target.checked)" aria-label="Select all visible rows">
                            </th>
                            @endif
                            <th class="th-name accounts-th-sortable" data-sort-key="name" data-sort-type="text" aria-sort="none">
                                <button type="button" class="accounts-sort-btn accounts-sort-btn--fullname" aria-haspopup="menu" aria-expanded="false">
                                    <span class="table-fullname-label">
                                        Full Name
                                        <span class="table-col-hint">LN, FN, MN, Suffix</span>
                                    </span>
                                    <span class="accounts-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th class="th-email accounts-th-sortable" data-sort-key="email" data-sort-type="text" aria-sort="none">
                                <button type="button" class="accounts-sort-btn" aria-haspopup="menu" aria-expanded="false">
                                    Email Address
                                    <span class="accounts-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th class="th-barangay accounts-th-sortable" data-sort-key="barangay" data-sort-type="text" aria-sort="none">
                                <button type="button" class="accounts-sort-btn" aria-haspopup="menu" aria-expanded="false">
                                    Barangay
                                    <span class="accounts-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th class="th-position">{{ $isOfficials ? 'Position' : 'SK Position' }}</th>
                            @if($isOfficials)
                                <th class="th-term">Term Start</th>
                                <th class="th-term">Term End</th>
                            @else
                                <th class="th-federation-position">Federation Position</th>
                            @endif
                            <th class="th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="accountsTableBody">
                        @forelse($accounts as $account)
                            @php
                                $profile = $account->officialProfile;
                                $term = $profile?->latestTerm;
                                $firstName = $profile?->first_name ? mb_strtoupper($profile->first_name, 'UTF-8') : null;
                                $lastName = $profile?->last_name ? mb_strtoupper($profile->last_name, 'UTF-8') : null;
                                $middleName = trim((string) ($profile?->middle_name ?? ''));
                                $middleInitial = $middleName !== '' ? mb_strtoupper(mb_substr($middleName, 0, 1, 'UTF-8'), 'UTF-8').'.' : null;
                                $suffixDisplay = $profile?->suffix;
                                if (in_array($suffixDisplay, ['NONE', 'None', null, ''], true)) {
                                    $suffixDisplay = null;
                                }
                                $nameCore = collect([$lastName, $firstName])->filter()->implode(', ');
                                $fullName = trim(collect([
                                    $nameCore,
                                    $middleInitial,
                                    $suffixDisplay,
                                ])->filter()->implode(' '));
                                $displayName = $fullName !== '' ? $fullName : ($account->name ?? 'N/A');
                                $sortName = strtolower(trim(collect([$lastName, $firstName, $middleInitial, $suffixDisplay])->filter()->implode(' ')));
                                $federationPosition = $profile?->displayFederationPosition();
                                $filterPosition = $isOfficials ? ($profile?->position ?? '') : ($profile?->federation_position ?? '');
                                $searchBlob = strtolower(trim(collect([
                                    $displayName,
                                    $account->email,
                                    $account->barangay?->name,
                                    $profile?->position,
                                    $federationPosition,
                                ])->filter()->implode(' ')));
                                $isIncomingTurnover = $account->isIncomingTurnoverOfficer();
                            @endphp
                            <tr data-account-id="{{ $account->id }}"
                                data-barangay-id="{{ $account->barangay_id ?? '' }}"
                                data-filter-position="{{ $filterPosition }}"
                                data-search-text="{{ $searchBlob }}"
                                data-sort-name="{{ $sortName }}"
                                data-sort-email="{{ strtolower($account->email ?? '') }}"
                                data-sort-barangay="{{ strtolower($account->barangay?->name ?? '') }}"
                                data-sort-position="{{ strtolower($profile?->position ?? '') }}"
                                data-sort-federation-position="{{ strtolower($federationPosition ?? '') }}"
                                data-sort-term="{{ $term?->term_end?->format('Y-m-d') ?? '' }}"
                                @if($isIncomingTurnover) data-incoming-turnover="1" @endif>
                                @if($isOfficials)
                                <td class="td-checkbox">
                                    @if($isIncomingTurnover)
                                        <span class="account-checkbox-placeholder" aria-hidden="true">—</span>
                                    @else
                                    <input type="checkbox" class="account-checkbox account-row-checkbox"
                                           value="{{ $account->id }}"
                                           @change="toggleRow({{ $account->id }}, $event.target.checked)"
                                           :checked="selectedRows.includes({{ $account->id }})"
                                           aria-label="Select {{ $displayName }}">
                                    @endif
                                </td>
                                @endif
                                <td class="td-name">{{ $displayName }}</td>
                                <td class="td-email">{{ $account->email }}</td>
                                <td class="td-barangay">{{ $account->barangay?->name ?? '—' }}</td>
                                <td class="td-position">
                                    @if($isOfficials)
                                        {{ $profile?->position ?? '—' }}
                                    @else
                                        {{ $profile?->position ?? '—' }}
                                    @endif
                                </td>
                                @if($isOfficials)
                                    <td class="td-term">{{ $term?->term_start?->format('m/d/Y') ?? '—' }}</td>
                                    <td class="td-term">{{ $term?->term_end?->format('m/d/Y') ?? '—' }}</td>
                                @else
                                    <td class="td-federation-position">
                                        @if($federationPosition)
                                            {{ $federationPosition }}
                                        @else
                                            <span class="text-muted">Not assigned</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="td-actions">
                                    @include('accounts::account_actions_menu', [
                                        'account' => $account,
                                        'profile' => $profile,
                                        'term' => $term,
                                        'displayName' => $displayName,
                                        'firstName' => $firstName,
                                        'lastName' => $lastName,
                                        'middleName' => $middleName,
                                        'hideDelete' => ! $isOfficials,
                                        'readOnlyActions' => $isIncomingTurnover,
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $tableColspan }}" class="text-center">No accounts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="accounts-page-footer pagination-footer" aria-label="Table pagination">
            <div class="pagination-footer-nav">
                <button type="button" class="pagination-arrow" id="prevBtn" disabled aria-label="Previous page">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <span class="pagination-page-label">Page</span>
                <input type="number" class="pagination-page-input" id="pageInput" value="1" min="1" aria-label="Current page">
                <span class="pagination-page-of">of <span id="totalPages">1</span></span>
                <button type="button" class="pagination-arrow" id="nextBtn" disabled aria-label="Next page">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
            <div class="pagination-footer-right">
                <select id="rowsPerPageSelect" class="pagination-rows-select" aria-label="Rows per page">
                    <option value="10">10 rows</option>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                </select>
                <span class="pagination-record-count" id="paginationInfo">0 records</span>
            </div>
        </div>
    </div>
</div>

<div id="accountsSortMenu" class="accounts-sort-menu" hidden role="menu" aria-label="Sort options"></div>

@if($isOfficials)
@include('accounts::form_sk_officials')
@else
@include('accounts::assign_federation_position_modal')
@endif
@include('accounts::view_account')
@include('accounts::delete_account_modal')

<div id="accountToast" role="status" aria-live="polite"><span id="accountToastMsg">Account successfully created!</span></div>
<div id="accountToastEdit" role="status" aria-live="polite"><span id="accountToastEditMsg">Account updated successfully!</span></div>
<div id="accountToastDelete" role="status" aria-live="polite"><span id="accountToastDeleteMsg">Account deleted successfully!</span></div>
<div id="accountToastError" role="alert" aria-live="assertive"><span id="accountToastErrorMsg">Unable to save account.</span></div>
@endsection

@push('scripts')
    <script src="{{ url('/modules/accounts/js/account-date-fields.js') }}?v={{ @filemtime(app_path('Modules/Accounts/assets/js/account-date-fields.js')) ?: time() }}"></script>
    <script src="{{ url('/modules/accounts/js/account.js') }}?v={{ $accountJsVersion }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@endpush
