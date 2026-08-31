{{-- Add Kabataan modal — Manual panel matches Officials / Kabataan KK Profiling layout --}}
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

        <div class="modal-body kabataan-modal-body">
            <div class="kabataan-mode-panel kabataan-panel-manual" id="kabataanPanelManual">
                <div class="kkp-paper kk-view-paper kabataan-add-paper">

                    <div class="kkp-form-clear-bar" id="kabataanFormHeaderToolbar">
                        <button
                            type="button"
                            class="kkp-form-clear-btn"
                            id="kabataanClearAllBtn"
                            aria-haspopup="dialog"
                        >
                            Clear All Data
                        </button>
                    </div>

                    <div class="kkp-form-header">
                        <div class="kkp-form-main-title">KK Survey Questionnaire</div>
                        <div class="kkp-form-header-right">
                            <div class="kkp-form-annex">ANNEX 3</div>
                            <div class="kkp-form-header-fields">
                                <div class="kkp-hdr-field kkp-hdr-field--respondent">
                                    <span class="kkp-hdr-label">Respondent #:</span>
                                    <input type="text" id="kabataanRespondentNumber" class="kkp-hdr-input kkp-hdr-input-readonly kkp-hdr-input-auto" value="Auto-assigned on save" readonly tabindex="-1" aria-readonly="true">
                                </div>
                                <div class="kkp-hdr-field">
                                    <span class="kkp-hdr-label">Date:</span>
                                    <input type="text" id="kabataanDate" class="kkp-hdr-input kkp-hdr-input-readonly" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kkp-notice-box">
                        <p class="kkp-notice-title">TO THE RESPONDENT:</p>
                        <p class="kkp-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. We would like to ask your participation by taking your time to answer this questionnaire. Please read the questions carefully and answer them accurately.</p>
                        <p class="kkp-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>
                    </div>

                    <div class="kkp-section-heading">I. PROFILE</div>

                    <div class="kkp-row-label">Name of Respondent <span class="kkp-required">*</span></div>
                    <div class="kkp-name-row kkf-name-row">
                        <div class="kkp-name-col kkf-name-col">
                            <div class="kkp-name-input-wrap">
                                <input type="text" id="kabataanLastName" class="kkp-uline kkp-name-input kkf-uline" placeholder=" " required maxlength="150" autocomplete="off">
                            </div>
                            <label for="kabataanLastName" class="kkp-col-label">Last Name <span class="kkp-required">*</span></label>
                        </div>
                        <div class="kkp-name-col kkf-name-col">
                            <div class="kkp-name-input-wrap">
                                <input type="text" id="kabataanFirstName" class="kkp-uline kkp-name-input kkf-uline" placeholder=" " required maxlength="150" autocomplete="off">
                            </div>
                            <label for="kabataanFirstName" class="kkp-col-label">First Name <span class="kkp-required">*</span></label>
                        </div>
                        <div class="kkp-name-col kkf-name-col">
                            <div class="kkp-name-input-wrap">
                                <input type="text" id="kabataanMiddleName" class="kkp-uline kkp-name-input kkf-uline" placeholder=" " maxlength="150" autocomplete="off">
                            </div>
                            <label for="kabataanMiddleName" class="kkp-col-label">Middle Name</label>
                        </div>
                        <div class="kkp-name-col kkp-name-col-sm kkf-name-col kkf-name-col--sm">
                            <select id="kabataanSuffix" class="kkp-uline kkp-uline-select kkf-uline kkf-uline-select" required>
                                <option value="None" selected>None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="I">I</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                                <option value="Others">Others</option>
                            </select>
                            <label for="kabataanSuffix" class="kkp-col-label">Suffix <span class="kkp-required">*</span></label>
                            <div class="kkp-custom-suffix-wrap" id="kabataanCustomSuffixWrap">
                                <input type="text" id="kabataanCustomSuffix" class="kkp-uline kkf-uline" placeholder="Please specify suffix" maxlength="5" autocomplete="off">
                                <label for="kabataanCustomSuffix" class="kkp-col-label">Please specify suffix <span class="kkp-required">*</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="kkp-row-label">Location:</div>
                    <div class="kkp-loc-row kkf-loc-row">
                        <div class="kkp-loc-col kkf-loc-col">
                            <input type="text" id="kabataanRegion" class="kkp-uline kkp-readonly kkf-uline" readonly tabindex="-1" aria-readonly="true">
                            <label for="kabataanRegion" class="kkp-col-label">Region</label>
                        </div>
                        <div class="kkp-loc-col kkf-loc-col">
                            <input type="text" id="kabataanProvince" class="kkp-uline kkp-readonly kkf-uline" readonly tabindex="-1" aria-readonly="true">
                            <label for="kabataanProvince" class="kkp-col-label">Province</label>
                        </div>
                        <div class="kkp-loc-col kkf-loc-col">
                            <input type="text" id="kabataanCity" class="kkp-uline kkp-readonly kkf-uline" readonly tabindex="-1" aria-readonly="true">
                            <label for="kabataanCity" class="kkp-col-label">City/Municipality</label>
                        </div>
                        <div class="kkp-loc-col kkf-loc-col">
                            <input type="text" id="kabataanBarangay" class="kkp-uline kkp-readonly kkf-uline" readonly tabindex="-1" aria-readonly="true">
                            <label for="kabataanBarangay" class="kkp-col-label">Barangay</label>
                        </div>
                        <div class="kkp-loc-col kkf-loc-col">
                            <select id="kabataanPurokZone" class="kkp-uline kkp-uline-select kkf-uline kkf-uline-select" required>
                                <option value="" disabled selected>Select</option>
                                @foreach ($purokZones ?? [] as $zoneName)
                                    <option value="{{ $zoneName }}">{{ $zoneName }}</option>
                                @endforeach
                            </select>
                            <label for="kabataanPurokZone" class="kkp-col-label">Purok/Zone <span class="kkp-required">*</span></label>
                        </div>
                    </div>

                    <div class="kkp-personal-row">
                        <div class="kkp-personal-left">
                            <div class="kkp-sex-block">
                                <div class="kkp-sex-label-box">Sex Assigned by Birth: <span class="kkp-required">*</span></div>
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
                                    <label class="kkp-inline-label" for="kabataanAgeInput">Age: <span class="kkp-required">*</span></label>
                                    <div class="kkp-age-select-box">
                                        <select id="kabataanAgeInput" class="kkp-uline kkp-uline-select kkp-uline-age" required>
                                            <option value="" disabled selected>Select</option>
                                            @for ($ageOption = 15; $ageOption <= 30; $ageOption++)
                                                <option value="{{ $ageOption }}">{{ $ageOption }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="kkp-inline-pair kkp-inline-pair--birthday">
                                    <label class="kkp-inline-label" for="kabataanDob">Birthday: <span class="kkp-required">*</span></label>
                                    <div class="kkp-birthday-field">
                                        <input type="date" id="kabataanDob" class="kkp-uline kkp-uline-med" required>
                                        <span class="kkp-hint kkp-birthday-hint">(mm/dd/yyyy)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kkp-personal-right kkp-personal-contact">
                            <div class="kkp-inline-pair kkp-inline-pair--email">
                                <label class="kkp-inline-label" for="kabataanEmail">Email Address: <span class="kkp-required">*</span></label>
                                <input type="email" id="kabataanEmail" class="kkp-uline kkp-uline-med kkp-email-input" placeholder=" " minlength="6" maxlength="64" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                            </div>
                            <div class="kkp-inline-pair kkp-inline-pair--contact">
                                <label class="kkp-inline-label" for="kabataanContactInput">Contact #: <span class="kkp-required">*</span></label>
                                <input type="tel" id="kabataanContactInput" class="kkp-uline kkp-uline-med" value="09" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" autocomplete="tel" required>
                            </div>
                        </div>
                    </div>

                    <div class="kkp-section-heading" style="margin-top:10px;">II. DEMOGRAPHIC CHARACTERISTICS <span class="kkp-required">*</span></div>
                    <p class="kkp-demo-instruction">Please put a Check mark next to the word or Phrase that matches your response. <span class="kkp-required">*</span></p>

                    <div class="kkp-demo-grid">
                        <div class="kkp-demo-col">
                            <div class="kkp-demo-block">
                                <div class="kkp-demo-block-label">Civil Status <span class="kkp-required">*</span></div>
                                <div class="kkp-demo-block-options">
                                    <div class="kkp-demo-options-2col">
                                        <div>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Single" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Single</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Married" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Married</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Widowed" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Widowed</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Divorced" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Divorced</label>
                                        </div>
                                        <div>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Separated" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Separated</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Annulled" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Annulled</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Unknown" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Unknown</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanCivilStatus" value="Live-in" onchange="kkfSingleCheck(this,'kabataanCivilStatus')"> Live-in</label>
                                        </div>
                                    </div>
                                    <input type="hidden" id="kabataanCivilStatus" value="">
                                </div>
                            </div>

                            <div class="kkp-demo-block">
                                <div class="kkp-demo-block-label">Youth Age Group <span class="kkp-required">*</span></div>
                                <div class="kkp-demo-block-options kkp-youth-age-group-readonly">
                                    <label class="kkp-chk-lbl kkp-chk-lbl--readonly"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthAgeGroup" value="Child Youth (15-17 yrs old)" disabled tabindex="-1"> Child Youth (15-17 yrs old)</label>
                                    <label class="kkp-chk-lbl kkp-chk-lbl--readonly"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthAgeGroup" value="Core Youth (18-24 yrs old)" disabled tabindex="-1"> Core Youth (18-24 yrs old)</label>
                                    <label class="kkp-chk-lbl kkp-chk-lbl--readonly"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthAgeGroup" value="Young Adult (15-30 yrs old)" disabled tabindex="-1"> Young Adult (25-30 yrs old)</label>
                                    <input type="hidden" id="kabataanYouthAgeGroup" value="">
                                </div>
                            </div>

                            <div class="kkp-demo-block">
                                <div class="kkp-demo-block-label">Educational Background <span class="kkp-required">*</span></div>
                                <div class="kkp-demo-block-options">
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Elementary Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Elementary Level</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Elementary Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Elementary Grad</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="High School Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> High school level</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="High School Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> High school Grad</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Vocational Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Vocational Grad</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="College Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> College Level</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="College Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> College Grad</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Masters Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Masters Level</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Masters Grad" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Masters Grad</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Doctorate Level" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Doctorate Level</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanEducationalBackground" value="Doctorate Graduate" onchange="kkfSingleCheck(this,'kabataanEducationalBackground')"> Doctorate Graduate</label>
                                    <input type="hidden" id="kabataanEducationalBackground" value="">
                                </div>
                            </div>
                        </div>

                        <div class="kkp-demo-col">
                            <div class="kkp-demo-block">
                                <div class="kkp-demo-block-label">Youth<br>Classification <span class="kkp-required">*</span></div>
                                <div class="kkp-demo-block-options kkp-youth-class-layout">
                                    <div class="kkp-youth-class-main">
                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="In School Youth" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> In school Youth</label>
                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="Out of School Youth" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Out of School Youth</label>
                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="Working Youth" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Working Youth</label>
                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="Youth w/ Specific Needs" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Youth w/ Specific Needs:</label>
                                    </div>
                                    <div class="kkp-youth-class-side" aria-label="Youth with specific needs options">
                                        <span class="kkp-youth-specific-brace" aria-hidden="true">{</span>
                                        <div class="kkp-youth-specific-options">
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="Person w/ Disability" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Person w/ Disability</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="Children in Conflict w/ Law" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Children In Conflict w/ Law</label>
                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanYouthClassification" value="Indigenous People" onchange="kkfSingleCheck(this,'kabataanYouthClassification')"> Indigenous People</label>
                                        </div>
                                    </div>
                                    <input type="hidden" id="kabataanYouthClassification" value="">
                                </div>
                            </div>

                            <div class="kkp-demo-block">
                                <div class="kkp-demo-block-label">Work Status <span class="kkp-required">*</span></div>
                                <div class="kkp-demo-block-options">
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWorkStatus" value="Employed" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Employed</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWorkStatus" value="Unemployed" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Unemployed</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWorkStatus" value="Self-Employed" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Self-Employed</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWorkStatus" value="Currently looking for a Job" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Currently looking for a Job</label>
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanWorkStatus" value="Not Interested Looking for a Job" onchange="kkfSingleCheck(this,'kabataanWorkStatus')"> Not Interested Looking for a Job</label>
                                    <input type="hidden" id="kabataanWorkStatus" value="">
                                </div>
                            </div>

                            <div class="kkp-voter-questions-wrap kabataan-voter-wrap">
                                <div class="kkp-voter-questions-grid">
                                    <div class="kkp-voter-questions-col">
                                        <div class="kkp-demo-block">
                                            <div class="kkp-demo-block-label">Registered SK Voter? <span class="kkp-required">*</span></div>
                                            <div class="kkp-demo-block-options">
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanRegisteredSKVoter" value="Yes" onchange="kkfSingleCheck(this,'kabataanRegisteredSKVoter')"> Yes</label>
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanRegisteredSKVoter" value="No" onchange="kkfSingleCheck(this,'kabataanRegisteredSKVoter')"> No</label>
                                                <input type="hidden" id="kabataanRegisteredSKVoter" value="">
                                            </div>
                                        </div>
                                        <div class="kkp-demo-block">
                                            <div class="kkp-demo-block-label">Registered National Voter? <span class="kkp-required">*</span></div>
                                            <div class="kkp-demo-block-options">
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanRegisteredNationalVoter" value="Yes" onchange="kkfSingleCheck(this,'kabataanRegisteredNationalVoter')"> Yes</label>
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanRegisteredNationalVoter" value="No" onchange="kkfSingleCheck(this,'kabataanRegisteredNationalVoter')"> No</label>
                                                <input type="hidden" id="kabataanRegisteredNationalVoter" value="">
                                            </div>
                                        </div>
                                        <div class="kkp-demo-block kkp-assembly-question" id="kabataanAssemblyQuestion">
                                            <div class="kkp-demo-block-label">Have you attended a KK Assembly? <span class="kkp-required">*</span></div>
                                            <div class="kkp-demo-block-options">
                                                <label class="kkp-chk-lbl kkp-assembly-chk-yes">
                                                    <input type="checkbox" class="kkp-sq-chk" name="kabataanAttendedKKAssembly" value="Yes" onchange="kkfSingleCheck(this,'kabataanAttendedKKAssembly'); kkfHandleAssembly(this)"> Yes
                                                    <span class="kkp-assembly-arrow kkp-assembly-arrow--yes" aria-hidden="true">→</span>
                                                </label>
                                                <label class="kkp-chk-lbl kkp-assembly-chk-no">
                                                    <input type="checkbox" class="kkp-sq-chk" name="kabataanAttendedKKAssembly" value="No" onchange="kkfSingleCheck(this,'kabataanAttendedKKAssembly'); kkfHandleAssembly(this)"> No
                                                    <span class="kkp-assembly-arrow kkp-assembly-arrow--no" aria-hidden="true">→</span>
                                                </label>
                                                <input type="hidden" id="kabataanAttendedKKAssembly" value="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="kkp-voter-questions-col">
                                        <div class="kkp-demo-block">
                                            <div class="kkp-demo-block-label">Did you vote last SK? <span class="kkp-required">*</span></div>
                                            <div class="kkp-demo-block-options">
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingHistory" value="Yes" onchange="kkfSingleCheck(this,'kabataanVotingHistory')"> Yes</label>
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingHistory" value="No" onchange="kkfSingleCheck(this,'kabataanVotingHistory')"> No</label>
                                                <input type="hidden" id="kabataanVotingHistory" value="">
                                            </div>
                                        </div>
                                        <div class="kkp-demo-block kkp-assembly-followup" id="kabataanAssemblyYesCell">
                                            <div class="kkp-demo-block-label">If Yes, How many times? <span class="kkp-required">*</span></div>
                                            <div class="kkp-demo-block-options">
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingFrequency" value="1-2 Times" onchange="kkfSingleCheck(this,'kabataanVotingFrequency')"> 1-2 Times</label>
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingFrequency" value="3-4 Times" onchange="kkfSingleCheck(this,'kabataanVotingFrequency')"> 3-4 Times</label>
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingFrequency" value="5 and above" onchange="kkfSingleCheck(this,'kabataanVotingFrequency')"> 5 and above</label>
                                                <input type="hidden" id="kabataanVotingFrequency" value="">
                                            </div>
                                        </div>
                                        <div class="kkp-demo-block kkp-assembly-followup" id="kabataanAssemblyNoCell">
                                            <div class="kkp-demo-block-label">If No, Why? <span class="kkp-required">*</span></div>
                                            <div class="kkp-demo-block-options">
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingReason" value="There was no KK Assembly Meeting" onchange="kkfSingleCheck(this,'kabataanVotingReason')"> There was no KK Assembly Meeting</label>
                                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kabataanVotingReason" value="Not interested to Attend" onchange="kkfSingleCheck(this,'kabataanVotingReason')"> Not interested to Attend</label>
                                                <input type="hidden" id="kabataanVotingReason" value="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <svg class="kkp-assembly-flow-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                    <path class="kkp-assembly-flow-path kkp-assembly-flow-path--yes" d="M42 72 C55 72, 58 38, 72 38" />
                                    <path class="kkp-assembly-flow-path kkp-assembly-flow-path--no" d="M42 88 C58 88, 58 78, 72 78" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="kkp-thankyou">Thank you for your participation!</div>

                    <div class="kkp-sig-section-left">
                        <div class="kkp-sig-container">
                            <div class="kkp-sig-overlay" id="kabataanSignatureOverlay" style="display:none;">
                                <img id="kabataanSignaturePreview" class="kkp-sig-overlay-img" alt="Signature">
                            </div>
                            <div class="kkp-sig-name-wrapper">
                                <input type="text" id="kabataanSignatureName" placeholder="" readonly class="kkp-sig-name-input">
                            </div>
                            <div class="kkp-sig-label-bottom">
                                <span class="kkp-sig-label-text">Name and Signature of Participant <span class="kkp-required">*</span></span>
                            </div>
                            <div class="kkp-sig-actions" id="kabataanSignatureActions">
                                <button type="button" class="kkp-sig-trigger-btn" id="kabataanSignatureTrigger" title="Sign here">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                    </svg>
                                    Sign
                                </button>
                                <button type="button" class="kkp-sig-clear-saved-btn" id="kabataanSignatureClearSaved" title="Clear signature" hidden>
                                    Clear
                                </button>
                            </div>
                            <p class="kkp-sig-status" id="kabataanSignatureStatus" role="status" aria-live="polite" hidden></p>
                            <input type="hidden" id="kabataanSignature" value="">
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <div class="modal-footer kabataan-modal-footer km-kkp-modal-footer" id="kabataanManualFooter">
            <button type="button" class="km-kkp-btn-close" data-add-modal-close>Cancel</button>
            <button type="button" class="km-kkp-btn-save" id="kabataanSaveBtn">Save Kabataan</button>
        </div>
    </div>
</div>

{{-- Clear All Data confirmation --}}
<div class="modal-backdrop kk-modal-backdrop" id="kabataanClearAllModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="kabataanClearAllTitle">
    <div class="modal-box kk-modal-box kk-modal-animate-small kk-modal-no-border kabataan-clear-all-modal">
        <div class="modal-header">
            <h2 class="modal-title" id="kabataanClearAllTitle">Clear all data?</h2>
            <button type="button" class="modal-close" data-clear-all-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body kabataan-clear-all-body">
            <p>This will remove all information currently entered in the Add Kabataan form.</p>
            <p>All fields will be cleared. This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-approve-cancel" data-clear-all-close>Cancel</button>
            <button type="button" class="btn kabataan-clear-all-confirm-btn" id="kabataanClearAllConfirmBtn">Clear All Data</button>
        </div>
    </div>
</div>

{{-- Signature pad for Add KK Record Manual --}}
<div class="kkp-sig-pad-overlay" id="kabataanSignaturePadOverlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="kabataanSignaturePadTitle">
    <div class="kkp-sig-pad-modal">
        <div class="kkp-sig-pad-header">
            <h3 class="kkp-sig-pad-title" id="kabataanSignaturePadTitle">Please Sign Here</h3>
            <button type="button" class="kkp-sig-pad-close" id="kabataanSignaturePadClose" aria-label="Close">&times;</button>
        </div>
        <div class="kkp-sig-pad-body">
            <div class="kkp-sig-canvas-wrap">
                <canvas id="kabataanSignaturePadCanvas" class="kkp-sig-canvas"></canvas>
                <div class="kkp-sig-canvas-placeholder" id="kabataanSignatureCanvasPlaceholder">
                    Sign here with your mouse or finger
                </div>
            </div>
        </div>
        <div class="kkp-sig-pad-footer">
            <button type="button" class="kkp-sig-btn-clear" id="kabataanSignaturePadClear">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Clear
            </button>
            <label class="kkp-sig-btn-upload" for="kabataanSignatureUploadInput" title="Upload signature image">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Upload Image
                <input
                    type="file"
                    id="kabataanSignatureUploadInput"
                    class="kkp-sig-upload-input"
                    accept="image/png,image/jpeg,image/jpg,.png,.jpg,.jpeg"
                    hidden
                >
            </label>
            <button type="button" class="kkp-sig-btn-save" id="kabataanSignaturePadSave">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Save Signature
            </button>
        </div>
        <p class="kkp-sig-pad-status" id="kabataanSignaturePadStatus" role="status" aria-live="polite" hidden></p>
    </div>
</div>
