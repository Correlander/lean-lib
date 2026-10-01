(function () {
    'use strict';

    function applyCompanyLayout(root) {
        if (!root || root.dataset.leantimelibCompanyLayoutApplied === '1') return;
        const config = root.querySelector('[data-leantimelib-company-layout]');
        const list = root.querySelector(':scope > ul');
        if (!config || !list) return;
        let layout;
        try { layout = JSON.parse(config.dataset.leantimelibCompanyLayout || '{}'); } catch (error) { return; }

        const headers = new Map();
        Array.from(list.children).forEach((item) => {
            const link = item.querySelector('a[href^="#"]');
            if (link) headers.set(link.getAttribute('href').slice(1), item);
        });
        const panels = new Map();
        Array.from(root.children).forEach((child) => { if (child.id) panels.set(child.id, child); });
        const orderedHeaders = (layout.tabs || []).map((id) => headers.get(id)).filter(Boolean);
        const orderedPanels = (layout.tabs || []).map((id) => panels.get(id)).filter(Boolean);
        orderedHeaders.forEach((node) => list.appendChild(node));
        let anchor = list.nextElementSibling;
        orderedPanels.forEach((node) => { if (node !== anchor) root.insertBefore(node, anchor); else anchor = anchor.nextElementSibling; });

        const stash = root.querySelector('[data-company-widget-stash]');
        const detailsPanel = panels.get('details');
        const detailsForm = detailsPanel && detailsPanel.querySelector('form input[name="saveSettings"]')?.closest('form');
        const sectionIds = [
            'company.details.profile',
            'company.details.defaults',
            'company.details.notifications',
            'company.details.notificationRelevance'
        ];
        if (detailsForm) {
            if (!detailsForm.id) detailsForm.id = 'leantimelib-company-settings-form';
            const headings = Array.from(detailsForm.querySelectorAll(':scope > h4.widgettitle'));
            headings.forEach((heading, index) => {
                const id = sectionIds[index];
                if (!id || root.querySelector('[data-company-live-widget="' + CSS.escape(id) + '"]')) return;
                const widget = document.createElement('section');
                widget.dataset.companyLiveWidget = id;
                let node = heading;
                const nextHeading = headings[index + 1] || null;
                const saveButton = detailsForm.querySelector('#saveBtn');
                while (node && node !== nextHeading && node !== saveButton) {
                    const next = node.nextSibling;
                    widget.appendChild(node);
                    node = next;
                }
                widget.querySelectorAll('input, select, textarea, button').forEach((control) => control.setAttribute('form', detailsForm.id));
                if (stash) stash.appendChild(widget);
            });
            const saveButton = detailsForm.querySelector('#saveBtn');
            if (saveButton && stash && !root.querySelector('[data-company-live-widget="company.details.save"]')) {
                const widget = document.createElement('div');
                widget.dataset.companyLiveWidget = 'company.details.save';
                saveButton.setAttribute('form', detailsForm.id);
                widget.appendChild(saveButton);
                stash.appendChild(widget);
            }
        }

        const logoForm = detailsPanel && detailsPanel.querySelector('form input[name="saveLogo"]')?.closest('form');
        const logoColumn = logoForm && logoForm.closest('.col-md-4');
        if (logoColumn && stash) {
            const logoWidget = document.createElement('div');
            logoWidget.dataset.companyLiveWidget = 'company.logo';
            logoWidget.appendChild(logoColumn);
            stash.appendChild(logoWidget);
        }

        const apiPanel = panels.get('apiKeys');
        if (apiPanel && stash) {
            const apiWidget = document.createElement('div');
            apiWidget.dataset.companyLiveWidget = 'company.apiKeys';
            while (apiPanel.firstChild) apiWidget.appendChild(apiPanel.firstChild);
            stash.appendChild(apiWidget);
        }

        const widgets = layout.widgets || {};
        Object.keys(widgets).forEach((tabId) => {
            const panel = panels.get(tabId);
            const targetPanel = panel || root.querySelector('#' + CSS.escape(tabId));
            if (!targetPanel) return;
            const regionLayouts = widgets[tabId] || {};
            Object.keys(regionLayouts).forEach((regionName) => {
                const ids = regionLayouts[regionName] || [];
                let zone = targetPanel.querySelector('[data-leantimelib-company-region="' + CSS.escape(regionName) + '"]');
                if (!zone) {
                    zone = document.createElement('div');
                    zone.dataset.leantimelibCompanyRegion = regionName;
                    targetPanel.appendChild(zone);
                }
                ids.forEach((id) => {
                    const providerNode = root.querySelector('[data-company-live-widget="' + CSS.escape(id) + '"]');
                    if (providerNode) zone.appendChild(providerNode);
                });
            });
        });
        if (stash) stash.remove();
        root.dataset.leantimelibCompanyLayoutApplied = '1';
        if (window.jQuery) {
            try {
                const tabs = window.jQuery(root);
                if (tabs.data('ui-tabs')) {
                    tabs.tabs('refresh');
                    if (root.dataset.leantimelibCompanyHashHandler !== '1') {
                        // Leantime 3.10.0's inline callback only writes
                        // ui.newPanel.selector, which is undefined in jQuery UI.
                        // Replace that option with the equivalent using the actual panel ID.
                        tabs.tabs('option', 'activate', function (event, ui) {
                            const panelId = ui && ui.newPanel && ui.newPanel.attr('id');
                            if (!panelId || !panels.has(panelId)) return;

                            const hash = '#' + panelId;
                            if (window.location.hash !== hash) {
                                window.history.replaceState(null, '', window.location.pathname + window.location.search + hash);
                            }
                        });
                        root.dataset.leantimelibCompanyHashHandler = '1';
                    }
                    const activeHash = window.location.hash.replace(/^#/, '');
                    const selectedTab = panels.has(activeHash) ? activeHash : ((layout.tabs || [])[0] || 'details');
                    const selectedIndex = Array.from(list.children).filter((item) => !item.hidden).findIndex((item) => item.querySelector('a[href="#' + CSS.escape(selectedTab) + '"]'));
                    if (selectedIndex >= 0) tabs.tabs('option', 'active', selectedIndex);
                }
            } catch (error) { console.warn('[LeanLib company settings] Could not refresh native tabs.', error); }
        }
    }

    function start() {
        document.querySelectorAll('.companyTabs').forEach(applyCompanyLayout);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
