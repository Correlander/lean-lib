<div class="lt-library-page-heading__actions">
    <a class="lt-library-support" href="https://www.startpage.com" target="_blank" rel="noopener noreferrer" title="Open lean-library support" aria-label="lean-library support by Alexander K.">
        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
        <span><strong>Support</strong></span>
    </a>
    <button type="button" class="lt-library-update-check" data-plugin-metadata-sync data-endpoint="{{ BASE_URL }}/LeantimeLib/plugins/check-for-updates" data-csrf="{{ csrf_token() }}" title="Refresh stored plugin metadata from installed composer.json files. Does not download or update plugin code.">
        <span>Check for updates</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
    </button>
    <p class="lt-library-update-check__status" data-plugin-metadata-status role="status" aria-live="polite" hidden></p>
</div>
