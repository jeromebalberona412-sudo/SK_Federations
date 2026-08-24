<div class="cp-shell" id="commentPreviewShell" hidden>
    <article class="cp-modal" id="commentPreviewModal" role="dialog" aria-modal="true" aria-labelledby="cpTitle">
        <header class="cp-header">
            <h1 class="cp-title" id="cpTitle">Post</h1>
            <button type="button" class="cp-close" id="cpClose" aria-label="Close">&times;</button>
        </header>

        <div class="cp-scroll" id="cpScroll">
            <div class="cp-post" id="cpPost"></div>
            <div class="cp-engage" id="cpEngage"></div>
            <div class="cp-sort">
                <button type="button" class="cp-sort-btn" id="cpSortBtn">Most relevant</button>
                <div class="cp-sort-menu" id="cpSortMenu">
                    <button type="button" data-sort="relevant">Most relevant</button>
                    <button type="button" data-sort="newest">Newest</button>
                    <button type="button" data-sort="oldest">Oldest</button>
                </div>
            </div>
            <div class="cp-comments" id="cpComments"></div>
        </div>

        <footer class="cp-composer" id="cpComposer">
            <img src="{{ $avatar ?? asset('Images/SK_Fed_profile.png') }}" alt="You" class="cp-composer-avatar" id="cpComposerAvatar">
            <div class="cp-composer-box">
                <input type="text" id="cpCommentInput" class="cp-composer-input" maxlength="500" placeholder="Comment as {{ $user->name ?? 'SK Federation' }}" autocomplete="off">
                <button type="button" class="cp-send-btn" id="cpSendBtn" aria-label="Send comment" disabled>
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                </button>
            </div>
        </footer>
    </article>
</div>

<div id="cpReactionViewer" class="cp-viewer" hidden>
    <div class="cp-viewer-overlay" id="cpViewerOverlay"></div>
    <div class="cp-viewer-panel" role="dialog" aria-label="People who reacted">
        <div class="cp-viewer-header">
            <div class="cp-viewer-tabs" id="cpViewerTabs"></div>
            <button type="button" class="cp-viewer-close" id="cpViewerClose" aria-label="Close">&times;</button>
        </div>
        <div class="cp-viewer-list" id="cpViewerList"></div>
    </div>
</div>
