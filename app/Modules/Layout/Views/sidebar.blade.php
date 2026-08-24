@php
    $archiveManagementOpen = request()->routeIs('archived.*');
    $accountsOpen = request()->routeIs('accounts.*');
    $isFederationAccountsActive = request()->routeIs('accounts.federation.index') || (request()->routeIs('accounts.manage') && (request('account_type', 'sk_federation') === 'sk_federation'));
    $isOfficialsAccountsActive = request()->routeIs('accounts.officials.index');
@endphp

<aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="{{ route('profile') }}" class="sidebar-profile sidebar-profile-link" id="sidebar-profile-link" data-no-loading>
            <img src="{{ $avatar }}" alt="Profile" class="sidebar-avatar">
            <div class="sidebar-user-info">
                <div class="s-name">{{ $displayName ?? ($user->name ?? 'User') }}</div>
                <div class="s-role">{{ $formattedRole }}</div>
                @if(!empty($sidebarMeta))
                    @if(!empty($sidebarMeta['position']))
                        <div class="s-meta">{{ $sidebarMeta['position'] }}</div>
                    @endif
                    @if(!empty($sidebarMeta['sex']))
                        <div class="s-meta">{{ $sidebarMeta['sex'] }}</div>
                    @endif
                    @if(!empty($sidebarMeta['contact_number']))
                        <div class="s-meta">{{ $sidebarMeta['contact_number'] }}</div>
                    @endif
                    @if(!empty($sidebarMeta['term_start']) && !empty($sidebarMeta['term_end']))
                        <div class="s-meta">Term: {{ $sidebarMeta['term_start'] }} – {{ $sidebarMeta['term_end'] }}</div>
                    @endif
                @endif
            </div>
        </a>

        <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-tooltip="Dashboard" id="sidebar-dashboard-link" data-no-loading>
            <i class="fas fa-home"></i><span>Dashboard</span>
        </a>

        <a href="{{ route('profile') }}" class="menu-item {{ request()->routeIs('profile') ? 'active' : '' }}" data-tooltip="Profile" data-no-loading>
            <i class="fas fa-user"></i><span>Profile</span>
        </a>

        <a href="{{ route('calendar') }}" class="menu-item {{ request()->routeIs('calendar') ? 'active' : '' }}" data-tooltip="Calendar" data-no-loading>
            <i class="fas fa-calendar-alt"></i><span>Calendar</span>
        </a>

        <a href="{{ route('community-feed') }}" class="menu-item {{ request()->routeIs('community-feed', 'community-feed.comments', 'sk-fed-profile', 'skfed.barangay-profile', 'skfed.barangay-profile.comments') ? 'active' : '' }}" data-tooltip="SK Community Feed" id="sidebar-community-feed-link" data-no-loading>
            <i class="fas fa-rss"></i><span>SK Community Feed</span>
        </a>

        <a href="{{ route('barangay-monitoring') }}" class="menu-item {{ request()->routeIs('barangay-monitoring*') ? 'active' : '' }}" data-tooltip="Barangay Monitoring" data-no-loading>
            <i class="fas fa-map-marker-alt"></i><span>Barangay Monitoring</span>
        </a>

        <a href="{{ route('kabataan-monitoring') }}" class="menu-item {{ request()->routeIs('kabataan-monitoring*') ? 'active' : '' }}" data-tooltip="Kabataan Monitoring" data-no-loading>
            <i class="fas fa-users"></i><span>Kabataan Monitoring</span>
        </a>

        <a href="{{ route('barangay-logos.index') }}" class="menu-item {{ request()->routeIs('barangay-logos*') ? 'active' : '' }}" data-nav-key="barangay-logos" data-tooltip="Barangay Logos" data-no-loading>
            <i class="fas fa-image"></i><span>Barangay Logos</span>
        </a>

        <a href="{{ route('auditlogs.index') }}" class="menu-item {{ request()->routeIs('auditlogs*') ? 'active' : '' }}" data-tooltip="Audit Logs" data-no-loading>
            <i class="fas fa-clipboard-list"></i><span>Audit Logs</span>
        </a>

        <button type="button" class="menu-item menu-dropdown-toggle" data-submenu-toggle="accountsSubmenu" data-tooltip="Accounts" aria-expanded="{{ $accountsOpen ? 'true' : 'false' }}" onclick="toggleSubmenuDropdown(this, event)">
            <i class="fas fa-user-cog"></i><span>Manage Accounts</span>
            <i class="fas fa-chevron-down menu-dropdown-chevron {{ $accountsOpen ? 'is-open' : '' }}" id="accountsChevron"></i>
        </button>

        <div id="accountsSubmenu" class="sidebar-submenu {{ $accountsOpen ? 'is-open' : '' }}">
            <a href="{{ route('accounts.officials.index') }}" class="menu-item submenu-item submenu-item--plain {{ $isOfficialsAccountsActive ? 'active' : '' }}" data-no-loading>
                <span>SK Officials</span>
            </a>
            <a href="{{ route('accounts.federation.index') }}" class="menu-item submenu-item submenu-item--plain {{ $isFederationAccountsActive ? 'active' : '' }}" data-no-loading>
                <span>SK Federation</span>
            </a>
        </div>

        <button type="button" class="menu-item menu-dropdown-toggle" data-submenu-toggle="archiveManagementSubmenu" data-tooltip="Archive Management" aria-expanded="{{ $archiveManagementOpen ? 'true' : 'false' }}" onclick="toggleSubmenuDropdown(this, event)">
            <i class="fas fa-box-archive"></i><span>Archive Management</span>
            <i class="fas fa-chevron-down menu-dropdown-chevron {{ $archiveManagementOpen ? 'is-open' : '' }}" id="archiveManagementChevron"></i>
        </button>

        <div id="archiveManagementSubmenu" class="sidebar-submenu {{ $archiveManagementOpen ? 'is-open' : '' }}">
            <a href="{{ route('archived.deleted-sk-officials') }}" class="menu-item submenu-item submenu-item--plain {{ request()->routeIs('archived.deleted-sk-officials') ? 'active' : '' }}" data-no-loading>
                <span>Deleted SK Officials</span>
            </a>
            <a href="{{ route('archived.sk-federation-records') }}" class="menu-item submenu-item submenu-item--plain {{ request()->routeIs('archived.sk-federation-records') ? 'active' : '' }}" data-no-loading>
                <span>SK Federation Records</span>
            </a>
            <a href="{{ route('archived.sk-officials-records') }}" class="menu-item submenu-item submenu-item--plain {{ request()->routeIs('archived.sk-officials-records') ? 'active' : '' }}" data-no-loading>
                <span>SK Officials Records</span>
            </a>
            <a href="{{ route('archived.deleted-posts') }}" class="menu-item submenu-item submenu-item--plain {{ request()->routeIs('archived.deleted-posts*') ? 'active' : '' }}" data-no-loading>
                <span>Deleted Posts</span>
            </a>
        </div>

        <div class="menu-divider"></div>
    </nav>
</aside>
