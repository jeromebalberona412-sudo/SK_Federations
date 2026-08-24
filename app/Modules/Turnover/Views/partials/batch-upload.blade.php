<div class="to-batch-panel" id="turnoverBatchPanel" hidden>
    <div class="to-batch-section">
        <h4 class="to-batch-title"><i class="fas fa-file-arrow-down"></i> Excel Template</h4>
        <p class="to-batch-hint">Use the template columns exactly as shown. Add exactly 2 rows for your incoming <strong>President</strong> and <strong>Vice President</strong>. Dates use <strong>MM/DD/YYYY</strong> format. Lowercase text is accepted and will be converted to uppercase automatically. You can fix validation errors directly in the preview table after upload.</p>
        <a href="{{ route('turnover.batch-template') }}" class="to-btn-template" id="turnoverBatchTemplateLink">
            <i class="fas fa-download"></i> Download Excel Template
        </a>
    </div>

    <div class="to-batch-section">
        <h4 class="to-batch-title"><i class="fas fa-cloud-arrow-up"></i> Upload File</h4>
        <div class="to-batch-dropzone" id="turnoverBatchDropzone" role="button" tabindex="0" aria-label="Upload Excel file">
            <i class="fas fa-file-excel"></i>
            <p>Drop .xlsx / .xls here or tap anywhere to upload</p>
            <p class="to-batch-filename" id="turnoverBatchFileName">Maximum 2 officers per file</p>
            <input type="file" id="turnoverBatchFileInput" accept=".xlsx,.xls" hidden>
        </div>
        <div id="turnoverBatchPreview" class="to-batch-preview" hidden></div>
        <div id="turnoverBatchErrors" class="to-batch-errors" hidden></div>
    </div>

    <div class="turnover-form-actions">
        <button type="button" class="to-btn-primary" id="turnoverBatchSubmitBtn" disabled>Submit Batch Registration</button>
    </div>
</div>
