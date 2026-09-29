(function () {
    'use strict';

    function start() {
        const panel = document.querySelector('.projectTabs #integrations');
        const base = window.leantime && window.leantime.appUrl;
        const match = window.location.pathname.match(/\/projects\/showProject\/(\d+)/i);
        if (!panel || !base || !match) return;

        const projectId = match[1];
        const resultParams = new URLSearchParams();
        ['github_error', 'github_connected'].forEach(function (key) {
            const value = new URLSearchParams(window.location.search).get(key);
            if (value) resultParams.set(key, value);
        });
        const query = resultParams.toString();
        const url = base.replace(/\/$/, '') + '/LeantimeLib/projectIntegrations/' + encodeURIComponent(projectId) + (query ? '?' + query : '');
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) throw new Error('Library integration panels could not be loaded.');
                return response.json();
            })
            .then(function (result) {
                panel.innerHTML = result.html || '<p>No project integrations are registered.</p>';
                panel.dataset.leantimelibLoaded = '1';
                panel.dispatchEvent(new CustomEvent('leantimelib:integrations-loaded', { bubbles: true, detail: { projectId: projectId } }));
            })
            .catch(function (error) {
                panel.innerHTML = '<div class="alert alert-warning" role="alert">Project integrations could not be loaded. Reload this page to try again.</div>';
                panel.dataset.leantimelibLoaded = '1';
                console.error('[LeantimeLib integrations]', error);
            });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
