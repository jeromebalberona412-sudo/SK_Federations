<div class="modal-backdrop kabataan-modal-backdrop" id="kabataanBatchUploadModal" hidden role="dialog" aria-modal="true" aria-labelledby="kabataanBatchUploadTitle">
    <div class="modal-box kabataan-modal-box">
        <div class="modal-header">
            <h2 class="modal-title" id="kabataanBatchUploadTitle">Upload KK Monitoring Records</h2>
            <div class="modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="kmBatchUploadMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="kmBatchUploadRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="modal-close" data-batch-modal-close aria-label="Close upload dialog">&times;</button>
            </div>
        </div>
        <div class="modal-body kabataan-modal-body">
            <div class="kabataan-batch-upload-container">
                <div class="kabataan-upload-instructions">
                    <h3>Barangay-scoped monitoring import</h3>
                    <p>Upload youth monitoring records for <strong>{{ $barangay }}</strong>. Files are validated on the server before any records are written.</p>
                    <ul>
                        <li>Use the template headers without renaming columns.</li>
                        <li>Required fields include name, sex, age, and Purok/Zone.</li>
                        <li>Valid records are added to the approved KK registry. Duplicate and invalid rows are skipped and included in the import summary.</li>
                        <li>Maximum file size: 25 MB; maximum data rows: 5,000.</li>
                    </ul>
                    <button type="button" class="km-btn km-btn--template" id="km-download-template-btn">
                        <i class="fas fa-file-download" aria-hidden="true"></i> Download Excel Template
                    </button>
                </div>

                <div class="kabataan-upload-area" id="kabataanUploadDropzone">
                    <div class="kabataan-upload-dropzone">
                        <div class="kabataan-upload-icon">
                            <i class="fas fa-file-excel" aria-hidden="true"></i>
                        </div>
                        <p class="kabataan-upload-text">Choose an Excel file for this barangay</p>
                        <input type="file" id="kabataanFileInput" accept=".xlsx,.xls,.csv" hidden>
                        <button type="button" class="km-btn km-btn--browse" id="kabataanSelectFileBtn">
                            <i class="fas fa-folder-open" aria-hidden="true"></i> Browse Files
                        </button>
                        <p class="kabataan-upload-formats">Supported: .xlsx, .xls, .csv</p>
                    </div>
                </div>
                <div class="kabataan-file-info" id="kabataanFileInfo" hidden>
                    <span class="kabataan-file-name" id="kabataanFileName"></span>
                    <button type="button" class="kabataan-remove-file" id="kabataanRemoveFileBtn" aria-label="Remove selected file">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <section class="km-excel-preview" id="kmExcelPreview" hidden aria-labelledby="kmExcelPreviewTitle">
                    <div class="km-excel-preview-heading">
                        <div>
                            <h3 id="kmExcelPreviewTitle">Spreadsheet Preview</h3>
                            <p id="kmExcelPreviewMeta"></p>
                        </div>
                        <span class="km-excel-preview-note">First worksheet · editable, paginated preview</span>
                    </div>
                    <div class="km-excel-preview-scroll" role="region" aria-label="Uploaded spreadsheet preview" tabindex="0">
                        <table class="km-excel-preview-table">
                            <thead id="kmExcelPreviewHead"></thead>
                            <tbody id="kmExcelPreviewBody"></tbody>
                        </table>
                    </div>
                    <nav class="km-excel-preview-pagination" id="kmExcelPreviewPagination" aria-label="Spreadsheet preview pages" hidden>
                        <button type="button" class="km-excel-preview-page-btn" data-preview-page-step="-1" disabled>Previous</button>
                        <span id="kmExcelPreviewPageLabel" aria-live="polite"></span>
                        <button type="button" class="km-excel-preview-page-btn" data-preview-page-step="1">Next</button>
                    </nav>
                </section>
                <div class="km-upload-feedback" id="km-upload-feedback" hidden aria-live="polite"></div>
                <ul class="km-upload-errors" id="km-upload-errors" hidden></ul>
            </div>
        </div>
        <div class="modal-footer kabataan-modal-footer">
            <button type="button" class="btn btn-approve-cancel" data-batch-modal-close>Cancel</button>
            <button type="button" class="btn primary-btn km-import-monitoring-btn" id="kabataanBatchUploadBtn" disabled>
                <i class="fas fa-file-import" aria-hidden="true"></i> Import Monitoring Records
            </button>
        </div>
    </div>
</div>
