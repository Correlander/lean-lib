(function () {
    'use strict';

    const selector = '.ticketTabs';
    let scheduled = false;

    function reorderTabs(container, order) {
        const list = Array.from(container.children).find((child) => child.tagName === 'UL');
        if (!list || !Array.isArray(order)) return;

        const activeLink = list.querySelector('li.ui-tabs-active a[href^="#"]');
        const activeId = activeLink ? activeLink.getAttribute('href').slice(1) : null;
        const headers = new Map();
        Array.from(list.children).forEach((item) => {
            const link = item.querySelector('a[href^="#"]');
            if (link) headers.set(link.getAttribute('href').slice(1), item);
        });

        const orderedHeaders = order.map((id) => headers.get(id)).filter(Boolean);
        headers.forEach((item, id) => {
            const visible = order.includes(id);
            item.hidden = !visible;
            item.setAttribute('aria-hidden', visible ? 'false' : 'true');
            if (!visible) orderedHeaders.push(item);
        });
        orderedHeaders.forEach((item, index) => {
            if (list.children[index] !== item) list.insertBefore(item, list.children[index] || null);
        });

        const panels = new Map();
        Array.from(container.children).forEach((child) => {
            if (child.id) panels.set(child.id, child);
        });
        const orderedPanels = order.map((id) => panels.get(id)).filter(Boolean);
        panels.forEach((panel, id) => {
            const visible = order.includes(id);
            panel.hidden = !visible;
            panel.setAttribute('aria-hidden', visible ? 'false' : 'true');
            if (!visible) orderedPanels.push(panel);
        });

        // Keep the native tab list first; panel order starts immediately after it.
        let anchor = list.nextElementSibling;
        orderedPanels.forEach((panel) => {
            if (panel === anchor) {
                anchor = anchor.nextElementSibling;
            } else {
                container.insertBefore(panel, anchor);
            }
        });

        if (window.jQuery) {
            try {
                const tabs = window.jQuery(container);
                if (tabs.data('ui-tabs')) {
                    tabs.tabs('refresh');
                    if (order.length) tabs.tabs('option', 'active', activeId && order.includes(activeId) ? order.indexOf(activeId) : 0);
                    else tabs.tabs('option', 'active', false);
                }
            } catch (error) {
                console.warn('[LeantimeLib todo layout] Could not refresh the tab widget.', error);
            }
        }
    }

    function reorderSidebar(container, order) {
        if (!container || !Array.isArray(order)) return;
        const elements = new Map();
        const organization = container.querySelector('#accordion_link_tickets-organization');
        const schedule = container.querySelector('#accordion_link_tickets-dates');

        if (organization) {
            const row = organization.closest('.row.marginBottom') || organization.closest('.row');
            if (row) elements.set('organization', row);
        }
        if (schedule) {
            const row = schedule.closest('.row.marginBottom') || schedule.closest('.row');
            if (row) elements.set('schedule', row);
        }
        container.querySelectorAll('.leantimelib-todo-section[data-leantimelib-section]').forEach((section) => {
            elements.set(section.dataset.leantimelibSection, section);
        });

        elements.forEach((element, id) => {
            const visible = order.includes(id);
            element.hidden = !visible;
            element.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });
        const ordered = order.map((id) => elements.get(id)).filter((element) => element && element.parentElement === container);
        elements.forEach((element, id) => {
            if (element.parentElement === container && !order.includes(id)) ordered.push(element);
        });
        ordered.forEach((element, index) => {
            if (container.children[index] !== element) container.insertBefore(element, container.children[index] || null);
        });
    }

    function apply(container) {
        const config = container.querySelector('.leantimelib-todo-layout-order[data-layout]');
        if (!config) {
            if (container.dataset.leantimelibLayoutWarning !== '1') {
                console.warn('[LeantimeLib] To-do modal found without layout metadata; check Library ticketTabs hook output.');
                container.dataset.leantimelibLayoutWarning = '1';
            }
            return false;
        }

        let layout;
        try {
            layout = JSON.parse(config.dataset.layout || '{}');
        } catch (error) {
            console.error('[LeantimeLib todo layout] Invalid layout configuration.', error);
            return false;
        }

        reorderTabs(container, layout.tabs || []);
        const details = container.querySelector('#ticketdetails');
        reorderSidebar(details && details.querySelector('.col-md-3'), layout.sections || []);
        const signature = JSON.stringify(layout);
        if (container.dataset.leantimelibLayoutApplied !== signature) {
            container.dataset.leantimelibLayoutApplied = signature;
            console.info('[LeantimeLib] Applied saved To-do layout.', layout);
        }
        return true;
    }

    function collect(node, containers) {
        if (!node || node.nodeType !== 1) return;
        if (node.matches(selector)) containers.add(node);
        const parent = node.closest(selector);
        if (parent) containers.add(parent);
        node.querySelectorAll(selector).forEach((container) => containers.add(container));
    }

    function scheduleApply(nodes) {
        const containers = new Set();
        nodes.forEach((node) => collect(node, containers));
        if (!containers.size || scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(() => {
            scheduled = false;
            containers.forEach(apply);
        });
    }

    function start() {
        scheduleApply([document.documentElement]);
        if (!document.body || !window.MutationObserver) return;

        const observer = new MutationObserver((records) => {
            const nodes = [];
            records.forEach((record) => record.addedNodes.forEach((node) => nodes.push(node)));
            scheduleApply(nodes);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
