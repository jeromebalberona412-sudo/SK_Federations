<div class="turnover-form-step" data-step="{{ $step }}" @if($step > 1) hidden @endif data-prefix="{{ $prefix }}">
    <div class="to-form-section">
        <h3 class="turnover-section-title"><i class="fas fa-user-tie"></i> {{ $title }}</h3>

        <div class="turnover-form-grid">
            <div class="to-form-group">
                <label class="to-form-label required">First Name</label>
                <input type="text" name="{{ $prefix }}[first_name]" class="to-form-input input-uppercase to-field" data-field="first_name" maxlength="50" autocomplete="off" placeholder="FIRST NAME">
                <span class="to-field-error" data-error-for="{{ $prefix }}_first_name"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label">Middle Name</label>
                <input type="text" name="{{ $prefix }}[middle_name]" class="to-form-input input-uppercase to-field" data-field="middle_name" maxlength="50" autocomplete="off" placeholder="MIDDLE NAME">
                <span class="to-field-hint">Optional · uppercase, no spaces</span>
                <span class="to-field-error" data-error-for="{{ $prefix }}_middle_name"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Last Name</label>
                <input type="text" name="{{ $prefix }}[last_name]" class="to-form-input input-uppercase to-field" data-field="last_name" maxlength="50" autocomplete="off" placeholder="LASTNAME">
                <span class="to-field-error" data-error-for="{{ $prefix }}_last_name"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Suffix</label>
                <select name="{{ $prefix }}[suffix]" class="to-form-input to-field" data-field="suffix">
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
                <span class="to-field-error" data-error-for="{{ $prefix }}_suffix"></span>
            </div>
            <div class="to-form-group turnover-suffix-other" hidden>
                <label class="to-form-label required">Other Suffix</label>
                <input type="text" name="{{ $prefix }}[suffix_other]" class="to-form-input input-uppercase to-field" data-field="suffix_other" maxlength="10" autocomplete="off" placeholder="SUFFIX">
                <span class="to-field-error" data-error-for="{{ $prefix }}_suffix_other"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Sex</label>
                <select name="{{ $prefix }}[sex]" class="to-form-input to-field" data-field="sex">
                    <option value="" disabled selected hidden>Select Sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <span class="to-field-error" data-error-for="{{ $prefix }}_sex"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Date of Birth</label>
                <input type="date" name="{{ $prefix }}[date_of_birth]" class="to-form-input to-field to-dob" data-field="date_of_birth" min="{{ $dob_min }}" max="{{ $dob_max }}">
                <span class="to-field-hint">Age 18–24 only</span>
                <span class="to-field-error" data-error-for="{{ $prefix }}_date_of_birth"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Age</label>
                <input type="number" name="{{ $prefix }}[age]" class="to-form-input to-field to-age" data-field="age" min="18" max="24" step="1" readonly tabindex="-1" placeholder="—">
                <span class="to-field-hint">Auto-calculated from birthdate</span>
                <span class="to-field-error" data-error-for="{{ $prefix }}_age"></span>
            </div>
            <div class="to-form-group to-form-group--wide">
                <label class="to-form-label required">Email</label>
                <input type="email" name="{{ $prefix }}[email]" class="to-form-input to-field" data-field="email" maxlength="255" autocomplete="off" placeholder="example@gmail.com">
                <span class="to-field-hint">@gmail.com only · 6–30 characters before @</span>
                <span class="to-field-error" data-error-for="{{ $prefix }}_email"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Contact Number</label>
                <input type="text" name="{{ $prefix }}[contact_number]" class="to-form-input to-field to-contact" data-field="contact_number" maxlength="11" placeholder="09XXXXXXXXX" inputmode="numeric">
                <span class="to-field-error" data-error-for="{{ $prefix }}_contact_number"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Barangay</label>
                <select name="{{ $prefix }}[barangay_id]" class="to-form-input to-field" data-field="barangay_id">
                    <option value="" disabled selected hidden>Select Barangay</option>
                    @foreach($barangays as $barangay)
                        <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                    @endforeach
                </select>
                <span class="to-field-error" data-error-for="{{ $prefix }}_barangay_id"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Term Start</label>
                <input type="date" name="{{ $prefix }}[term_start]" class="to-form-input to-field to-term-start" data-field="term_start" value="{{ $term_start_default ?? '' }}" min="{{ $term_start_default ?? '' }}">
                <span class="to-field-hint">Cannot select past dates</span>
                <span class="to-field-error" data-error-for="{{ $prefix }}_term_start"></span>
            </div>
            <div class="to-form-group">
                <label class="to-form-label required">Term End</label>
                <input type="date" name="{{ $prefix }}[term_end]" class="to-form-input to-field to-term-end" data-field="term_end" value="{{ $term_end_default ?? '' }}" readonly tabindex="-1">
                <span class="to-field-hint">Exactly 4 years after term start</span>
                <span class="to-field-error" data-error-for="{{ $prefix }}_term_end"></span>
            </div>
        </div>
    </div>
</div>
