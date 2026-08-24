{{-- ============================================================
     SK Federation Form — shared Add / Edit modal
     Add mode:  opens #addAccountModal  (empty form)
     Edit mode: opens #editAccountModal (pre-filled via JS)
     ============================================================ --}}

{{-- ── ADD SK FEDERATION MODAL ─────────────────────────────── --}}
<div id="addAccountModal" class="modal-overlay" style="display:none;">
    <div class="modal-content modal-large modal-light" id="addSkFedModalContent">

        <div class="modal-header modal-header-blue-grad">
            <h3 class="modal-title">Add SK Federation Account</h3>
            @include('accounts::modal_window_controls', [
                'resizeId' => 'addFedResizeBtn',
                'resizeFn' => 'toggleAddFedSize',
                'closeFn' => 'closeAddAccountModal',
            ])
        </div>

        <div class="modal-body modal-body-light account-modal-scroll">
            <div class="add-mode-switcher">
                <p class="add-mode-label">How do you want to add?</p>
                <div class="add-mode-tabs">
                    <button type="button" class="add-mode-tab active" id="fedTabManual" onclick="switchAddFedTab('manual')">Manual</button>
                    <button type="button" class="add-mode-tab" id="fedTabBatch" onclick="switchAddFedTab('batch')">Batch Upload</button>
                </div>
            </div>

            <div id="addFedManualPane">
            <form id="addSkFedForm" class="sk-officials-form account-modal-form" novalidate>
                @csrf
                <input type="hidden" name="role" value="sk_fed">
                <input type="hidden" name="term_status" value="ACTIVE">
                <input type="hidden" name="status" value="{{ \App\Modules\Shared\Models\User::STATUS_ACTIVE }}">

                <div class="form-section-light">
                    <h4 class="section-title-light">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px;">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Personal Information
                    </h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label class="form-label-light required">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input-light input-uppercase" placeholder="FIRST NAME" required autocomplete="off" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="form-input-light input-uppercase" placeholder="MIDDLE NAME" maxlength="100" autocomplete="off" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input-light input-uppercase" placeholder="LAST NAME" required autocomplete="off" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light">Suffix</label>
                            <select id="suffix" name="suffix" class="form-input-light">
                                <option value="" disabled selected>Select Suffix</option>
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                                <option value="__other__">Other Suffix</option>
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light" id="fed_suffix_other_group" style="display:none;">
                            <label class="form-label-light required">Other Suffix</label>
                            <input type="text" name="suffix_other" id="fed_suffix_other" class="form-input-light input-uppercase" maxlength="10" placeholder="OTHER" autocomplete="off" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Sex</label>
                            <select id="sex" name="sex" class="form-input-light" required>
                                <option value="" disabled selected>Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Birthdate</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Age</label>
                            <input type="number" id="age" name="age" class="form-input-light" min="0" max="150" readonly>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-input-light" value="09" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" required>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px;">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        Position Information
                    </h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label class="form-label-light required">Position</label>
                            <select id="position" name="position" class="form-input-light" required>
                                <option value="" disabled selected>Select Position</option>
                                @foreach(\App\Modules\Accounts\Models\OfficialProfile::federationPositionOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px;">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        Address
                    </h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label class="form-label-light required">Region</label>
                            <input type="text" id="region" name="region" class="form-input-light" value="IV-A CALABARZON" readonly required>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Province</label>
                            <input type="text" id="province" name="province" class="form-input-light" value="Laguna" readonly required>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Municipality</label>
                            <input type="text" id="municipality" name="municipality" class="form-input-light" value="Santa Cruz" readonly required>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Barangay</label>
                            <select id="barangay_id" name="barangay_id" class="form-input-light" required>
                                <option value="" disabled selected>Select Barangay</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                                @endforeach
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px;">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Term
                    </h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label class="form-label-light required">Term Start</label>
                            <input type="date" id="term_start" name="term_start" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label class="form-label-light required">Term End</label>
                            <input type="date" id="term_end" name="term_end" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px;">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Account
                    </h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label class="form-label-light required">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input-light" placeholder="Email Address" required>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

            </form>
            </div>

            <div id="addFedBatchPane" style="display:none;">
                @include('accounts::batch_upload_panel', ['prefix' => 'fed', 'templateType' => 'federation'])
            </div>
        </div>
        <div class="modal-footer account-modal-footer" id="addFedManualFooter">
            <button type="button" class="btn-cancel-light" onclick="closeAddAccountModal()">Cancel</button>
            <button type="submit" form="addSkFedForm" class="btn-submit-light">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                Create Account
            </button>
        </div>
        <div class="modal-footer account-modal-footer" id="addFedBatchFooter" style="display:none;">
            <button type="button" class="btn-cancel-light" onclick="closeAddAccountModal()">Cancel</button>
            <button type="button" class="btn-submit-light" id="fed_batchConfirmBtn" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                Import Accounts
            </button>
        </div>
    </div>
</div>

{{-- ── EDIT SK FEDERATION MODAL ─────────────────────────────── --}}
<div id="editAccountModal" class="modal-overlay" style="display: none;">
    <div class="modal-content modal-large modal-light">
        <div class="modal-header modal-header-blue-grad">
            <h3 class="modal-title">Edit SK Federation Account</h3>
            @include('accounts::modal_window_controls', [
                'resizeId' => 'editFedResizeBtn',
                'resizeFn' => 'toggleEditFedSize',
                'closeFn' => 'closeEditModal',
            ])
        </div>
        <div class="modal-body modal-body-light account-modal-scroll">
            <form id="editAccountForm" class="sk-officials-form account-modal-form" data-account-id="" novalidate>
                @csrf
                <input type="hidden" name="term_status" id="edit_term_status" value="ACTIVE">
                <input type="hidden" name="status" id="edit_status" value="{{ \App\Modules\Shared\Models\User::STATUS_ACTIVE }}">

                <div class="form-section-light">
                    <h4 class="section-title-light"><i class="fa-solid fa-user"></i> Personal Information</h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label for="edit_first_name" class="form-label-light required">First Name</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-input-light input-uppercase" required style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_last_name" class="form-label-light required">Last Name</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-input-light input-uppercase" required style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_middle_name" class="form-label-light">Middle Name</label>
                            <input type="text" id="edit_middle_name" name="middle_name" class="form-input-light input-uppercase" maxlength="100" placeholder="e.g., Marie" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_suffix" class="form-label-light">Suffix</label>
                            <select id="edit_suffix" name="suffix" class="form-input-light">
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                                <option value="__other__">Other Suffix</option>
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light" id="edit_fed_suffix_other_group" style="display:none;">
                            <label for="edit_fed_suffix_other" class="form-label-light required">Other Suffix</label>
                            <input type="text" name="suffix_other" id="edit_fed_suffix_other" class="form-input-light input-uppercase" maxlength="10" placeholder="OTHER" autocomplete="off" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sex" class="form-label-light required">Sex</label>
                            <select id="edit_sex" name="sex" class="form-input-light" required>
                                <option value="">Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_email" class="form-label-light required">Email Address</label>
                            <input type="email" id="edit_email" name="email" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_contact_number" class="form-label-light required">Contact Number</label>
                            <input type="text" id="edit_contact_number" name="contact_number" class="form-input-light" maxlength="20" placeholder="09XXXXXXXXX" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_date_of_birth" class="form-label-light required">Date of Birth</label>
                            <input type="date" id="edit_date_of_birth" name="date_of_birth" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_age" class="form-label-light required">Age</label>
                            <input type="number" id="edit_age" name="age" class="form-input-light" min="0" max="150" readonly>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light"><i class="fa-solid fa-location-dot"></i> Address</h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label for="edit_region" class="form-label-light required">Region</label>
                            <input type="text" id="edit_region" class="form-input-light" value="IV-A CALABARZON" readonly>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_province" class="form-label-light required">Province</label>
                            <input type="text" id="edit_province" class="form-input-light" value="Laguna" readonly>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_municipality" class="form-label-light required">Municipality</label>
                            <input type="text" id="edit_municipality" class="form-input-light" value="Santa Cruz" readonly>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_barangay_id" class="form-label-light required">Barangay</label>
                            <select id="edit_barangay_id" name="barangay_id" class="form-input-light" required>
                                <option value="">Select Barangay</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                                @endforeach
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light"><i class="fa-solid fa-briefcase"></i> Position & Term</h4>
                    <div class="form-grid">
                        <div class="form-group-light" id="edit_fed_position_group">
                            <label for="edit_position" class="form-label-light required">Federation Position</label>
                            <select id="edit_position" name="position" class="form-input-light" required>
                                <option value="">Select Position</option>
                                @foreach(\App\Modules\Accounts\Models\OfficialProfile::federationPositionOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light" id="edit_federation_position_group" style="display:none;">
                            <label for="edit_federation_position" class="form-label-light">Assign Federation Position</label>
                            <select id="edit_federation_position" name="federation_position" class="form-input-light">
                                <option value="">Not assigned yet</option>
                                @foreach(\App\Modules\Accounts\Models\OfficialProfile::federationPositionOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="form-hint-light">This SK Chairperson is listed here automatically. Assign their federation role when ready.</p>
                            <input type="hidden" id="edit_chair_position" name="position" value="" disabled>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_term_start" class="form-label-light required">Term Start</label>
                            <input type="date" id="edit_term_start" name="term_start" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_term_end" class="form-label-light required">Term End</label>
                            <input type="date" id="edit_term_end" name="term_end" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer account-modal-footer">
            <button type="button" class="btn-cancel-light" onclick="closeEditModal()">Cancel</button>
            <button type="submit" form="editAccountForm" class="btn-submit-light">Update Account</button>
        </div>
    </div>
</div>
