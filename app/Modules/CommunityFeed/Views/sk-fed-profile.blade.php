@extends('layout::app')

@section('title', 'SK Federation Profile')

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/community-feed/css/community-feed.css') }}">
@endpush

@push('navbar-center')
    <div class="navbar-search">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search..." aria-label="Search">
        </div>
@endpush

@section('content')
{{-- HEADER CARD --}}
        <div class="skfp-header-card">
            <div class="skfp-cover"></div>
            <div class="skfp-info-section">
                <div class="skfp-avatar-wrap">
                    <img src="{{ asset('Images/SK_OnePortal.png') }}" alt="SK Fed" class="skfp-avatar">
                    <button class="skfp-avatar-edit" onclick="openEditModal()" title="Change photo"><i class="fas fa-camera"></i></button>
                </div>
                <div class="skfp-meta">
                    <h1 class="skfp-name">SK Federation Santa Cruz</h1>
                    <p class="skfp-sub">Sangguniang Kabataan Federation · Santa Cruz, Laguna</p>
                    <div class="skfp-stats">
                        <div class="skfp-stat"><strong>26</strong><span>Barangays</span></div>
                        <div class="skfp-stat"><strong>12</strong><span>Officers</span></div>
                        <div class="skfp-stat"><strong>2023–2026</strong><span>SK Term</span></div>
                    </div>
                </div>
                <div class="skfp-actions">
                    <button class="skfp-btn-primary" onclick="openPostModal()"><i class="fas fa-plus"></i> Create Post</button>
                    <button class="skfp-btn-ghost" onclick="openEditModal()"><i class="fas fa-edit"></i> Edit Profile</button>
                </div>
            </div>
        </div>

        @if(session('success'))
        <div class="skfp-content-wrap" style="padding-bottom:0;">
        <div style="background:#dcfce7;color:#15803d;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:600;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        </div>
        @endif

        {{-- CONTENT GRID --}}
        <div class="skfp-content-wrap">
        <div class="skfp-grid">

            {{-- LEFT --}}
            <div class="skfp-left">

                {{-- About --}}
                <div class="skfp-card">
                    <div class="skfp-card-title"><i class="fas fa-info-circle"></i> About</div>
                    <div class="skfp-info-row">
                        <div class="skfp-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div><p class="skfp-info-label">Municipality</p><p class="skfp-info-value">Santa Cruz, Laguna</p></div>
                    </div>
                    <div class="skfp-info-row">
                        <div class="skfp-info-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div><p class="skfp-info-label">SK Term</p><p class="skfp-info-value">2023 – 2026</p></div>
                    </div>
                    <div class="skfp-info-row">
                        <div class="skfp-info-icon"><i class="fas fa-phone"></i></div>
                        <div><p class="skfp-info-label">Contact</p><p class="skfp-info-value">[SK Fed Contact Number]</p></div>
                    </div>
                    <div class="skfp-info-row">
                        <div class="skfp-info-icon"><i class="fas fa-envelope"></i></div>
                        <div><p class="skfp-info-label">Email</p><p class="skfp-info-value">[SK Fed Email]</p></div>
                    </div>
                    <div class="skfp-info-row">
                        <div class="skfp-info-icon"><i class="fas fa-clock"></i></div>
                        <div><p class="skfp-info-label">Office Hours</p><p class="skfp-info-value">Mon–Fri, 8:00 AM – 5:00 PM</p></div>
                    </div>
                    <div class="skfp-info-row">
                        <div class="skfp-info-icon"><i class="fas fa-building"></i></div>
                        <div><p class="skfp-info-label">Office Address</p><p class="skfp-info-value">Santa Cruz Municipal Hall, Laguna</p></div>
                    </div>
                </div>

                {{-- Officers --}}
                <div class="skfp-card">
                    <div class="skfp-card-title"><i class="fas fa-users"></i> SK Federation Officers</div>
                    @php
                    $officers = [
                        ['name'=>'[SK Federation Chairman]','role'=>'Chairman'],
                        ['name'=>'[Vice Chairman]','role'=>'Vice Chairman'],
                        ['name'=>'[Secretary]','role'=>'Secretary'],
                        ['name'=>'[Treasurer]','role'=>'Treasurer'],
                        ['name'=>'[Auditor]','role'=>'Auditor'],
                        ['name'=>'[PRO]','role'=>'Public Relations Officer'],
                    ];
                    @endphp
                    @foreach($officers as $o)
                    <div class="skfp-officer-item">
                        <div class="skfp-officer-dot">{{ strtoupper(substr(trim($o['name'],'[]'),0,2)) }}</div>
                        <div>
                            <p class="skfp-officer-name">{{ $o['name'] }}</p>
                            <p class="skfp-officer-role">{{ $o['role'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>

            {{-- RIGHT --}}
            <div class="skfp-right">

                {{-- Compose --}}
                <div class="skfp-compose">
                    <div class="skfp-compose-row">
                        <img src="{{ asset('Images/SK_OnePortal.png') }}" alt="SK Fed" class="skfp-post-avatar">
                        <input type="text" class="skfp-compose-input" placeholder="What's happening in SK Federation?" readonly onclick="openPostModal()">
                    </div>
                    <div class="skfp-compose-actions">
                        <button class="skfp-compose-type" onclick="openPostModal('announcement')"><i class="fas fa-bullhorn"></i> Announcement</button>
                        <button class="skfp-compose-type" onclick="openPostModal('event')"><i class="fas fa-calendar"></i> Event</button>
                        <button class="skfp-compose-type" onclick="openPostModal('activity')"><i class="fas fa-running"></i> Activity</button>
                        <button class="skfp-compose-type" onclick="openPostModal('program')"><i class="fas fa-tasks"></i> Program</button>
                    </div>
                </div>

                {{-- Posts --}}
                <div class="skfp-post">
                    <div class="skfp-post-header">
                        <img src="{{ asset('Images/SK_OnePortal.png') }}" alt="SK Fed" class="skfp-post-avatar">
                        <div>
                            <p class="skfp-post-author">SK Federation Santa Cruz</p>
                            <p class="skfp-post-meta"><span class="skfp-post-type announcement">Announcement</span> Mar 16, 2026 · 9:00 AM</p>
                        </div>
                        <button class="skfp-post-edit-btn" style="margin-left:auto;" onclick="openEditPostModal()"><i class="fas fa-edit"></i> Edit</button>
                    </div>
                    <h3 class="skfp-post-title">Quarterly Assembly — April 5, 2026</h3>
                    <p class="skfp-post-text">The SK Federation Santa Cruz will hold its quarterly assembly on April 5, 2026 at the Municipal Hall. All SK officials are required to attend. Please confirm your attendance by March 30.</p>
                    <div class="skfp-post-actions">
                        <button class="skfp-post-btn"><i class="fas fa-thumbs-up"></i> Like (24)</button>
                        <button class="skfp-post-btn"><i class="fas fa-comment"></i> Comment (3)</button>
                        <button class="skfp-post-btn"><i class="fas fa-share"></i> Share</button>
                    </div>
                </div>

                <div class="skfp-post">
                    <div class="skfp-post-header">
                        <img src="{{ asset('Images/SK_OnePortal.png') }}" alt="SK Fed" class="skfp-post-avatar">
                        <div>
                            <p class="skfp-post-author">SK Federation Santa Cruz</p>
                            <p class="skfp-post-meta"><span class="skfp-post-type announcement">Announcement</span> Mar 10, 2026 · 8:00 AM</p>
                        </div>
                        <button class="skfp-post-edit-btn" style="margin-left:auto;" onclick="openEditPostModal()"><i class="fas fa-edit"></i> Edit</button>
                    </div>
                    <h3 class="skfp-post-title">Q1 2026 Report Submission Deadline</h3>
                    <p class="skfp-post-text">Reminder: Submission of Barangay Program Reports for Q1 2026 is due on March 31. Please coordinate with your respective SK Chairpersons.</p>
                    <div class="skfp-post-actions">
                        <button class="skfp-post-btn"><i class="fas fa-thumbs-up"></i> Like (15)</button>
                        <button class="skfp-post-btn"><i class="fas fa-comment"></i> Comment (0)</button>
                        <button class="skfp-post-btn"><i class="fas fa-share"></i> Share</button>
                    </div>
                </div>

            </div>
        </div>
        </div>{{-- end skfp-content-wrap --}}
@endsection

@push('scripts')
{{-- CREATE POST MODAL --}}
    <div class="skfp-modal-wrap" id="postModal">
        <div class="skfp-modal-overlay" onclick="closePostModal()"></div>
        <div class="skfp-modal-box">
            <div class="skfp-modal-header">
                <h3>Create Post</h3>
                <button class="skfp-modal-close" onclick="closePostModal()"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="{{ route('sk-fed-profile.post') }}">
                @csrf
                <div class="skfp-modal-body">
                    <div class="skfp-form-group">
                        <label>Post Type</label>
                        <select name="type" id="postTypeSelect">
                            <option value="announcement">Announcement</option>
                            <option value="event">Event</option>
                            <option value="activity">Activity</option>
                            <option value="program">Program</option>
                        </select>
                    </div>
                    <div class="skfp-form-group">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="Post title..." required>
                    </div>
                    <div class="skfp-form-group">
                        <label>Content</label>
                        <textarea name="content" rows="4" placeholder="What's happening?" required></textarea>
                    </div>
                    <div class="skfp-form-row">
                        <div class="skfp-form-group">
                            <label>Date</label>
                            <input type="date" name="event_date">
                        </div>
                        <div class="skfp-form-group">
                            <label>Time</label>
                            <input type="time" name="event_time">
                        </div>
                    </div>
                    <div class="skfp-form-group">
                        <label>Venue / Location</label>
                        <input type="text" name="venue" placeholder="e.g. Municipal Hall">
                    </div>
                </div>
                <div class="skfp-modal-footer">
                    <button type="button" class="skfp-btn-ghost" onclick="closePostModal()">Cancel</button>
                    <button type="submit" class="skfp-btn-primary"><i class="fas fa-paper-plane"></i> Publish Post</button>
                </div>
            </form>
        </div>
    </div>

    {{-- EDIT PROFILE MODAL --}}
    <div class="skfp-modal-wrap" id="editModal">
        <div class="skfp-modal-overlay" onclick="closeEditModal()"></div>
        <div class="skfp-modal-box">
            <div class="skfp-modal-header">
                <h3>Edit SK Federation Profile</h3>
                <button class="skfp-modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="skfp-modal-body">
                <div class="skfp-form-group">
                    <label>Federation Name</label>
                    <input type="text" value="SK Federation Santa Cruz">
                </div>
                <div class="skfp-form-row">
                    <div class="skfp-form-group">
                        <label>Municipality</label>
                        <input type="text" value="Santa Cruz" readonly>
                    </div>
                    <div class="skfp-form-group">
                        <label>Province</label>
                        <input type="text" value="Laguna" readonly>
                    </div>
                </div>
                <div class="skfp-form-group">
                    <label>Contact Number</label>
                    <input type="text" placeholder="[SK Fed Contact Number]">
                </div>
                <div class="skfp-form-group">
                    <label>Email Address</label>
                    <input type="email" placeholder="[SK Fed Email]">
                </div>
                <div class="skfp-form-group">
                    <label>Office Address</label>
                    <input type="text" value="Santa Cruz Municipal Hall, Laguna">
                </div>
                <div class="skfp-form-group">
                    <label>About / Description</label>
                    <textarea rows="3" placeholder="Brief description of the SK Federation..."></textarea>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin-top:4px;">Note: Profile editing is in prototype mode. Changes will not be saved to the database.</p>
            </div>
            <div class="skfp-modal-footer">
                <button type="button" class="skfp-btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="skfp-btn-primary" onclick="closeEditModal()"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </div>
    </div>

    {{-- LOGOUT MODAL --}}
    <div class="modal-overlay-logout" id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;padding:32px 28px;max-width:380px;width:90%;text-align:center;">
            <div style="width:56px;height:56px;background:#fff3e0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;"><i class="fas fa-sign-out-alt" style="color:#f97316;font-size:22px;"></i></div>
            <h3 style="font-size:18px;color:#0d1b4b;margin-bottom:8px;">Confirm Logout</h3>
            <p style="color:#64748b;font-size:14px;margin-bottom:24px;">Are you sure you want to logout?</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="hideLogoutModal()" style="padding:10px 24px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;color:#64748b;font-weight:600;cursor:pointer;">Cancel</button>
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" style="padding:10px 24px;background:linear-gradient(135deg,#f44336,#d32f2f);color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Logout</button>
                </form>
            </div>
        </div>
    </div>
<script src="{{ url('/shared/js/loading.js') }}"></script>
    <script>
    function openPostModal(type) {
        if (type) document.getElementById('postTypeSelect').value = type;
        document.getElementById('postModal').classList.add('active');
    }
    function closePostModal() { document.getElementById('postModal').classList.remove('active'); }
    function openEditModal()  { document.getElementById('editModal').classList.add('active'); }
    function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }
    function openEditPostModal() { openEditModal(); }
    function showLogoutModal() { const m = document.getElementById('logoutModal'); m.style.display = 'flex'; }
    function hideLogoutModal() { const m = document.getElementById('logoutModal'); m.style.display = 'none'; }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closePostModal(); closeEditModal(); hideLogoutModal(); }
    });
    </script>
@endpush
