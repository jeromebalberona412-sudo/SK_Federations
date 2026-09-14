@php
    /** @var callable(string, string): bool $isChecked */
    $checked = $isChecked ?? fn () => false;
    $on = fn (string $field, string ...$options) => collect($options)->contains(fn ($option) => $checked($field, $option));
@endphp

<div class="kkp-paper kk-view-paper kk-print-questionnaire">
    <div class="kkp-form-header">
        <div class="kkp-form-main-title">KK Survey Questionnaire</div>
        <div class="kkp-form-header-right">
            <div class="kkp-form-annex">ANNEX 3</div>
            <div class="kkp-form-header-fields">
                <div class="kkp-hdr-field kkp-hdr-field--respondent">
                    <span class="kkp-hdr-label">Respondent #:</span>
                    <span class="kkp-hdr-input kkp-hdr-input-readonly">{{ $respondentNumber ?? '—' }}</span>
                </div>
                <div class="kkp-hdr-field">
                    <span class="kkp-hdr-label">Date:</span>
                    <span class="kkp-hdr-input kkp-hdr-input-readonly">{{ $date ?? '' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="kkp-notice-box">
        <p class="kkp-notice-title">TO THE RESPONDENT:</p>
        <p class="kkp-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. We would like to ask your participation by taking time to answer this questionnaire. Please read the questions carefully and answer them accurately.</p>
        <p class="kkp-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>
    </div>

    <div class="kkp-section-heading">I. PROFILE</div>
    <div class="kkp-row-label">Name of Respondent</div>
    <div class="kkp-name-row">
        <div class="kkp-name-col">
            <span class="kkp-uline kkp-name-input kkp-view-value">{{ $lastName ?? '' }}</span>
            <span class="kkp-col-label">Last Name</span>
        </div>
        <div class="kkp-name-col">
            <span class="kkp-uline kkp-name-input kkp-view-value">{{ $firstName ?? '' }}</span>
            <span class="kkp-col-label">First Name</span>
        </div>
        <div class="kkp-name-col">
            <span class="kkp-uline kkp-name-input kkp-view-value">{{ $middleName ?? '' }}</span>
            <span class="kkp-col-label">Middle Name</span>
        </div>
        <div class="kkp-name-col kkp-name-col-sm">
            <span class="kkp-uline kkp-uline-select kkp-view-value">{{ ($suffix ?? '') !== '' ? $suffix : 'None' }}</span>
            <span class="kkp-col-label">Suffix</span>
        </div>
    </div>

    <div class="kkp-row-label">Location:</div>
    <div class="kkp-loc-row">
        <div class="kkp-loc-col"><span class="kkp-uline kkp-view-value">{{ $region ?? '' }}</span><span class="kkp-col-label">Region</span></div>
        <div class="kkp-loc-col"><span class="kkp-uline kkp-view-value">{{ $province ?? '' }}</span><span class="kkp-col-label">Province</span></div>
        <div class="kkp-loc-col"><span class="kkp-uline kkp-view-value">{{ $city ?? '' }}</span><span class="kkp-col-label">City/Municipality</span></div>
        <div class="kkp-loc-col"><span class="kkp-uline kkp-view-value">{{ $barangay ?? '' }}</span><span class="kkp-col-label">Barangay</span></div>
        <div class="kkp-loc-col"><span class="kkp-uline kkp-view-value">{{ $purokZone ?? '' }}</span><span class="kkp-col-label">Purok/Sitio/Zone</span></div>
    </div>

    <div class="kkp-personal-row">
        <div class="kkp-personal-left">
            <div class="kkp-sex-block">
                <div class="kkp-sex-label-box">Sex Assigned by Birth:</div>
                <div class="kkp-sex-options">
                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('sex', 'Male'))> Male</label>
                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('sex', 'Female'))> Female</label>
                </div>
            </div>
        </div>
        <div class="kkp-personal-center">
            <div class="kkp-age-dob-row">
                <div class="kkp-inline-pair kkp-inline-pair--age">
                    <span class="kkp-inline-label">Age:</span>
                    <span class="kkp-uline kkp-uline-age kkp-view-value">{{ $age ?? '' }}</span>
                </div>
                <div class="kkp-inline-pair kkp-inline-pair--birthday">
                    <span class="kkp-inline-label">Birthday:</span>
                    <div class="kkp-birthday-field">
                        <span class="kkp-uline kkp-uline-med kkp-view-value">{{ $birthday ?? '' }}</span>
                        <span class="kkp-hint kkp-birthday-hint">(mm/dd/yyyy)</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="kkp-personal-right">
            <div class="kkp-inline-pair kkp-inline-pair--email">
                <span class="kkp-inline-label">E-mail address:</span>
                <span class="kkp-uline kkp-view-value">{{ $email ?? '' }}</span>
            </div>
            <div class="kkp-inline-pair">
                <span class="kkp-inline-label">Contact #:</span>
                <span class="kkp-uline kkp-view-value">{{ $contactNumber ?? '' }}</span>
            </div>
        </div>
    </div>

    <div class="kkp-section-heading">II. DEMOGRAPHIC CHARACTERISTICS</div>
    <p class="kkp-demo-instruction">Please put a Check mark (/) next to the word or Phrase that matches your response.</p>

    <div class="kkp-demo-grid">
        <div class="kkp-demo-col">
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Civil Status</div>
                <div class="kkp-demo-block-options">
                    <div class="kkp-demo-options-2col">
                        <div>
                            @foreach (['Single', 'Married', 'Widowed', 'Divorced'] as $option)
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('civil_status', $option))> {{ $option }}</label>
                            @endforeach
                        </div>
                        <div>
                            @foreach (['Separated', 'Annulled', 'Unknown', 'Live-in'] as $option)
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('civil_status', $option))> {{ $option }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Youth Age Group</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Child Youth (15-17 yrs old)', 'Core Youth (18-24 yrs old)', 'Young Adult (25-30 yrs old)'] as $option)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_age_group', $option))> {{ $option }}</label>
                    @endforeach
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Educational Background</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Elementary Level', 'Elementary Grad', 'High school level', 'High school Grad', 'Vocational Grad', 'College Level', 'College Grad', 'Masters Level', 'Masters Grad', 'Doctorate Level', 'Doctorate Graduate'] as $option)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('education', $option, str_replace('school', 'School', $option)))> {{ $option }}</label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="kkp-demo-col">
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Youth<br>Classification</div>
                <div class="kkp-demo-block-options kkp-youth-class-layout">
                    <div class="kkp-youth-class-main">
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'In School Youth', 'In school Youth'))> In school Youth</label>
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'Out of School Youth'))> Out of School Youth</label>
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'Working Youth'))> Working Youth</label>
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'Youth w/ Specific Needs', 'Youth with Specific Needs', 'Youth w/ Specific needs'))> Youth w/ Specific Needs:</label>
                    </div>
                    <div class="kkp-youth-class-side" aria-label="Youth with specific needs options">
                        <span class="kkp-youth-specific-brace" aria-hidden="true">{</span>
                        <div class="kkp-youth-specific-options">
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'Person w/ Disability'))> Person w/ Disability</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'Children In Conflict w/ Law', 'Children in Conflict w/ Law'))> Children In Conflict w/ Law</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('youth_classification', 'Indigenous People'))> Indigenous People</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Work Status</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Employed', 'Unemployed', 'Self-Employed', 'Currently looking for a Job', 'Not Interested Looking for a Job'] as $option)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('work_status', $option))> {{ $option }}</label>
                    @endforeach
                </div>
            </div>
            <div class="kkp-voter-questions-wrap">
                <div class="kkp-voter-questions-grid">
                    <div class="kkp-voter-questions-col">
                        <div class="kkp-demo-block">
                            <div class="kkp-demo-block-label">Registered SK Voter?</div>
                            <div class="kkp-demo-block-options">
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('sk_voter', 'Yes'))> Yes</label>
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('sk_voter', 'No'))> No</label>
                            </div>
                        </div>
                        <div class="kkp-demo-block">
                            <div class="kkp-demo-block-label">Registered National Voter?</div>
                            <div class="kkp-demo-block-options">
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('national_voter', 'Yes'))> Yes</label>
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('national_voter', 'No'))> No</label>
                            </div>
                        </div>
                        <div class="kkp-demo-block">
                            <div class="kkp-demo-block-label">Have you attended a KK Assembly?</div>
                            <div class="kkp-demo-block-options">
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('kk_assembly', 'Yes'))> Yes</label>
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('kk_assembly', 'No'))> No</label>
                            </div>
                        </div>
                    </div>
                    <div class="kkp-voter-questions-col">
                        <div class="kkp-demo-block">
                            <div class="kkp-demo-block-label">Did you vote last SK?</div>
                            <div class="kkp-demo-block-options">
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('sk_voted', 'Yes'))> Yes</label>
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('sk_voted', 'No'))> No</label>
                            </div>
                        </div>
                        <div class="kkp-demo-block">
                            <div class="kkp-demo-block-label">If Yes, How many times?</div>
                            <div class="kkp-demo-block-options">
                                @foreach (['1-2 Times', '3-4 Times', '5 and above'] as $option)
                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('kk_times', $option))> {{ $option }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="kkp-demo-block kkp-assembly-why">
                            <div class="kkp-demo-block-label">If No, Why?</div>
                            <div class="kkp-demo-block-options">
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('kk_reason', 'There was no KK Assembly Meeting', 'There was no KK Assembly'))> <span>There was no KK Assembly Meeting</span></label>
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" disabled @checked($on('kk_reason', 'Not interested to Attend', 'Not Interested to Attend'))> <span>Not interested to Attend</span></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="kkp-thankyou">Thank you for your participation!</div>
    <div class="kkp-sig-section-left">
        <div class="kkp-sig-container kkp-sig-container--print">
            @if (! empty($signatureImage))
                <img src="{{ $signatureImage }}" class="kkp-sig-print-img" alt="Signature">
            @endif
            <div class="kkp-sig-name-wrapper">
                <span class="kkp-sig-name-input kkp-view-value">{{ $signatureName ?? '' }}</span>
            </div>
            <div class="kkp-sig-label-bottom">Name and Signature of Participant</div>
        </div>
    </div>
</div>
