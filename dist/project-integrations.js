(function () {
    'use strict';

    const heading = '<header class="leantimelib-integrations-heading"><h3>Correlander’s Leantime Integration Library</h3><p>Project integration settings provided by enabled Leantime plugins.</p></header>';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

    function start() {
        const panel = document.querySelector('.projectTabs #integrations');
        const base = window.leantime && window.leantime.appUrl;
        const match = window.location.pathname.match(/\/projects\/showProject\/(\d+)/i);
        if (!panel || !base || !match) return;

        const projectId = match[1];
        panel.innerHTML = heading + '<p role="status">Loading integrations from the Library…</p>';
        const resultParams = new URLSearchParams();
        ['github_error', 'github_connected'].forEach(function (key) {
            const value = new URLSearchParams(window.location.search).get(key);
            if (value) resultParams.set(key, value);
        });
        const query = resultParams.toString();
        const url = base.replace(/\/$/, '') + '/LeantimeLib/projectIntegrations/' + encodeURIComponent(projectId) + (query ? '?' + query : '');
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(async function (response) {
                const responseText = await response.text();
                let result = null;
                try { result = responseText ? JSON.parse(responseText) : null; } catch (ignored) {}
                if (!response.ok) {
                    const detail = result && (result.error || result.message)
                        ? String(result.error || result.message)
                        : responseText.replace(/\s+/g, ' ').trim().slice(0, 400);
                    throw new Error('The Library endpoint returned HTTP ' + response.status + (detail ? ': ' + detail : '.'));
                }
                if (!result) throw new Error('The Library endpoint returned invalid JSON: ' + responseText.replace(/\s+/g, ' ').trim().slice(0, 400));
                return result;
            })
            .then(function (result) {
                if (!result || typeof result.html !== 'string') throw new Error('The Library endpoint returned an invalid response.');
                panel.innerHTML = heading + result.html;
                panel.dataset.leantimelibLoaded = '1';
                panel.dispatchEvent(new CustomEvent('leantimelib:integrations-loaded', { bubbles: true, detail: { projectId: projectId } }));
            })
            .catch(function (error) {
                panel.innerHTML = heading + '<div class="alert alert-warning" role="alert">Project integrations could not be loaded. '+escapeHtml(error.message)+' Check the browser console and Leantime application log for details.</div>';
                panel.dataset.leantimelibLoaded = '1';
                console.error('[LeantimeLib integrations]', { endpoint: url, projectId: projectId, error: error });
            });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
