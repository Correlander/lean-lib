(function () {
    'use strict';

    const heading = '<header class="leantimelib-integrations-heading"><h3>leantime lib — project integrations</h3><p>Project integration settings provided by enabled Leantime plugins.</p></header>';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

    function installGuiSurfaceSelector(root) {
        const selector = root.querySelector('[data-gui-surface-selector]');
        if (!selector || selector.dataset.installed === '1') return;
        selector.dataset.installed = '1';
        const panels = Array.from(root.querySelectorAll('[data-gui-surface]'));
        function showSelected() {
            panels.forEach(function (surface) { surface.hidden = surface.dataset.guiSurface !== selector.value; });
        }
        selector.addEventListener('change', showSelected);
        showSelected();
    }

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
        const url = base.replace(/\/$/, '') + '/LeanLib/projectIntegrations/' + encodeURIComponent(projectId) + (query ? '?' + query : '');
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
                installGuiSurfaceSelector(panel);
                installProjectPanelOrderEditor(panel);
                panel.dataset.leantimelibLoaded = '1';
                panel.dispatchEvent(new CustomEvent('leantimelib:integrations-loaded', { bubbles: true, detail: { projectId: projectId } }));
            })
            .catch(function (error) {
                panel.innerHTML = heading + '<div class="alert alert-warning" role="alert">Project integrations could not be loaded. '+escapeHtml(error.message)+' Check the browser console and Leantime application log for details.</div>';
                panel.dataset.leantimelibLoaded = '1';
                console.error('[LeanLib integrations]', { endpoint: url, projectId: projectId, error: error });
            });
    }

    async function saveVisibility(control, sectionId, payload, previousEnabled) {
        const status = control.querySelector('[data-visibility-status]');
        const row = control.querySelector('[data-section-row="' + CSS.escape(sectionId) + '"]');
        const checkbox = row && row.querySelector('[data-section-toggle]');
        const reset = row && row.querySelector('[data-section-reset]');
        if (status) status.textContent = 'Saving…';
        if (checkbox) checkbox.disabled = true;
        if (reset) reset.disabled = true;
        try {
            const response = await fetch(control.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': control.dataset.csrf || ''
                },
                body: JSON.stringify(Object.assign({ sectionId: sectionId }, payload))
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || result.message || 'Could not save this project override.');
            if (checkbox) {
                checkbox.checked = !!result.enabled;
                checkbox.disabled = false;
                checkbox.dataset.savedEnabled = result.enabled ? '1' : '0';
            }
            if (reset) {
                reset.disabled = !result.overridden;
            }
            const rowStatus = row && row.querySelector('[data-section-status]');
            if (rowStatus) rowStatus.textContent = result.overridden ? 'Project override' : 'Using Library default';
            if (status) status.textContent = 'To-do visibility saved.';
        } catch (error) {
            if (status) status.textContent = error.message;
            if (checkbox) {
                if (typeof previousEnabled === 'boolean') checkbox.checked = previousEnabled;
                checkbox.disabled = false;
            }
            if (reset) reset.disabled = false;
            console.error('[LeanLib project visibility]', error);
        }
    }

    function getProjectState(control, kind, visibleZone) {
        const ids = function (zone) {
            return Array.from(control.querySelector('[data-library-zone="' + zone + '"]').querySelectorAll('[data-widget-kind="' + kind + '"]')).map((item) => item.dataset.widgetId);
        };
        const visible = ids(visibleZone);
        const parked = ids('parked');
        return { order: visible.concat(parked), visible: visible };
    }

    function getFieldState(control) {
        const ids = function (zone) {
            return Array.from(control.querySelectorAll('[data-widget-kind]'))
                .filter((item) => item.dataset.widgetKind !== 'tabs' && item.closest('[data-library-zone]')?.dataset.libraryZone === zone)
                .map((item) => item.dataset.widgetId);
        };
        const groups = {};
        control.querySelectorAll('[data-section-children]').forEach((zone) => {
            const id = zone.dataset.sectionChildren;
            groups[id] = Array.from(control.querySelectorAll('[data-widget-kind][data-parent-section="' + CSS.escape(id) + '"]'))
                .map((item) => item.dataset.widgetId);
        });
        return { main: ids('main'), auxiliary: ids('auxiliary'), sidebar: ids('sidebar'), hidden: ids('parked'), groups: groups };
    }

    async function saveProjectLayout(control, reset) {
        const status = control.querySelector('[data-project-layout-status]');
        const buttons = control.querySelectorAll('[data-project-layout-save], [data-project-layout-reset]');
        buttons.forEach((button) => { button.disabled = true; });
        if (status) status.textContent = reset ? 'Restoring instance defaults…' : 'Saving project layout…';
        try {
            const tabs = getProjectState(control, 'tabs', 'tabs');
            const fields = getFieldState(control);
            const response = await fetch(control.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': control.dataset.csrf || '' },
                body: JSON.stringify({ tabs: tabs, fields: fields, reset: !!reset })
            });
            const result = await response.json();
            if (!response.ok) {
                const details = result.errors && typeof result.errors === 'object'
                    ? Object.entries(result.errors).flatMap(([field, messages]) => (Array.isArray(messages) ? messages : [messages]).map((message) => field + ': ' + message)).join(' ')
                    : '';
                throw new Error([result.error || result.message || 'Could not save this project layout.', details].filter(Boolean).join(' '));
            }
            if (reset) window.location.reload();
            else if (status) status.textContent = 'Project To-do layout saved.';
        } catch (error) {
            if (status) status.textContent = error.message;
            if (reset) {
                const toggle = control.querySelector('[data-project-layout-toggle]');
                const editor = control.querySelector('[data-project-layout-panel]');
                if (toggle) toggle.checked = true;
                if (editor) editor.hidden = false;
            }
            console.error('[LeanLib project layout]', error);
        } finally {
            buttons.forEach((button) => { button.disabled = false; });
        }
    }

    async function saveProjectPanelOrder(control, useDefault) {
        const status = control.querySelector('[data-project-integration-order-status]');
        const buttons = control.querySelectorAll('[data-project-integration-order-save], [data-project-integration-order-reset]');
        buttons.forEach((button) => { button.disabled = true; });
        if (status) status.textContent = useDefault ? 'Restoring instance order…' : 'Saving project order…';
        try {
            const order = Array.from(control.querySelectorAll('[data-project-integration-item]')).map((item) => item.dataset.projectIntegrationItem);
            const response = await fetch(control.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': control.dataset.csrf || '' },
                body: JSON.stringify({ order: order, useDefault: !!useDefault })
            });
            const result = await response.json();
            if (!response.ok || result.saved !== true) throw new Error(result.error || result.message || 'Could not save the project integration order.');
            window.location.reload();
        } catch (error) {
            if (status) status.textContent = error.message;
            const toggle = control.querySelector('[data-project-integration-order-toggle]');
            if (toggle && useDefault) toggle.checked = true;
            buttons.forEach((button) => { button.disabled = false; });
            console.error('[LeanLib project integration order]', error);
        }
    }

    function installProjectPanelOrderEditor(root) {
        const controls = [];
        if (root.matches && root.matches('[data-project-integration-order]')) controls.push(root);
        if (root.querySelectorAll) controls.push.apply(controls, root.querySelectorAll('[data-project-integration-order]'));
        controls.forEach(function (control) {
            if (control.dataset.orderInstalled === '1') return;
            control.dataset.orderInstalled = '1';
            const list = control.querySelector('[data-project-integration-sorter]');
            if (!list) return;
            let dragged = null;
            list.addEventListener('dragstart', function (event) {
                const item = event.target.closest('[data-project-integration-item]');
                if (!item) return;
                dragged = item;
                item.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
            });
            list.addEventListener('dragend', function () {
                if (dragged) dragged.classList.remove('is-dragging');
                dragged = null;
            });
            list.addEventListener('dragover', function (event) {
                const target = event.target.closest('[data-project-integration-item]');
                if (!dragged || !target || target === dragged) return;
                event.preventDefault();
                const bounds = target.getBoundingClientRect();
                list.insertBefore(dragged, event.clientY > bounds.top + bounds.height / 2 ? target.nextSibling : target);
            });
            control.addEventListener('click', function (event) {
                if (event.target.closest('[data-project-integration-order-save]')) saveProjectPanelOrder(control, false);
                if (event.target.closest('[data-project-integration-order-reset]')) saveProjectPanelOrder(control, true);
            });
            control.addEventListener('change', function (event) {
                const toggle = event.target.closest('[data-project-integration-order-toggle]');
                if (!toggle) return;
                const editor = control.querySelector('[data-project-integration-order-editor]');
                if (toggle.checked) {
                    if (editor) editor.hidden = false;
                } else {
                    saveProjectPanelOrder(control, true);
                }
            });
        });
    }

    document.addEventListener('change', function (event) {
        const layoutToggle = event.target.closest('[data-project-layout-toggle]');
        if (layoutToggle) {
            const control = layoutToggle.closest('[data-project-layout]');
            const editor = control && control.querySelector('[data-project-layout-panel]');
            if (layoutToggle.checked) {
                if (editor) editor.hidden = false;
            } else if (control) {
                saveProjectLayout(control, true);
            }
        }
        const checkbox = event.target.closest('[data-section-toggle]');
        if (!checkbox) return;
        const control = checkbox.closest('[data-leantimelib-visibility]');
        const previousEnabled = checkbox.dataset.savedEnabled === '1';
        if (control) saveVisibility(control, checkbox.dataset.sectionToggle, { enabled: checkbox.checked }, previousEnabled);
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-section-reset]');
        if (button) {
            const control = button.closest('[data-leantimelib-visibility]');
            if (control) saveVisibility(control, button.dataset.sectionReset, { useDefault: true });
        }

        const save = event.target.closest('[data-project-layout-save]');
        if (save) saveProjectLayout(save.closest('[data-project-layout]'), false);

        const resetLayout = event.target.closest('[data-project-layout-reset]');
        if (resetLayout) saveProjectLayout(resetLayout.closest('[data-project-layout]'), true);
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
