{{-- Add / Edit Kabataan modal — KK Profiling Step 1 questionnaire (same as Kabataan KK Profiling) --}}
<div class="modal-backdrop kabataan-modal-backdrop" id="kabataanAddModal" style="display:none;">
    <div class="modal-box kabataan-modal-box">
        <div class="modal-header">
            <h2 class="modal-title" id="kabataanAddModalTitle">Add KK Record</h2>
            <div class="modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="kabataanAddMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="kabataanAddRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="modal-close" data-add-modal-close aria-label="Close">&times;</button>
            </div>
        </div>

        <div class="km-add-mode-switcher" id="kmAddModeSwitcher">
            <div class="km-add-mode-tabs" role="tablist" aria-label="Add record mode">
                <button type="button" class="km-add-mode-tab active" id="kmTabManual" role="tab" aria-selected="true">Manual</button>
                <button type="button" class="km-add-mode-tab" id="kmTabBatch" role="tab" aria-selected="false">Batch Upload</button>
            </div>
        </div>

        <div class="modal-body kabataan-modal-body">
            <div class="kabataan-mode-panel kabataan-panel-manual km-add-mode-pane" id="kabataanPanelManual">

                    {{-- PAPER FORM HEADER --}}
                    <div class="kkp-form-header">
                        <div class="kkp-form-main-title">KK Survey Questionnaire</div>
                        <div class="kkp-form-header-right">
                            <div class="kkp-form-annex">ANNEX 3</div>
                            <div class="kkp-form-header-fields">
                                <div class="kkp-hdr-field kkp-hdr-field--respondent">
                                    <span class="kkp-hdr-label">Respondent #:</span>
                                    <input type="text" id="kabataanRespondentNumber" class="kkp-hdr-input kkp-hdr-input-readonly kkp-hdr-input-auto" value="Auto-assigned on save" readonly tabindex="-1">
                                </div>
                                <div class="kkp-hdr-field">
                                    <label class="kkp-hdr-label" for="kabataanDate">Date:</label>
                                    <input type="text" id="kabataanDate" class="kkp-hdr-input kkp-hdr-input-readonly" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TO THE RESPONDENT NOTICE --}}
                    <div class="kkf-notice-box">
                        <div class="kkf-notice-title">TO THE RESPONDENT:</div>
                        <p class="kkf-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. We would like to ask your participation by taking your time to answer this questionnaire. Please read the questions carefully and answer them accurately.</p>
                        <p class="kkf-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>
                    </div>

                    {{-- I. PROFILE --}}
                    <div class="kkf-section-title">I. Profile</div>

                    {{-- Name Row --}}
                    <div class="kkf-row-label">Name of Respondent:</div>
                    <div class="kkf-name-row">
                        <div class="kkf-name-col">
                            <input type="text" id="kabataanLastName" class="kkf-uline" placeholder=" " required>
                            <label for="kabataanLastName" class="kkf-col-label">Last Name *</label>
                        </div>
                        <div class="kkf-name-col">
                            <input type="text" id="kabataanFirstName" class="kkf-uline" placeholder=" " required>
                            <label for="kabataanFirstName" class="kkf-col-label">First Name *</label>
                        </div>
                        <div class="kkf-name-col">
                            <input type="text" id="kabataanMiddleName" class="kkf-uline" placeholder=" " required>
                            <label for="kabataanMiddleName" class="kkf-col-label">Middle Name *</label>
                        </div>
                        <div class="kkf-name-col kkf-name-col--sm">
                            <select id="kabataanSuffix" class="kkf-uline kkf-uline-select">
                                <option value="None">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="I">I</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                                <option value="Others">Others</option>
                            </select>
                            <label for="kabataanSuffix" class="kkf-col-label">Suffix</label>
                            <div class="kkp-custom-suffix-wrap" id="kabataanCustomSuffixWrap">
                                <input type="text" id="kabataanCustomSuffix" class="kkf-uline" placeholder="Please specify suffix" maxlength="5" autocomplete="off">
                                <label for="kabataanCustomSuffix" class="kkf-col-label">Please specify suffix</label>
                            </div>
                        </div>
                    </div>

                    {{-- Location Row --}}
                    <div class="kkf-row-label">Location:</div>
                    <div class="kkf-loc-row">
                        <div class="kkf-loc-col">
                            <input type="text" id="kabataanRegion" class="kkf-uline" placeholder=" ">
                            <label for="kabataanRegion" class="kkf-col-label">Region</label>
                        </div>
                        <div class="kkf-loc-col">
                            <input type="text" id="kabataanProvince" class="kkf-uline" placeholder=" ">
                            <label for="kabataanProvince" class="kkf-col-label">Province</label>
                        </div>
                        <div class="kkf-loc-col">
                            <input type="text" id="kabataanCity" class="kkf-uline" placeholder=" ">
                            <label for="kabataanCity" class="kkf-col-label">City/Municipality</label>
                        </div>
                        <div class="kkf-loc-col">
                            <input type="text" id="kabataanBarangay" class="kkf-uline" placeholder=" ">
                            <label for="kabataanBarangay" class="kkf-col-label">Barangay</label>
                        </div>
                        <div class="kkf-loc-col">
                            <select id="kabataanPurokZone" class="kkf-uline kkf-uline-select">
                                <option value="">Select</option>
                                @include('layout::partials.barangay-zone-options')
                            </select>
                            <label for="kabataanPurokZone" class="kkf-col-label">Purok/Zone</label>
                        </div>
                    </div>

                    {{-- Personal Info Row: Sex | Age + Birthday | Email + Contact --}}
                    <div class="kkp-personal-row">
                        <div class="kkp-personal-left">
                            <div class="kkp-sex-block">
                                <div class="kkp-sex-label-box">Sex Assigned by Birth:</div>
                                <div class="kkp-sex-options">
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanSexChk" value="Male" onchange="kkfSingleCheck(this,'kabataanSex')"> Male</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanSexChk" value="Female" onchange="kkfSingleCheck(this,'kabataanSex')"> Female</label>
                                </div>
                                <input type="hidden" id="kabataanSex" value="">
                            </div>
                        </div>
                        <div class="kkp-personal-center">
                            <div class="kkp-age-dob-row">
                                <div class="kkp-inline-pair kkp-inline-pair--age">
                                    <label class="kkp-inline-label" for="kabataanAgeInput">Age:</label>
                                    <div class="kkp-age-select-box">
                                        <select id="kabataanAgeInput" class="kkp-uline kkp-uline-select kkp-uline-age kkp-age-select-compact">
                                            <option value="" disabled selected>Select</option>
                                            @for ($ageOption = 15; $ageOption <= 30; $ageOption++)
                                                <option value="{{ $ageOption }}">{{ $ageOption }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="kkp-inline-pair kkp-inline-pair--birthday">
                                    <label class="kkp-inline-label" for="kabataanDob">Birthday:</label>
                                    <div class="kkp-birthday-field">
                                        <input type="date" id="kabataanDob" class="kkp-uline kkp-uline-med">
                                        <span class="kkp-hint kkp-birthday-hint">(mm/dd/yyyy)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kkp-personal-right">
                            <div class="kkp-inline-pair kkp-inline-pair--email">
                                <label class="kkp-inline-label" for="kabataanEmail">E-mail address: <span class="kkp-optional-label">(Optional)</span></label>
                                <input type="email" id="kabataanEmail" class="kkp-uline kkp-uline-med kkp-email-input" placeholder=" " maxlength="254" autocomplete="email">
                            </div>
                            <div class="kkp-inline-pair">
                                <label class="kkp-inline-label" for="kabataanContactInput">Contact #:</label>
                                <input type="tel" id="kabataanContactInput" class="kkp-uline kkp-uline-med" placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11">
                            </div>
                        </div>
                    </div>

                    {{-- II. DEMOGRAPHIC CHARACTERISTICS --}}
                    <div class="kkf-section-title">II. Demographic Characteristics</div>
                    <p class="kkf-instruction">Please put a Check mark next to the word or Phrase that matches your response.</p>

                    <div class="kkf-demo-grid">
                        {{-- LEFT COLUMN --}}
                        <div class="kkf-demo-col">
                            <div class="kkf-demo-block">
                                <div class="kkf-demo-block-label">Civil Status</div>
                                <div class="kkf-demo-block-options">
                                    <div class="kkf-demo-options-2col">
                                        <div>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Single" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Single</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Married" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Married</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Widowed" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Widowed</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Divorced" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Divorced</label>
                                        </div>
                                        <div>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Separated" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Separated</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Annulled" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Annulled</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Unknown" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Unknown</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanCivilStatus" value="Live-in" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Live-in</label>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="kabataanCivilStatus" value="">
                            </div>

                            <div class="kkf-demo-block">
                                <div class="kkf-demo-block-label">Youth Age Group</div>
                                <div class="kkf-demo-block-options">
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthAgeGroup" value="Child Youth (15-17 yrs old)" onchange="kkfSingleCheck(this,'kabataanYouthAgeGroup')"> Child Youth (15-17 yrs old)</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthAgeGroup" value="Core Youth (18-24 yrs old)" onchange="kkfSingleCheck(this,'kabataanYouthAgeGroup')"> Core Youth (18-24 yrs old)</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthAgeGroup" value="Young Adult (15-30 yrs old)" onchange="kkfSingleCheck(this,'kabataanYouthAgeGroup')"> Young Adult (15-30 yrs old)</label>
                                </div>
                                <input type="hidden" id="kabataanYouthAgeGroup" value="">
                            </div>

                            <div class="kkf-demo-block">
                                <div class="kkf-demo-block-label">Educational Background</div>
                                <div class="kkf-demo-block-options">
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Elementary Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Elementary Level</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Elementary Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Elementary Grad</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="High School Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> High school level</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="High School Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> High school Grad</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Vocational Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Vocational Grad</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="College Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> College Level</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="College Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> College Grad</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Masters Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Masters Level</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Masters Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Masters Grad</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Doctorate Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Doctorate Level</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanEducationalBackground" value="Doctorate Graduate" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Doctorate Graduate</label>
                                </div>
                                <input type="hidden" id="kabataanEducationalBackground" value="">
                            </div>
                        </div>
                        {{-- RIGHT COLUMN --}}
                        <div class="kkf-demo-col">
                            <div class="kkf-demo-block">
                                <div class="kkf-demo-block-label">Youth<br>Classification</div>
                                <div class="kkf-demo-block-options kkp-youth-class-layout">
                                    <div class="kkp-youth-class-main">
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="In School Youth" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> In school Youth</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="Out of School Youth" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Out of School Youth</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="Working Youth" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Working Youth</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="Youth w/ Specific Needs" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Youth w/ Specific Needs:</label>
                                    </div>
                                    <div class="kkp-youth-class-side" aria-label="Youth with specific needs options">
                                        <span class="kkp-youth-specific-brace" aria-hidden="true">{</span>
                                        <div class="kkp-youth-specific-options">
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="Person w/ Disability" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Person w/ Disability</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="Children in Conflict w/ Law" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Children In Conflict w/ Law</label>
                                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanYouthClassification" value="Indigenous People" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Indigenous People</label>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="kabataanYouthClassification" value="">
                            </div>

                            <div class="kkf-demo-block">
                                <div class="kkf-demo-block-label">Work Status</div>
                                <div class="kkf-demo-block-options">
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanWorkStatus" value="Employed" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Employed</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanWorkStatus" value="Unemployed" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Unemployed</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanWorkStatus" value="Self-Employed" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Self-Employed</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanWorkStatus" value="Currently looking for a Job" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Currently looking for a Job</label>
                                    <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanWorkStatus" value="Not Interested Looking for a Job" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Not Interested Looking for a Job</label>
                                </div>
                                <input type="hidden" id="kabataanWorkStatus" value="">
                            </div>

                            {{-- Voter / Civic Participation inline rows --}}
                            <div class="kkf-voter-section">
                                {{-- Row 1: SK Voter | Vote Last SK | How many times --}}
                                <div class="kkf-voter-row">
                                    <div class="kkf-voter-cell">
                                        <div class="kkf-voter-cell-label">Registered SK Voter?</div>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanRegisteredSKVoter" value="Yes" onchange="kkfSingleCheck(this,'kabataanRegisteredSKVoter')"> Yes</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanRegisteredSKVoter" value="No" onchange="kkfSingleCheck(this,'kabataanRegisteredSKVoter')"> No</label>
                                        <input type="hidden" id="kabataanRegisteredSKVoter" value="">
                                    </div>
                                    <div class="kkf-voter-cell">
                                        <div class="kkf-voter-cell-label">Did you vote last SK?</div>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingHistory" value="Yes" onchange="kkfSingleCheck(this,'kabataanVotingHistory')"> Yes</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingHistory" value="No" onchange="kkfSingleCheck(this,'kabataanVotingHistory')"> No</label>
                                        <input type="hidden" id="kabataanVotingHistory" value="">
                                    </div>
                                    <div class="kkf-voter-cell">
                                        <div class="kkf-voter-cell-label">If Yes, How many times?</div>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingFrequency" value="1-2 Times" onchange="kkfSingleCheck(this,'kabataanVotingFrequency')"> 1-2 Times</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingFrequency" value="3-4 Times" onchange="kkfSingleCheck(this,'kabataanVotingFrequency')"> 3-4 Times</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingFrequency" value="5 and above" onchange="kkfSingleCheck(this,'kabataanVotingFrequency')"> 5 and above</label>
                                        <input type="hidden" id="kabataanVotingFrequency" value="">
                                    </div>
                                </div>
                                {{-- Row 2: National Voter | KK Assembly | If No Why --}}
                                <div class="kkf-voter-row">
                                    <div class="kkf-voter-cell">
                                        <div class="kkf-voter-cell-label">Registered National Voter?</div>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanRegisteredNationalVoter" value="Yes" onchange="kkfSingleCheck(this,'kabataanRegisteredNationalVoter')"> Yes</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanRegisteredNationalVoter" value="No" onchange="kkfSingleCheck(this,'kabataanRegisteredNationalVoter')"> No</label>
                                        <input type="hidden" id="kabataanRegisteredNationalVoter" value="">
                                    </div>
                                    <div class="kkf-voter-cell">
                                        <div class="kkf-voter-cell-label">Have you attended a KK Assembly?</div>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanAttendedKKAssembly" value="Yes" onchange="kkfSingleCheck(this,'kabataanAttendedKKAssembly')"> Yes</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanAttendedKKAssembly" value="No" onchange="kkfSingleCheck(this,'kabataanAttendedKKAssembly')"> No</label>
                                        <input type="hidden" id="kabataanAttendedKKAssembly" value="">
                                    </div>
                                    <div class="kkf-voter-cell">
                                        <div class="kkf-voter-cell-label">If No, Why?</div>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingReason" value="There was no KK Assembly" onchange="kkfSingleCheck(this,'kabataanVotingReason')"> There was no KK Assembly</label>
                                        <label class="kkf-chk-lbl"><input type="checkbox" class="kkf-sq-chk" name="kabataanVotingReason" value="Not Interested to Attend" onchange="kkfSingleCheck(this,'kabataanVotingReason')"> Not Interested to Attend</label>
                                        <input type="hidden" id="kabataanVotingReason" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- FOOTER: FB Account + Group Chat (with spacing) --}}
                    <div class="kkf-footer-row">
                        <div class="kkf-footer-fb">
                            <label class="kkf-inline-label" for="kabataanFacebookAccount">FB Account:</label>
                            <input type="text" id="kabataanFacebookAccount" class="kkf-uline kkf-uline-fb" placeholder=" ">
                        </div>
                        <div class="kkf-footer-spacer"></div>
                        <div class="kkf-footer-chat">
                            <span class="kkf-inline-label">Willing to join the group chat?</span>
                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWillingToJoinGroupChat" value="Yes" onchange="kkfSingleCheck(this,'kabataanWillingToJoinGroupChat')"> Yes</label>
                            <label class="kkf-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWillingToJoinGroupChat" value="No" onchange="kkfSingleCheck(this,'kabataanWillingToJoinGroupChat')"> No</label>
                            <input type="hidden" id="kabataanWillingToJoinGroupChat" value="">
                        </div>
                    </div>

                    {{-- SIGNATURE --}}
                    <div class="kkf-sig-section">
                        <div class="kkf-sig-container">
                            <!-- Signature overlay (centered on top of name) -->
                            <div class="kkf-sig-overlay" id="kabataanSignatureOverlay" style="display: none;">
                                <img id="kabataanSignaturePreview" class="kkf-sig-overlay-img" alt="Signature">
                            </div>
                            
                            <!-- Name field with underline -->
                            <div class="kkf-sig-name-wrapper">
                                <input type="text" id="kabataanSignatureName" placeholder="" readonly class="kkf-sig-name-input">
                            </div>
                            
                            <!-- Label below the underline -->
                            <div class="kkf-sig-label-bottom">Name and Signature of Participant</div>
                            
                            <!-- Sign button (shown when no signature) -->
                            <button type="button" class="kkf-sig-trigger-btn" id="kabataanSignatureTrigger" title="Sign here">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                </svg>
                                Sign
                            </button>
                            
                            <!-- Hidden signature input -->
                            <input type="hidden" id="kabataanSignature" value="">
                        </div>
                    </div>

            </div>

            <div class="kabataan-mode-panel kabataan-panel-batch km-add-mode-pane" id="kabataanPanelBatch" hidden>
                <div class="kabataan-batch-upload-container">
                    <div class="kabataan-upload-instructions">
                        <h3>Upload Instructions</h3>
                        <p>Upload an Excel (.xlsx) or CSV file containing Kabataan records for this barangay.</p>
                        <ul>
                            <li>Download the template below to ensure correct format</li>
                            <li>Fill in the required fields</li>
                            <li>Upload the completed file</li>
                        </ul>
                        <button type="button" class="km-btn km-btn--manual" id="km-download-template-btn">
                            <i class="fas fa-download"></i> Download Template
                        </button>
                    </div>

                    <div class="kabataan-upload-area">
                        <div class="kabataan-upload-dropzone" id="kabataanUploadDropzone">
                            <div class="kabataan-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <p class="kabataan-upload-text">Drag and drop your file here</p>
                            <p class="kabataan-upload-or">or</p>
                            <input type="file" id="kabataanFileInput" accept=".xlsx,.xls,.csv" style="display:none;">
                            <button type="button" class="km-btn km-btn--manual" id="kabataanSelectFileBtn">
                                <i class="fas fa-folder-open"></i> Browse Files
                            </button>
                            <p class="kabataan-upload-formats">Supported formats: .xlsx, .xls, .csv</p>
                        </div>
                        <div class="kabataan-file-info" id="kabataanFileInfo" style="display:none;">
                            <div class="kabataan-file-name" id="kabataanFileName"></div>
                            <button type="button" class="kabataan-remove-file" id="kabataanRemoveFileBtn" aria-label="Remove file">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="kabataan-upload-preview" id="kabataanUploadPreview" style="display:none;">
                        <h3>Preview</h3>
                        <div class="kabataan-preview-table-container">
                            <table class="kabataan-preview-table">
                                <thead>
                                    <tr>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Age</th>
                                        <th>Sex</th>
                                        <th>Purok/Zone</th>
                                    </tr>
                                </thead>
                                <tbody id="kabataanPreviewBody"></tbody>
                            </table>
                        </div>
                        <div class="kabataan-pagination-info" id="kabataanPaginationInfo"></div>
                        <div class="kabataan-pagination-controls">
                            <button type="button" class="km-btn" id="kabataanPreviewPrev" disabled>Previous</button>
                            <button type="button" class="km-btn" id="kabataanPreviewNext" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer kabataan-modal-footer" id="kabataanManualFooter">
            <button type="button" class="btn btn-approve-cancel" data-add-modal-close>Cancel</button>
            <button type="button" class="btn primary-btn" id="kabataanSaveBtn">Save Record</button>
        </div>
        <div class="modal-footer kabataan-modal-footer" id="kabataanBatchFooter" hidden>
            <button type="button" class="btn btn-approve-cancel" data-add-modal-close>Cancel</button>
            <button type="button" class="btn primary-btn" id="kabataanBatchUploadBtn" disabled>Upload Records</button>
        </div>
    </div>
</div>
