{{-- ============================================================
     SK Officials Form — shared Add / Edit modal
     Add mode:  $mode = 'add'  (no $official passed)
     Edit mode: $mode = 'edit' (pass $official data via JS)
     ============================================================ --}}

{{-- ── ADD SK OFFICIALS MODAL ─────────────────────────────── --}}
<div id="addSkOfficialsModal" class="modal-overlay" style="display:none;">
    <div class="modal-content modal-large modal-light" id="addSkOfficialsModalContent">

        <div class="modal-header modal-header-blue-grad">
            <h3 class="modal-title">Add SK Official Account</h3>
            @include('accounts::modal_window_controls', [
                'resizeId' => 'addOfficialsResizeBtn',
                'resizeFn' => 'toggleAddOfficialsSize',
                'closeFn' => 'closeAddSkOfficialsModal',
            ])
        </div>

        <div class="modal-body modal-body-light account-modal-scroll">
            <div class="add-mode-switcher">
                <p class="add-mode-label">How do you want to add?</p>
                <div class="add-mode-tabs">
                    <button type="button" class="add-mode-tab active" id="tabManual" onclick="switchAddOfficialTab('manual')">Manual</button>
                    <button type="button" class="add-mode-tab" id="tabBatch" onclick="switchAddOfficialTab('batch')">Batch Upload</button>
                </div>
            </div>

            <div id="addOfficialManualPane">
            <form id="addSkOfficialsForm" class="sk-officials-form account-modal-form" novalidate>
                    @csrf
                    <input type="hidden" name="role" value="sk_official">
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
                                <input type="text" name="first_name" id="official_first_name" class="form-input-light input-uppercase" placeholder="FIRST NAME" maxlength="50" required autocomplete="off" style="text-transform:uppercase;">
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light">Middle Name</label>
                                <input type="text" name="middle_name" id="official_middle_name" class="form-input-light input-uppercase" placeholder="MIDDLE NAME" maxlength="50" autocomplete="off" style="text-transform:uppercase;">
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Last Name</label>
                                <input type="text" name="last_name" id="official_last_name" class="form-input-light input-uppercase" placeholder="LAST NAME" maxlength="50" required autocomplete="off" style="text-transform:uppercase;">
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Suffix</label>
                                <select name="suffix" id="official_suffix" class="form-input-light" required>
                                    <option value="" disabled selected hidden>Select Suffix</option>
                                    <option value="NONE">None</option>
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
                            <div class="form-group-light" id="official_suffix_other_group" style="display:none;">
                                <label class="form-label-light required">Other Suffix</label>
                                <input type="text" name="suffix_other" id="official_suffix_other" class="form-input-light input-uppercase" maxlength="4" placeholder="OTHER" autocomplete="off" style="text-transform:uppercase;">
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Sex</label>
                                <select name="sex" id="official_sex" class="form-input-light" required>
                                    <option value="" disabled selected>Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Birthdate (MM/DD/YYYY)</label>
                                <input type="date" name="date_of_birth" id="official_date_of_birth" class="form-input-light" required>
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Age</label>
                                <input type="number" name="age" id="official_age" class="form-input-light" min="15" max="30" inputmode="numeric" required>
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Contact Number</label>
                                <input type="tel" name="contact_number" id="official_contact_number" class="form-input-light" value="09" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" required>
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
                                <select name="position" id="official_position" class="form-input-light" required>
                                    <option value="" disabled selected>Select Position</option>
                                    @foreach(\App\Modules\Accounts\Models\OfficialProfile::officialPositionOptions() as $value => $label)
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
                                <input type="text" name="region" id="official_region" class="form-input-light" value="IV-A CALABARZON" readonly required>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Province</label>
                                <input type="text" name="province" id="official_province" class="form-input-light" value="Laguna" readonly required>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Municipality</label>
                                <input type="text" name="municipality" id="official_municipality" class="form-input-light" value="Santa Cruz" readonly required>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Barangay</label>
                                <select name="barangay_id" id="official_barangay_id" class="form-input-light" required>
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
                                <label class="form-label-light required">Term Start Date (MM/DD/YYYY)</label>
                                <input type="date" name="term_start" id="official_term_start" class="form-input-light" required>
                                <p class="form-hint-light">November 30, 12:00 noon (RA 11935). Use 2023-11-30 for the 2023 SK term — not December 1.</p>
                                <span class="form-error-light"></span>
                            </div>
                            <div class="form-group-light">
                                <label class="form-label-light required">Term End Date (MM/DD/YYYY)</label>
                                <input type="date" name="term_end" id="official_term_end" class="form-input-light" required>
                                <p class="form-hint-light">Term end must be between 3 and 4 years after the term start date.</p>
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
                                <input type="email" name="email" id="official_email" class="form-input-light" placeholder="example@gmail.com" maxlength="40" required autocomplete="off" style="text-transform:lowercase;">
                                <span class="form-error-light"></span>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <div id="addOfficialBatchPane" style="display:none;">
                @include('accounts::batch_upload_panel', ['prefix' => 'official', 'templateType' => 'officials'])
            </div>
        </div>
        <div class="modal-footer account-modal-footer" id="addOfficialManualFooter">
            <button type="button" class="btn-cancel-light" onclick="closeAddSkOfficialsModal()">Cancel</button>
            <button type="submit" form="addSkOfficialsForm" class="btn-submit-light">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                Create Account
            </button>
        </div>
        <div class="modal-footer account-modal-footer" id="addOfficialBatchFooter" style="display:none;">
            <button type="button" class="btn-cancel-light" onclick="closeAddSkOfficialsModal()">Cancel</button>
            <button type="button" class="btn-submit-light" id="official_batchConfirmBtn" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                Import Accounts
            </button>
        </div>
    </div>
