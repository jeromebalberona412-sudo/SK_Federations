<nav class="navbar sk-fed-navbar">
    <div class="navbar-left">
        <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="fas fa-bars toggle-icon-expand"></i>
            <i class="fas fa-ellipsis-v toggle-icon-collapse"></i>
        </button>
        <div class="navbar-brand">
            <img src="{{ asset('Images/SK_OnePortal.png') }}" alt="SK OnePortal Logo" class="brand-logo">
            <span class="brand-name">SK OnePortal</span>
        </div>
    </div>

    @stack('navbar-center')

    <div class="navbar-right">
        @php
            $unreadNotifCount = (int) ($unreadNotificationCount ?? 0);
            $unreadNotifLabel = $unreadNotifCount > 99 ? '99+' : (string) $unreadNotifCount;
        @endphp
        <div class="notif-menu" id="notifMenu">
            <button
                type="button"
                class="notif-btn"
                id="notifBtn"
                onclick="toggleNotifPopover(event)"
                aria-label="Notifications"
                aria-expanded="false"
                aria-haspopup="true"
            >
                <i class="fas fa-bell"></i>
                <span class="notif-badge" id="notifBadge" data-unread-total="{{ $unreadNotifCount }}" style="{{ $unreadNotifCount > 0 ? '' : 'display: none;' }}">{{ $unreadNotifLabel }}</span>
            </button>

            @include('notifications::dropdown-popover')
        </div>

        <div class="profile-dropdown-wrapper">
            <button class="profile-btn" onclick="toggleProfileDropdown(event)" aria-label="Profile menu" aria-expanded="false" aria-haspopup="true">
                <img src="{{ $avatar }}" alt="Profile" class="nav-avatar">
                <i class="fas fa-chevron-down nav-chevron"></i>
            </button>

            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-dropdown-user-card">
                    <img src="{{ $avatar }}" alt="{{ $user->name ?? 'User' }}" class="profile-dropdown-avatar">
                    <div class="profile-dropdown-user-info">
                        <span class="profile-dropdown-name">{{ $displayName ?? ($user->name ?? 'User') }}</span>
                        <span class="profile-dropdown-role">{{ $formattedRole ?? 'SK Federation' }}</span>
                    </div>
                </div>

                <div class="profile-dropdown-divider"></div>

                <a href="{{ route('profile') }}" class="profile-dropdown-item" id="nav-profile-link" data-no-loading>
                    <span class="profile-dropdown-icon profile-dropdown-icon--profile">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                    </span>
                    View Profile
                </a>

                <a href="{{ route('profile', ['tab' => 'settings']) }}" class="profile-dropdown-item" id="nav-account-settings-link" data-no-loading>
                    <span class="profile-dropdown-icon profile-dropdown-icon--settings">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    Account Settings
                </a>

                <div class="profile-dropdown-divider"></div>

                <button type="button" class="profile-dropdown-item profile-dropdown-item--logout" onclick="showLogoutModal()">
                    <span class="profile-dropdown-icon profile-dropdown-icon--logout">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </span>
                    Logout
                </button>
            </div>
        </div>
    </div>
</nav>
