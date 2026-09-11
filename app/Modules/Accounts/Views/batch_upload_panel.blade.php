{{-- Embedded batch upload panel (used inside Add modals) --}}
@php
    $prefix = $prefix ?? 'official';
    $templateType = $templateType ?? ($prefix === 'fed' ? 'federation' : 'officials');
    $isOfficialsTemplate = $templateType === 'officials';
    $maxAccounts = \App\Modules\Accounts\Requests\BatchStoreAccountsRequest::MAX_ACCOUNTS;
@endphp

<div class="batch-upload-layout">
    {{-- ── Excel Template card ─────────────────────────────────── --}}
    <section class="batch-card batch-template-card" aria-labelledby="{{ $prefix }}_batchTemplateTitle">
        <div class="batch-template-hero">
            <div class="batch-excel-visual" aria-hidden="true">
                <div class="batch-excel-sheet">
                    <span class="batch-excel-mark">X</span>
                    <span class="batch-excel-ext">LS</span>
                </div>
                <span class="batch-excel-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </span>
            </div>

            <div class="batch-template-copy">
                <h4 class="batch-card-title" id="{{ $prefix }}_batchTemplateTitle">
                    <svg class="batch-card-title-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Excel Template
                </h4>
                <p class="batch-card-subtitle">Download the template to see the required format and columns.</p>

                <div class="batch-template-actions">
                    <a
                        href="{{ route('accounts.batch-template', ['type' => $templateType]) }}"
                        class="btn-template-download"
                        id="{{ $prefix }}_batchTemplateDownloadLink"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download Excel Template
                    </a>
                    <span class="batch-xlsx-pill">.xlsx</span>
                </div>
            </div>
        </div>

        <div class="batch-info-banner" role="note">
            <svg class="batch-info-banner-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <p>
                Keep the Excel column headers exactly as provided. Middle Name is optional.
                Empty <strong>Suffix</strong> defaults to <strong>None</strong>.
                Region, Province, and Municipality are already filled
                (IV-A CALABARZON, Laguna, Santa Cruz).
                Birthdate must be for ages <strong>18–24</strong> only.
                Use <strong>MM/DD/YYYY</strong> for Birthdate and Term dates.
                @if ($isOfficialsTemplate)
                    For SK Officials, term dates follow the SK Federation President when a President is set (locked in preview). Otherwise Term Start may be from 01/01/2023, and Term End must be 3 to 5 years after Term Start. Empty term dates default to the current leadership term.
                @else
                    For SK Federation, Term End must be 3 to 5 years after Term Start.
                @endif
            </p>
        </div>

        <div class="batch-req-grid" role="list">
            <div class="batch-req-item" role="listitem">
                <span class="batch-req-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </span>
                <div>
                    <strong>Date Format</strong>
                    <span>Use MM/DD/YYYY for all dates</span>
                </div>
            </div>

            <div class="batch-req-item" role="listitem">
                <span class="batch-req-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </span>
                <div>
                    <strong>Birthdate</strong>
                    <span>Age 18–24 years old only</span>
                </div>
            </div>

            <div class="batch-req-item" role="listitem">
                <span class="batch-req-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </span>
                <div>
                    <strong>Contact Number</strong>
                    <span>11 digits starting with 09</span>
                </div>
            </div>

            <div class="batch-req-item" role="listitem">
                <span class="batch-req-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 7h16"/>
                        <path d="M4 12h10"/>
                        <path d="M4 17h14"/>
                        <path d="M15 10l4 7"/>
                    </svg>
                </span>
                <div>
                    <strong>Text Format</strong>
                    <span>Lowercase accepted, converted to uppercase</span>
                </div>
            </div>

            <div class="batch-req-item" role="listitem">
                <span class="batch-req-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <div>
                    <strong>Limit</strong>
                    <span>Maximum {{ $maxAccounts }} accounts per upload</span>
                </div>
            </div>
        </div>

        <p class="batch-limit-note" id="{{ $prefix }}_batchLimitNote" hidden>
            Maximum upload limit reached ({{ $maxAccounts }} accounts per file).
        </p>
    </section>

    {{-- ── Upload File card ────────────────────────────────────── --}}
    <section class="batch-card batch-upload-card" aria-labelledby="{{ $prefix }}_batchUploadTitle">
        <h4 class="batch-card-title" id="{{ $prefix }}_batchUploadTitle">
            <svg class="batch-card-title-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Upload File
        </h4>
        <p class="batch-card-subtitle">Upload your filled-out Excel file.</p>

        <div class="batch-dropzone" id="{{ $prefix }}_batchDropzone" role="button" tabindex="0" aria-label="Upload Excel file">
            <div class="batch-dropzone-icon" aria-hidden="true">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
            </div>
            <p class="dropzone-text">Drop your Excel file here or click to browse</p>
            <p class="dropzone-sub" id="{{ $prefix }}_batchFileName">Supported format: .xlsx, .xls</p>
            <input type="file" id="{{ $prefix }}_batchFileInput" accept=".xlsx,.xls" style="display:none;">
        </div>

        <div id="{{ $prefix }}_batchPreview" style="display:none;" class="batch-preview-always"></div>
        <div id="{{ $prefix }}_batchErrorReport" style="display:none;" class="batch-error-report"></div>
    </section>
</div>
