{{-- Batch Upload Modal --}}
<div class="modal-backdrop kabataan-modal-backdrop" id="kabataanBatchUploadModal" style="display:none;">
    <div class="modal-box kabataan-modal-box">
        <div class="modal-header">
            <h2 class="modal-title">Batch Upload Kabataan Records</h2>
            <div class="modal-window-controls">
                <button type="button" class="modal-close" data-batch-modal-close aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="modal-body kabataan-modal-body">
            <div class="kabataan-batch-upload-container">
                <div class="kabataan-upload-instructions">
                    <h3>Upload Instructions</h3>
                    <p>Upload an Excel (.xlsx) or CSV file containing Kabataan records.</p>
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
                        <button type="button" class="kabataan-remove-file" id="kabataanRemoveFileBtn">
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
                            <tbody id="kabataanPreviewBody">
                            </tbody>
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
        <div class="modal-footer kabataan-modal-footer">
            <button type="button" class="btn btn-approve-cancel" data-batch-modal-close>Cancel</button>
            <button type="button" class="btn primary-btn" id="kabataanBatchUploadBtn" disabled>Upload Records</button>
        </div>
    </div>
</div>
