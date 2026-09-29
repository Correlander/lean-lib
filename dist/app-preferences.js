(function () {
    'use strict';

    const preferences = window.leantimeLibraryPreferences || {};
    if (!preferences.hideExploreApps) return;

    const appUrl = String(preferences.appUrl || '').replace(/\/$/, '');
    if (/\/plugins\/marketplace\/?$/i.test(window.location.pathname)) {
        window.location.replace(appUrl + '/plugins/myapps');
        return;
    }

    function removeExploreAppsTab() {
        const nav = document.querySelector('.lt-tabs-group[aria-label="Apps"]');
        if (!nav) return;
        nav.querySelectorAll('a[href]').forEach(function (link) {
            try {
                if (new URL(link.href, window.location.href).pathname.replace(/\/$/, '').toLowerCase().endsWith('/plugins/marketplace')) {
                    const item = link.closest('li');
                    if (item) item.remove();
                }
            } catch (ignored) {}
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeExploreAppsTab, { once: true });
    } else {
        removeExploreAppsTab();
    }
})();
