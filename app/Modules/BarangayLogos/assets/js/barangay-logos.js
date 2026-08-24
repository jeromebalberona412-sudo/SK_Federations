/**
 * barangay-logos.js
 * Handles UI interactions and calls the backend API for upload/delete.
 */

(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    /* ── Shared state ──────────────────────────────────────── */
    var uploadedCount  = 0;
    var logosVisible   = true;

    // Change-logo flow
    var selectedFileInput = null;
    var selectedCardIndex = null;
    var isChangingLogo    = false;

    // Remove-logo flow
    var removeCardIndex = null;

    // Pending upload (preview modal)
    var pendingIndex   = null;
    var pendingFile    = null;
    var pendingDataUrl = null;

    /* ══════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {

        updateCounter();

        var sidebarLink = document.querySelector('[data-nav-key="barangay-logos"]');
        if (sidebarLink) sidebarLink.classList.add('active');

        /* ── Change-logo confirm ── */
        var changeConfirmBtn = document.getElementById('blChangeConfirmBtn');
        if (changeConfirmBtn) {
            changeConfirmBtn.addEventListener('click', function () {
                hideModal('blChangeModal');
                if (selectedFileInput) {
                    isChangingLogo = true;
                    selectedFileInput.click();
                }
            });
        }

        var changeCancelBtn = document.getElementById('blChangeCancelBtn');
        if (changeCancelBtn) {
            changeCancelBtn.addEventListener('click', function () {
                selectedFileInput = null;
                selectedCardIndex = null;
                isChangingLogo    = false;
                hideModal('blChangeModal');
            });
        }

        /* ── Remove-logo confirm ── */
        var removeConfirmBtn = document.getElementById('blRemoveConfirmBtn');
        if (removeConfirmBtn) {
            removeConfirmBtn.addEventListener('click', function () {
                hideModal('blRemoveModal');
                if (removeCardIndex !== null) {
                    doRemoveLogo(removeCardIndex);
                    removeCardIndex = null;
                }
            });
        }

        var removeCancelBtn = document.getElementById('blRemoveCancelBtn');
        if (removeCancelBtn) {
            removeCancelBtn.addEventListener('click', function () {
                removeCardIndex = null;
                hideModal('blRemoveModal');
            });
        }

        /* ── Upload confirm (preview modal) ── */
        var uploadConfirmBtn = document.getElementById('blUploadConfirmBtn');
        if (uploadConfirmBtn) {
            uploadConfirmBtn.addEventListener('click', function () {
                blConfirmUpload();
            });
        }

        var uploadCancelBtn = document.getElementById('blUploadCancelBtn');
        if (uploadCancelBtn) {
            uploadCancelBtn.addEventListener('click', function () { blCancelUpload(); });
        }

        var uploadCancelBtn2 = document.getElementById('blUploadCancelBtn2');
        if (uploadCancelBtn2) {
            uploadCancelBtn2.addEventListener('click', function () { blCancelUpload(); });
        }

        /* ── Backdrop clicks ── */
        ['blChangeModal', 'blRemoveModal', 'blConfirmModal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('click', function (e) {
                if (e.target !== el) return;
                if (id === 'blChangeModal')  { selectedFileInput = null; selectedCardIndex = null; }
                if (id === 'blRemoveModal')  { removeCardIndex = null; }
                if (id === 'blConfirmModal') { blCancelUpload(); return; }
                hideModal(id);
            });
        });

        /* ── Escape key ── */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            ['blChangeModal', 'blRemoveModal', 'blConfirmModal'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el && el.style.display !== 'none') {
                    if (id === 'blConfirmModal') blCancelUpload();
                    else hideModal(id);
                }
            });
        });

        /* ── File inputs ── */
        document.querySelectorAll('.bl-file-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var idx  = parseInt(this.getAttribute('data-index'), 10);
                var name = this.getAttribute('data-name') || '';
                var file = this.files[0];
                this.value = '';

                if (!file) return;

                if (!file.type.startsWith('image/')) {
                    blToast('Please select a valid image file.', 'error');
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (e) {
                    if (isChangingLogo) {
                        // Skip preview modal for change — upload directly
                        isChangingLogo = false;
                        doUploadLogo(idx, file, e.target.result);
                    } else {
                        // Show preview modal for fresh upload
                        showUploadPreviewModal(idx, name, file, e.target.result);
                    }
                };
                reader.onerror = function () {
                    blToast('Failed to read the image. Please try again.', 'error');
                };
                reader.readAsDataURL(file);
            });
        });

        /* ── Upload buttons: intercept when card already has logo ── */
        document.querySelectorAll('.bl-upload-label').forEach(function (label) {
            label.addEventListener('click', function (e) {
                var idx  = parseInt(this.getAttribute('data-index'), 10);
                var name = this.getAttribute('data-name') || '';
                var card = document.getElementById('card-' + idx);

                if (card && card.classList.contains('has-logo')) {
                    e.preventDefault();
                    selectedFileInput = document.getElementById('fileInput-' + idx);
                    selectedCardIndex = idx;
                    showChangeModal(name);
                }
            });
        });

        /* ── Remove buttons ── */
        document.querySelectorAll('.bl-remove-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx  = parseInt(this.getAttribute('data-index'), 10);
                var name = this.getAttribute('data-name') || '';
                removeCardIndex = idx;
                showRemoveModal(name);
            });
        });

        /* ── Hide/Show toggle ── */
        var toggleBtn = document.getElementById('toggleLogosBtn');
        if (toggleBtn) {
            toggleBtn.classList.add('logos-visible');
            toggleBtn.addEventListener('click', function () { blToggleLogos(); });
        }

        /* ── Search ── */
        var searchInput = document.getElementById('blSearchInput');
        var searchClear = document.getElementById('blSearchClear');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var q = this.value.trim();
                searchClear.style.display = q ? 'flex' : 'none';
                blFilterCards(q);
            });
        }

        if (searchClear) {
            searchClear.addEventListener('click', function () {
                searchInput.value = '';
                searchClear.style.display = 'none';
                blFilterCards('');
                searchInput.focus();
            });
        }

    }); // end DOMContentLoaded

    /* ══════════════════════════════════════════════════════════
       UPLOAD PREVIEW MODAL
    ══════════════════════════════════════════════════════════ */
    function showUploadPreviewModal(idx, name, file, dataUrl) {
        pendingIndex   = idx;
        pendingFile    = file;
        pendingDataUrl = dataUrl;

        var subtitle = document.getElementById('blModalSubtitle');
        var previewImg = document.getElementById('blModalPreviewImg');
        var fileName = document.getElementById('blModalFileName');
        var fileSize = document.getElementById('blModalFileSize');

        if (subtitle)   subtitle.textContent  = name;
        if (previewImg) previewImg.src         = dataUrl;
        if (fileName)   fileName.textContent   = file.name;
        if (fileSize)   fileSize.textContent   = formatFileSize(file.size);

        showModal(document.getElementById('blConfirmModal'));
    }

    window.blConfirmUpload = function () {
        if (pendingIndex === null || !pendingFile) return;
        doUploadLogo(pendingIndex, pendingFile, pendingDataUrl);
        hideModal('blConfirmModal');
        pendingIndex   = null;
        pendingFile    = null;
        pendingDataUrl = null;
    };

    window.blCancelUpload = function () {
        pendingIndex   = null;
        pendingFile    = null;
        pendingDataUrl = null;
        hideModal('blConfirmModal');
    };

    /* ══════════════════════════════════════════════════════════
       API: UPLOAD
    ══════════════════════════════════════════════════════════ */
    function setUploadOverlay(visible, title) {
        var overlay = document.getElementById('blUploadOverlay');
        var titleEl = document.getElementById('blUploadOverlayTitle');
        if (!overlay) return;
        if (titleEl && title) titleEl.textContent = title;
        if (visible) {
            overlay.removeAttribute('hidden');
            overlay.classList.add('is-visible');
        } else {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('hidden', '');
        }
    }

    function withCacheBuster(url) {
        if (!url || url.indexOf('data:') === 0) return url;
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + 'cb=' + Date.now();
    }

    function doUploadLogo(index, file, previewDataUrl) {
        var card = document.getElementById('card-' + index);
        if (!card) return;

        var barangayId = card.getAttribute('data-barangay-id');
        if (!barangayId) {
            blToast('Barangay not found in database. Please refresh.', 'error');
            return;
        }

        // Optimistic UI
        var wasAlreadyUploaded = card.classList.contains('has-logo');
        var previousUrl = null;
        var imgBefore = document.getElementById('img-' + index);
        if (imgBefore && wasAlreadyUploaded) {
            previousUrl = imgBefore.getAttribute('data-current-url') || imgBefore.src || null;
        }

        applyLogoToCard(index, previewDataUrl, null);
        setCardLoading(index, true);
        setUploadOverlay(true, wasAlreadyUploaded ? 'Updating Logo' : 'Uploading Logo');

        var formData = new FormData();
        formData.append('barangay_id', barangayId);
        formData.append('logo', file);

        fetch((window.barangayLogosRoutes && window.barangayLogosRoutes.upload) || '/barangay-logos/upload', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: formData,
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        })
        .then(function (result) {
            setCardLoading(index, false);
            setUploadOverlay(false);
            if (!result.ok) {
                if (wasAlreadyUploaded && previousUrl) {
                    applyLogoToCard(index, previousUrl, card.getAttribute('data-logo-id'));
                } else {
                    revertCard(index);
                }
                blToast(result.data.message || 'Upload failed.', 'error');
                return;
            }
            applyLogoToCard(index, result.data.url, result.data.id);
            var msg = wasAlreadyUploaded ? 'Logo changed successfully!' : 'Logo uploaded successfully!';
            blToast(msg, 'success');
        })
        .catch(function () {
            setCardLoading(index, false);
            setUploadOverlay(false);
            if (wasAlreadyUploaded && previousUrl) {
                applyLogoToCard(index, previousUrl, card.getAttribute('data-logo-id'));
            } else {
                revertCard(index);
            }
            blToast('Upload failed. Please try again.', 'error');
        });
    }

    /* ══════════════════════════════════════════════════════════
       API: DELETE
    ══════════════════════════════════════════════════════════ */
    function doRemoveLogo(index) {
        var card = document.getElementById('card-' + index);
        if (!card) return;

        var logoId = card.getAttribute('data-logo-id');
        if (!logoId) {
            blToast('Logo ID not found. Please refresh.', 'error');
            return;
        }

        setCardLoading(index, true);
        setUploadOverlay(true, 'Removing Logo');

        fetch(((window.barangayLogosRoutes && window.barangayLogosRoutes.deleteBase) || '/barangay-logos') + '/' + logoId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        })
        .then(function (result) {
            setCardLoading(index, false);
            setUploadOverlay(false);
            if (!result.ok) {
                blToast(result.data.message || 'Delete failed.', 'error');
                return;
            }
            clearLogoFromCard(index);
            blToast('Logo deleted successfully!', 'success');
        })
        .catch(function () {
            setCardLoading(index, false);
            setUploadOverlay(false);
            blToast('Delete failed. Please try again.', 'error');
        });
    }

    /* ══════════════════════════════════════════════════════════
       CARD DOM HELPERS
    ══════════════════════════════════════════════════════════ */
    function applyLogoToCard(index, url, logoId) {
        var img         = document.getElementById('img-'           + index);
        var placeholder = document.getElementById('placeholder-'   + index);
        var removeBtn   = document.getElementById('removeBtn-'     + index);
        var card        = document.getElementById('card-'          + index);
        var btnText     = document.getElementById('uploadBtnText-' + index);
        var overlay     = document.getElementById('overlay-'       + index);

        if (!img || !placeholder || !removeBtn || !card) return;

        var displayUrl = withCacheBuster(url);
        img.src = displayUrl;
        img.setAttribute('data-current-url', url);
        img.style.display = logosVisible ? 'block' : 'none';

        placeholder.style.display = 'none';

        if (overlay) overlay.style.display = logosVisible ? 'none' : 'flex';

        removeBtn.style.display = 'block';

        if (!card.classList.contains('has-logo')) {
            card.classList.add('has-logo');
            uploadedCount++;
            updateCounter();
        }

        if (btnText) btnText.textContent = 'Change Logo';

        if (logoId !== null) {
            card.setAttribute('data-logo-id', logoId);
        }
    }

    function clearLogoFromCard(index) {
        var img         = document.getElementById('img-'           + index);
        var placeholder = document.getElementById('placeholder-'   + index);
        var removeBtn   = document.getElementById('removeBtn-'     + index);
        var card        = document.getElementById('card-'          + index);
        var btnText     = document.getElementById('uploadBtnText-' + index);
        var overlay     = document.getElementById('overlay-'       + index);

        if (!img || !placeholder || !removeBtn || !card) return;

        img.src = '';
        img.removeAttribute('data-current-url');
        img.style.display = 'none';

        placeholder.style.display = '';

        if (overlay) overlay.style.display = 'none';

        removeBtn.style.display = 'none';

        if (card.classList.contains('has-logo')) {
            card.classList.remove('has-logo');
            uploadedCount = Math.max(0, uploadedCount - 1);
            updateCounter();
        }

        if (btnText) btnText.textContent = 'Upload Logo';

        card.setAttribute('data-logo-id', '');
    }

    function revertCard(index) {
        // If the card didn't have a logo before the failed upload, clear it
        var card = document.getElementById('card-' + index);
        if (card && !card.getAttribute('data-logo-id')) {
            clearLogoFromCard(index);
        }
    }

    function setCardLoading(index, loading) {
        var card = document.getElementById('card-' + index);
        if (!card) return;
        if (loading) {
            card.classList.add('bl-card-loading');
        } else {
            card.classList.remove('bl-card-loading');
        }
    }

    /* ══════════════════════════════════════════════════════════
       SHOW / HIDE MODALS
    ══════════════════════════════════════════════════════════ */
    function showChangeModal(barangayName) {
        var nameEl = document.getElementById('blChangeBarangayName');
        if (nameEl) nameEl.textContent = barangayName;
        showModal(document.getElementById('blChangeModal'));
    }

    function showRemoveModal(barangayName) {
        var nameEl = document.getElementById('blRemoveBarangayName');
        if (nameEl) nameEl.textContent = barangayName;
        showModal(document.getElementById('blRemoveModal'));
    }

    function showModal(el) {
        if (!el) return;
        el.style.display = 'flex';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                el.classList.add('bl-modal-visible');
            });
        });
    }

    function hideModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('bl-modal-visible');
        setTimeout(function () { el.style.display = 'none'; }, 230);
    }

    /* ══════════════════════════════════════════════════════════
       COUNTER / TOGGLE / SEARCH / TOAST
    ══════════════════════════════════════════════════════════ */
    function updateCounter() {
        var el = document.getElementById('uploadedCount');
        if (el) el.textContent = uploadedCount;
    }

    function blToggleLogos() {
        logosVisible = !logosVisible;

        var toggleBtn  = document.getElementById('toggleLogosBtn');
        var toggleText = document.getElementById('toggleBtnText');
        var label      = logosVisible ? 'Hide Logos' : 'Show Logos';

        if (toggleBtn) {
            toggleBtn.classList.toggle('logos-visible', logosVisible);
            toggleBtn.setAttribute('aria-label', label);
        }
        if (toggleText) toggleText.textContent = label;

        document.querySelectorAll('.bl-card.has-logo').forEach(function (card) {
            var idx     = card.id.replace('card-', '');
            var img     = document.getElementById('img-'     + idx);
            var overlay = document.getElementById('overlay-' + idx);

            if (img)     img.style.display     = logosVisible ? 'block' : 'none';
            if (overlay) overlay.style.display = logosVisible ? 'none'  : 'flex';
        });
    }

    function blFilterCards(query) {
        var q       = query.toLowerCase();
        var cards   = document.querySelectorAll('.bl-card');
        var visible = 0;

        cards.forEach(function (card) {
            var nameEl = card.querySelector('.bl-barangay-name');
            var name   = nameEl ? nameEl.textContent.toLowerCase() : '';
            var show   = !q || name.includes(q);
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        var noResults = document.getElementById('blNoResults');
        if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
    }

    function blToast(message, type) {
        var existing = document.querySelector('.bl-toast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.className = 'bl-toast bl-toast-' + (type || 'info');
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () { toast.classList.add('bl-toast-visible'); });
        });

        setTimeout(function () {
            toast.classList.remove('bl-toast-visible');
            setTimeout(function () { toast.remove(); }, 300);
        }, 3000);
    }

    function formatFileSize(bytes) {
        if (bytes < 1024)       return bytes + ' B';
        if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    /* ── Init uploadedCount from DOM (cards already marked has-logo by Blade) ── */
    document.addEventListener('DOMContentLoaded', function () {
        uploadedCount = document.querySelectorAll('.bl-card.has-logo').length;
        updateCounter();
    });

})();
