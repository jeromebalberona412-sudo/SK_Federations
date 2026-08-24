@php
    $checked = fn (string $option, ?string $current) => strcasecmp(trim($option), trim((string) $current)) === 0 ? 'checked' : '';
    $barangayName = $form['barangay'] ?? ($record?->barangay?->name ?? 'Barangay');
    $signatureImage = $form['signature'] ?? null;
    $hasSignatureImage = filled($signatureImage)
        && str_starts_with((string) $signatureImage, 'data:image');
    $signatureName = $form['signature_name'] ?? '';
@endphp

{{-- Read-only KK Survey — matches Kabataan KKProfiling kkp-* layout --}}
<div class="kk-view-paper">
    <div class="kkp-responsive-container">

    <div class="kkp-form-header">
        <div class="kkp-form-title-col">
            <div class="kkp-form-main-title">KK Survey Questionnaire</div>
            <div class="kkp-form-header-fields">
                <div class="kkp-hdr-field">
                    <span class="kkp-hdr-label">Respondent #:</span>
                    <span class="kkp-hdr-input kkp-hdr-input-readonly kkp-view-value">{{ $form['respondent_number'] }}</span>
                </div>
                <div class="kkp-hdr-field">
                    <span class="kkp-hdr-label">Date:</span>
                    <span class="kkp-hdr-input kkp-hdr-input-readonly kkp-view-value">{{ $form['date'] }}</span>
                </div>
            </div>
        </div>
        <div class="kkp-form-logo">
            <img
                src="{{ $barangayLogoUrl ?? url('/modules/authentication/images/skoneportal_logo.webp') }}"
                alt="{{ $barangayName }} SK Logo"
                onerror="this.onerror=null;this.src='{{ url('/modules/authentication/images/skoneportal_logo.webp') }}';"
            >
        </div>
    </div>

    <div class="kkp-notice-box">
        <p class="kkp-notice-title">TO THE RESPONDENT:</p>
        <p class="kkp-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. We would like to<br>ask your participation by taking time to answer this questionnaire.&nbsp; Please read the questions carefully and answer them<br>accurately.</p>
        <p class="kkp-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>
    </div>

    <div class="kkp-section-heading">I. PROFILE</div>

    <div class="kkp-row-label">Name of Respondent</div>
    <div class="kkp-name-row">
        <div class="kkp-name-col">
            <span class="kkp-uline kkp-view-value">{{ $form['last_name'] }}</span>
            <span class="kkp-col-label">Last Name</span>
        </div>
        <div class="kkp-name-col">
            <span class="kkp-uline kkp-view-value">{{ $form['first_name'] }}</span>
            <span class="kkp-col-label">First Name</span>
        </div>
        <div class="kkp-name-col">
            <span class="kkp-uline kkp-view-value">{{ $form['middle_name'] }}</span>
            <span class="kkp-col-label">Middle Name</span>
        </div>
        <div class="kkp-name-col kkp-name-col-sm">
            <span class="kkp-uline kkp-view-value">{{ $form['suffix'] }}</span>
            <span class="kkp-col-label">Suffix</span>
        </div>
    </div>

    <div class="kkp-row-label">Location:</div>
    <div class="kkp-loc-row">
        <div class="kkp-loc-col">
            <span class="kkp-uline kkp-view-value kkp-readonly">{{ $form['region'] }}</span>
            <span class="kkp-col-label">Region</span>
        </div>
        <div class="kkp-loc-col">
            <span class="kkp-uline kkp-view-value kkp-readonly">{{ $form['province'] }}</span>
            <span class="kkp-col-label">Province</span>
        </div>
        <div class="kkp-loc-col">
            <span class="kkp-uline kkp-view-value kkp-readonly">{{ $form['city'] }}</span>
            <span class="kkp-col-label">City/Municipality</span>
        </div>
        <div class="kkp-loc-col">
            <span class="kkp-uline kkp-view-value kkp-readonly">{{ $form['barangay'] }}</span>
            <span class="kkp-col-label">Barangay</span>
        </div>
        <div class="kkp-loc-col">
            <span class="kkp-uline kkp-view-value">{{ $form['purok_zone'] }}</span>
            <span class="kkp-col-label">Purok/Zone</span>
        </div>
    </div>

    <div class="kkp-personal-row">
        <div class="kkp-personal-left">
            <div class="kkp-sex-block">
                <div class="kkp-sex-label-box">Sex Assigned by Birth:</div>
                <div class="kkp-sex-options">
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Male', $form['sex']) }}> Male</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Female', $form['sex']) }}> Female</label>
                </div>
            </div>
        </div>
        <div class="kkp-personal-center">
            <div class="kkp-age-dob-row">
                <div class="kkp-inline-pair">
                    <span class="kkp-inline-label">Age:</span>
                    <span class="kkp-uline kkp-uline-short kkp-view-value">{{ $form['age'] }}</span>
                </div>
                <div class="kkp-inline-pair">
                    <span class="kkp-inline-label">Birthday:</span>
                    <span class="kkp-uline kkp-uline-med kkp-view-value">{{ $form['birthday'] }}</span>
                </div>
            </div>
        </div>
        <div class="kkp-personal-right">
            <div class="kkp-inline-pair">
                <span class="kkp-inline-label">E-mail address:</span>
                <span class="kkp-uline kkp-uline-med kkp-view-value">{{ $form['email'] }}</span>
            </div>
            <div class="kkp-inline-pair">
                <span class="kkp-inline-label">Contact #:</span>
                <span class="kkp-uline kkp-uline-med kkp-view-value">{{ $form['contact_number'] }}</span>
            </div>
        </div>
    </div>

    <div class="kkp-section-heading" style="margin-top:10px;">II. DEMOGRAPHIC CHARACTERISTICS</div>
    <p class="kkp-demo-instruction">Please put a Check mark (✓) next to the word or Phrase that matches your response.</p>

    <div class="kkp-demo-grid">
        <div class="kkp-demo-col">
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Civil Status</div>
                <div class="kkp-demo-block-options">
                    <div class="kkp-demo-options-2col">
                        <div>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Single', $form['civil_status']) }}> Single</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Married', $form['civil_status']) }}> Married</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Widowed', $form['civil_status']) }}> Widowed</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Divorced', $form['civil_status']) }}> Divorced</label>
                        </div>
                        <div>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Separated', $form['civil_status']) }}> Separated</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Annulled', $form['civil_status']) }}> Annulled</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Unknown', $form['civil_status']) }}> Unknown</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Live-in', $form['civil_status']) }}> Live-in</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Youth Age Group</div>
                <div class="kkp-demo-block-options">
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Child Youth (15-17 yrs old)', $form['youth_age_group']) }}> Child Youth (15-17 yrs old)</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Core Youth (18-24 yrs old)', $form['youth_age_group']) }}> Core Youth (18-24 yrs old)</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Young Adult (25-30 yrs old)', $form['youth_age_group']) }}> Young Adult (25-30 yrs old)</label>
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Educational Background</div>
                <div class="kkp-demo-block-options">
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Elementary Level', $form['education']) }}> Elementary Level</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Elementary Grad', $form['education']) }}> Elementary Grad</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('High School Level', $form['education']) }}> High school level</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('High School Grad', $form['education']) }}> High school Grad</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Vocational Grad', $form['education']) }}> Vocational Grad</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('College Level', $form['education']) }}> College Level</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('College Grad', $form['education']) }}> College Grad</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Masters Level', $form['education']) }}> Masters Level</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Masters Grad', $form['education']) }}> Masters Grad</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Doctorate Level', $form['education']) }}> Doctorate Level</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Doctorate Graduate', $form['education']) }}> Doctorate Graduate</label>
                </div>
            </div>
        </div>

        <div class="kkp-demo-col">
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Youth Classification</div>
                <div class="kkp-demo-block-options kkp-demo-two-col">
                    <div class="kkp-demo-left-col">
                        <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('In School Youth', $form['youth_classification']) }}> In school Youth</label>
                        <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Out of School Youth', $form['youth_classification']) }}> Out of School Youth</label>
                        <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Working Youth', $form['youth_classification']) }}> Working Youth</label>
                    </div>
                    <div class="kkp-demo-right-col">
                        <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Person w/ Disability', $form['youth_classification']) }}> Person w/ Disability</label>
                        <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Children in Conflict w/ Law', $form['youth_classification']) }}> Children In Conflict w/ Law</label>
                        <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Indigenous People', $form['youth_classification']) }}> Indigenous People</label>
                    </div>
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Work Status</div>
                <div class="kkp-demo-block-options">
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Employed', $form['work_status']) }}> Employed</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Unemployed', $form['work_status']) }}> Unemployed</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Self-Employed', $form['work_status']) }}> Self-Employed</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Currently looking for a Job', $form['work_status']) }}> Currently looking for a Job</label>
                    <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Not Interested Looking for a Job', $form['work_status']) }}> Not Interested Looking for a Job</label>
                </div>
            </div>

            <div class="kkp-voter-questions-grid">
                <div class="kkp-voter-questions-col">
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Registered SK Voter?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Yes', $form['sk_voter']) }}> Yes</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('No', $form['sk_voter']) }}> No</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Registered National Voter?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Yes', $form['national_voter']) }}> Yes</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('No', $form['national_voter']) }}> No</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Have you attended a KK Assembly?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Yes', $form['kk_assembly']) }}> Yes</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('No', $form['kk_assembly']) }}> No</label>
                        </div>
                    </div>
                </div>
                <div class="kkp-voter-questions-col">
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Did you vote last SK?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Yes', $form['sk_voted']) }}> Yes</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('No', $form['sk_voted']) }}> No</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">If Yes, How many times?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('1-2 Times', $form['kk_times']) }}> 1-2 Times</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('3-4 Times', $form['kk_times']) }}> 3-4 Times</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('5 and above', $form['kk_times']) }}> 5 and above</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">If No, Why?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('There was no KK Assembly Meeting', $form['kk_reason']) }}> There was no KK Assembly Meeting</label>
                            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Not interested to Attend', $form['kk_reason']) }}> Not interested to Attend</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="kkp-footer-row">
        <div class="kkp-footer-fb">
            <div class="kkp-footer-fb-label-col">
                <span class="kkp-inline-label">FB Account:</span>
            </div>
            <div class="kkp-footer-fb-field">
                <span class="kkp-uline kkp-uline-fb kkp-view-value">{{ $form['facebook_profile_url'] ?? $form['facebook'] ?? '—' }}</span>
            </div>
        </div>
        <div class="kkp-footer-chat">
            <span class="kkp-inline-label">Willing to join the group chat?</span>
            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('Yes', $form['group_chat']) }}> Yes</label>
            <label class="kkp-chk-lbl kkp-view-chk"><input type="checkbox" class="kkp-sq-chk" disabled {{ $checked('No', $form['group_chat']) }}> No</label>
        </div>
    </div>

    <div class="kkp-thankyou">Thank you for your participation!</div>

    <div class="kkp-sig-section-left">
        <div class="kkp-sig-container">
            @if ($hasSignatureImage)
                <div class="kkp-sig-overlay kkp-sig-overlay--visible">
                    <img src="{{ $signatureImage }}" class="kkp-sig-overlay-img" alt="Signature">
                </div>
            @endif
            <div class="kkp-sig-name-wrapper">
                <span class="kkp-sig-name-input kkp-view-value">{{ $signatureName !== '—' ? $signatureName : '' }}</span>
            </div>
            <div class="kkp-sig-label-bottom">Name and Signature of Participant</div>
        </div>
    </div>

    </div>
</div>
