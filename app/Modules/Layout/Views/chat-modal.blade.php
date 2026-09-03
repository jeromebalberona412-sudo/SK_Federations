<div
    class="comms-chat-modal"
    id="commsChatModal"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="commsChatModalTitle"
>
    <div class="comms-chat-modal-backdrop" data-comms-chat-close tabindex="-1"></div>
    <div class="comms-chat-modal-panel">
        <div class="comms-chat-modal-header">
            <div class="comms-chat-modal-peer">
                <span class="comms-chat-modal-avatar" id="commsChatModalAvatar" aria-hidden="true">U</span>
                <div class="comms-chat-modal-peer-text">
                    <h3 id="commsChatModalTitle">Chat</h3>
                    <p class="comms-chat-modal-sub" id="commsChatModalSub">Messages</p>
                </div>
            </div>
            <div class="comms-chat-modal-actions">
                <button type="button" class="comms-chat-modal-action-btn" id="commsChatModalVoiceBtn" title="Voice call" aria-label="Start voice call">
                    <i class="fas fa-phone" aria-hidden="true"></i>
                </button>
                <button type="button" class="comms-chat-modal-action-btn" id="commsChatModalVideoBtn" title="Video call" aria-label="Start video call">
                    <i class="fas fa-video" aria-hidden="true"></i>
                </button>
                <a
                    href="{{ \Illuminate\Support\Facades\Route::has('communications.index') ? route('communications.index') : url('/communications') }}"
                    class="comms-chat-modal-action-btn"
                    id="commsChatModalOpenFull"
                    data-no-loading
                    title="Open in Messages"
                >
                    <i class="fas fa-expand" aria-hidden="true"></i>
                </a>
                <button type="button" class="comms-chat-modal-action-btn comms-chat-modal-close-btn" data-comms-chat-close aria-label="Close chat">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="comms-chat-modal-body" id="commsChatModalBody" aria-live="polite">
            <p class="comms-chat-modal-empty">Select a conversation</p>
        </div>

        <form class="comms-chat-modal-composer" id="commsChatModalForm" autocomplete="off">
            <input type="file" id="commsChatPhotoInput" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif" multiple aria-hidden="true" tabindex="-1">
            <input type="file" id="commsChatFileInput" class="visually-hidden" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple aria-hidden="true" tabindex="-1">
            <div class="comms-chat-attach-wrap">
                <button type="button" class="comms-chat-attach-btn" id="commsChatAttachBtn" title="Attach" aria-label="Attach">
                    <span aria-hidden="true">+</span>
                </button>
                <div class="comms-chat-attach-menu" id="commsChatAttachMenu" hidden>
                    <button type="button" class="comms-chat-attach-menu-item" id="commsChatPickPhotos">
                        <i class="fas fa-image" aria-hidden="true"></i> Photos
                    </button>
                    <button type="button" class="comms-chat-attach-menu-item" id="commsChatPickFiles">
                        <i class="fas fa-file-alt" aria-hidden="true"></i> Files
                    </button>
                </div>
            </div>
            <div class="comms-chat-composer-main">
                <div class="comms-chat-attach-preview" id="commsChatAttachPreview" hidden>
                    <div class="comms-chat-attach-preview-inner" id="commsChatAttachPreviewInner"></div>
                    <button type="button" class="comms-chat-attach-clear" id="commsChatAttachClear" aria-label="Remove attachments">&times;</button>
                </div>
                <label class="visually-hidden" for="commsChatModalInput">Message</label>
                <textarea
                    id="commsChatModalInput"
                    class="comms-chat-modal-input"
                    rows="1"
                    placeholder="Type a message…"
                    maxlength="5000"
                ></textarea>
            </div>
            <button type="submit" class="comms-chat-modal-send" id="commsChatModalSend" aria-label="Send message">
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</div>
