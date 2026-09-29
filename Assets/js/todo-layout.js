(function () {
    'use strict';

    function reorderTabs(container, order) {
        const list = container.querySelector(':scope > ul');
        if (!list || !Array.isArray(order)) return;
        const items = Array.from(list.children).filter((item) => item.tagName === 'LI');
        const byId = new Map();
        items.forEach((item) => {
            const anchor = item.querySelector('a[href^="#"]');
            if (anchor) byId.set(anchor.getAttribute('href').slice(1), item);
        });
        const ordered = order.map((id) => byId.get(id)).filter(Boolean);
        items.forEach((item) => { if (!ordered.includes(item)) ordered.push(item); });
        ordered.forEach((item) => list.appendChild(item));

        const panels = Array.from(container.children).filter((child) => child.id);
        const panelsById = new Map(panels.map((panel) => [panel.id, panel]));
        const orderedPanels = order.map((id) => panelsById.get(id)).filter(Boolean);
        panels.forEach((panel) => { if (!orderedPanels.includes(panel)) orderedPanels.push(panel); });
        orderedPanels.forEach((panel) => container.appendChild(panel));

        if (window.jQuery) {
            try {
                const $tabs = window.jQuery(container);
                if ($tabs.data('ui-tabs')) $tabs.tabs('refresh');
            } catch (error) {
                console.warn('[LeantimeLib todo layout] Could not refresh native tab widget.', error);
            }
        }
    }

    function reorderSidebar(container, order) {
        if (!container || !Array.isArray(order)) return;
        const elements = new Map();
        const organization = container.querySelector('#accordion_link_tickets-organization');
        const schedule = container.querySelector('#accordion_link_tickets-dates');
        if (organization) elements.set('organization', organization.closest('.row.marginBottom') || organization.closest('.row'));
        if (schedule) elements.set('schedule', schedule.closest('.row.marginBottom') || schedule.closest('.row'));
        container.querySelectorAll('.leantimelib-todo-section[data-leantimelib-section]').forEach((section) => {
            elements.set(section.dataset.leantimelibSection, section);
        });
        order.forEach((id) => {
            const element = elements.get(id);
            if (element) container.appendChild(element);
        });
    }

    function apply(container) {
        if (!container || container.dataset.leantimelibLayoutApplied === '1') return;
        const configNode = container.querySelector('.leantimelib-todo-layout-order');
        if (!configNode) return;
        let layout;
        try { layout = JSON.parse(configNode.textContent || '{}'); }
        catch (error) {
            console.error('[LeantimeLib todo layout] Invalid layout configuration.', error);
            return;
        }
        reorderTabs(container, layout.tabs || []);
        const details = container.querySelector('#ticketdetails');
        const sidebar = details && details.querySelector('.col-md-3');
        reorderSidebar(sidebar, layout.sections || []);
        container.dataset.leantimelibLayoutApplied = '1';
    }

    function scan(node) {
        if (!node || node.nodeType !== 1) return;
        if (node.matches('.ticketTabs')) apply(node);
        node.querySelectorAll('.ticketTabs').forEach(apply);
    }

    function start() {
        scan(document.documentElement);
        if (!document.body || !window.MutationObserver) return;
        const observer = new MutationObserver((records) => {
            records.forEach((record) => record.addedNodes.forEach(scan));
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