</div>


{{-- ── EDIT SK OFFICIALS MODAL ─────────────────────────────── --}}
<div id="editSkOfficialsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content modal-large modal-light">
        <div class="modal-header modal-header-blue-grad">
            <h3 class="modal-title">Edit SK Officials Account</h3>
            @include('accounts::modal_window_controls', [
                'resizeId' => 'editOfficialsResizeBtn',
                'resizeFn' => 'toggleEditOfficialsSize',
                'closeFn' => 'closeEditSkOfficialsModal',
            ])
        </div>
        <div class="modal-body modal-body-light account-modal-scroll">
            <form id="editSkOfficialsForm" class="sk-officials-form account-modal-form" data-account-id="" novalidate>
                @csrf
                <input type="hidden" name="term_status" id="edit_sk_officials_term_status" value="ACTIVE">
                <input type="hidden" name="status" id="edit_sk_officials_status" value="{{ \App\Modules\Shared\Models\User::STATUS_ACTIVE }}">

                <div class="form-section-light">
                    <h4 class="section-title-light"><i class="fa-solid fa-user"></i> Personal Information</h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label for="edit_sk_officials_first_name" class="form-label-light required">First Name</label>
                            <input type="text" id="edit_sk_officials_first_name" name="first_name" class="form-input-light input-uppercase" required style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_last_name" class="form-label-light required">Last Name</label>
                            <input type="text" id="edit_sk_officials_last_name" name="last_name" class="form-input-light input-uppercase" required style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_middle_name" class="form-label-light">Middle Name</label>
                            <input type="text" id="edit_sk_officials_middle_name" name="middle_name" class="form-input-light input-uppercase" maxlength="100" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_suffix" class="form-label-light">Suffix</label>
                            <select id="edit_sk_officials_suffix" name="suffix" class="form-input-light">
                                <option value="NONE">None</option>
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
                        <div class="form-group-light" id="edit_sk_officials_suffix_other_group" style="display:none;">
                            <label for="edit_sk_officials_suffix_other" class="form-label-light required">Other Suffix</label>
                            <input type="text" id="edit_sk_officials_suffix_other" name="suffix_other" class="form-input-light input-uppercase" maxlength="4" style="text-transform:uppercase;">
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_sex" class="form-label-light required">Sex</label>
                            <select id="edit_sk_officials_sex" name="sex" class="form-input-light" required>
                                <option value="">Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_email" class="form-label-light required">Email Address</label>
                            <input type="email" id="edit_sk_officials_email" name="email" class="form-input-light" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_contact_number" class="form-label-light required">Contact Number</label>
                            <input type="text" id="edit_sk_officials_contact_number" name="contact_number" class="form-input-light" maxlength="11" placeholder="09XXXXXXXXX" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_date_of_birth" class="form-label-light required">Birthdate (MM/DD/YYYY)</label>
                            <input type="text" id="edit_sk_officials_date_of_birth" name="date_of_birth" class="form-input-light" placeholder="MM/DD/YYYY" inputmode="numeric" autocomplete="off" required>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_age" class="form-label-light required">Age</label>
                            <input type="number" id="edit_sk_officials_age" name="age" class="form-input-light" min="15" max="30" inputmode="numeric" required>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light"><i class="fa-solid fa-briefcase"></i> Position & Location</h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label for="edit_sk_officials_position" class="form-label-light required">Position</label>
                            <select id="edit_sk_officials_position" name="position" class="form-input-light" required>
                                <option value="">Select Position</option>
                                @foreach(\App\Modules\Accounts\Models\OfficialProfile::officialPositionOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_barangay_id" class="form-label-light required">Barangay</label>
                            <select id="edit_sk_officials_barangay_id" name="barangay_id" class="form-input-light" required>
                                <option value="">Select Barangay</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                                @endforeach
                            </select>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_municipality" class="form-label-light required">Municipality</label>
                            <input type="text" id="edit_sk_officials_municipality" class="form-input-light" value="Santa Cruz" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section-light">
                    <h4 class="section-title-light"><i class="fa-solid fa-calendar-check"></i> Term Information</h4>
                    <div class="form-grid">
                        <div class="form-group-light">
                            <label for="edit_sk_officials_term_start" class="form-label-light required">Term Start Date (MM/DD/YYYY)</label>
                            <input type="text" id="edit_sk_officials_term_start" name="term_start" class="form-input-light" placeholder="MM/DD/YYYY" inputmode="numeric" autocomplete="off" required>
                            <p class="form-hint-light">November 30, 12:00 noon (RA 11935). Use 2023-11-30 for the 2023 SK term — not December 1.</p>
                            <span class="form-error-light"></span>
                        </div>
                        <div class="form-group-light">
                            <label for="edit_sk_officials_term_end" class="form-label-light required">Term End Date (MM/DD/YYYY)</label>
                            <input type="text" id="edit_sk_officials_term_end" name="term_end" class="form-input-light" placeholder="MM/DD/YYYY" inputmode="numeric" autocomplete="off" required>
                            <p class="form-hint-light">Term end must be between 3 and 4 years after the term start date.</p>
                            <span class="form-error-light"></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer account-modal-footer">
            <button type="button" class="btn-cancel-light" onclick="closeEditSkOfficialsModal()">Cancel</button>
            <button type="submit" form="editSkOfficialsForm" class="btn-submit-light">Update Account</button>
        </div>
    </div>
</div>
