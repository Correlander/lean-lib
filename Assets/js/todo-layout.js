(function () {
    'use strict';

    const selector = '.ticketTabs';
    let scheduled = false;
    const nativeFields = {
        headline: '[name="headline"]', status: '#status-select', priority: '#priority', effort: '#storypoints',
        editor: '#editorId', collaborators: '#collaborators', dueDate: '[name="dateToFinish"]', tags: '#tags',
        description: '#descriptionEditor', subtasks: '@subtasks', discussion: '@discussion', type: '#type', project: '[name="projectId"]', milestone: '[name="milestoneid"]',
        sprint: '#sprint-select', related: '[name="dependingTicketId"]', workStart: '[name="editFrom"]',
        workEnd: '[name="editTo"]', plannedHours: '[name="planHours"]'
    };

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

    function applyFields(details, layout) {
        if (!details || !layout || typeof layout !== 'object') return;
        const main = details.querySelector('.col-md-9 > .row.marginBottom > .col-md-12');
        const sidebar = details.querySelector(':scope > .row > .col-md-3');
        if (!main || !sidebar) return;
        let mainZone = main.querySelector(':scope > [data-leantimelib-field-zone="main"]');
        let sidebarZone = sidebar.querySelector(':scope > [data-leantimelib-field-zone="sidebar"]');
        if (!mainZone) {
            mainZone = document.createElement('div');
            mainZone.className = 'leantimelib-native-fields';
            mainZone.dataset.leantimelibFieldZone = 'main';
            main.insertBefore(mainZone, main.firstChild);
        }
        if (!sidebarZone) {
            sidebarZone = document.createElement('div');
            sidebarZone.className = 'leantimelib-native-fields';
            sidebarZone.dataset.leantimelibFieldZone = 'sidebar';
            sidebar.insertBefore(sidebarZone, sidebar.firstChild);
        }
        const nativeSections = {
            organization: details.querySelector('#accordion_content-tickets-organization'),
            schedule: details.querySelector('#accordion_content-tickets-dates')
        };

        Object.entries(nativeFields).forEach(([id, fieldSelector]) => {
            let field = details.querySelector('[data-leantimelib-field="' + id + '"]');
            if (!field) {
                if (fieldSelector === '@subtasks') field = wrapAuxiliary(details, 'subtasks');
                else if (fieldSelector === '@discussion') field = wrapAuxiliary(details, 'discussion');
                else {
                    const input = details.querySelector(fieldSelector);
                    field = input && (input.closest('.form-group') || input);
                }
                if (field) field.dataset.leantimelibField = id;
            }
            if (!field) return;
            const requestedZone = (layout.main || []).includes(id) ? 'main'
                : (layout.sidebar || []).includes(id) ? 'sidebar' : 'hidden';
            const sectionId = ['type', 'project', 'milestone', 'sprint', 'related'].includes(id) ? 'organization'
                : (['workStart', 'workEnd', 'plannedHours'].includes(id) ? 'schedule' : null);
            const target = requestedZone === 'sidebar' && sectionId && nativeSections[sectionId]
                ? nativeSections[sectionId]
                : (requestedZone === 'sidebar' ? sidebarZone : mainZone);
            if (field.parentElement !== target) target.appendChild(field);
            field.hidden = requestedZone === 'hidden';
            field.setAttribute('aria-hidden', requestedZone === 'hidden' ? 'true' : 'false');
        });

        (layout.sidebar || []).forEach((id) => {
            if (Object.prototype.hasOwnProperty.call(nativeFields, id)) return;
            const section = details.querySelector('.leantimelib-todo-section[data-leantimelib-section="' + CSS.escape(id) + '"]');
            if (section && section.parentElement !== sidebarZone) sidebarZone.appendChild(section);
            if (section) {
                section.hidden = false;
                section.setAttribute('aria-hidden', 'false');
            }
        });
        (layout.hidden || []).forEach((id) => {
            if (Object.prototype.hasOwnProperty.call(nativeFields, id)) return;
            const section = details.querySelector('.leantimelib-todo-section[data-leantimelib-section="' + CSS.escape(id) + '"]');
            if (section && section.parentElement !== sidebarZone) sidebarZone.appendChild(section);
            if (section) {
                section.hidden = true;
                section.setAttribute('aria-hidden', 'true');
            }
        });

        ['organization', 'schedule'].forEach((sectionId) => {
            const coreId = sectionId === 'organization' ? 'organization' : 'dates';
            const heading = details.querySelector('#accordion_link_tickets-' + coreId);
            const row = heading && heading.closest('.row.marginBottom');
            if (!row) return;
            const visible = (layout.sidebar || []).includes(sectionId);
            row.hidden = !visible;
            row.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });

        // Keep each zone in the saved order. Hidden controls remain in the form,
        // so existing values still submit when a widget is parked.
        ['main', 'sidebar'].forEach((zoneName) => {
            const zone = zoneName === 'main' ? mainZone : sidebarZone;
            const order = layout[zoneName] || [];
            const zones = zoneName === 'sidebar' ? [sidebarZone, nativeSections.organization, nativeSections.schedule].filter(Boolean) : [zone];
            const nodes = new Map(zones.flatMap((parent) => Array.from(parent.children)).map((node) => [node.dataset.leantimelibField || node.dataset.leantimelibSection, node]));
            const ordered = order.map((id) => nodes.get(id)).filter(Boolean);
            nodes.forEach((node) => { if (!ordered.includes(node)) ordered.push(node); });
            const headerRank = { organization: order.indexOf('organization'), schedule: order.indexOf('schedule') };
            const sectionRows = ['organization', 'schedule'].sort((a, b) => (headerRank[a] < 0 ? 999 : headerRank[a]) - (headerRank[b] < 0 ? 999 : headerRank[b]));
            let sectionAnchor = sidebarZone.nextSibling;
            sectionRows.forEach((id) => {
                const header = details.querySelector('#accordion_link_tickets-' + (id === 'organization' ? 'organization' : 'dates'));
                const row = header && header.closest('.row.marginBottom');
                if (row && order.includes(id)) {
                    sidebar.insertBefore(row, sectionAnchor);
                    sectionAnchor = row.nextSibling;
                }
            });
            const groupedIds = new Set(['organization', 'schedule', 'type', 'project', 'milestone', 'sprint', 'related', 'workStart', 'workEnd', 'plannedHours']);
            ordered.filter((node) => !groupedIds.has(node.dataset.leantimelibField || node.dataset.leantimelibSection)).forEach((node, index) => { if (zone.children[index] !== node) zone.insertBefore(node, zone.children[index] || null); });
            if (zoneName === 'sidebar') {
                ['organization', 'schedule'].forEach((id) => {
                    const parent = nativeSections[id];
                    if (!parent) return;
                    const sectionOrder = order.filter((fieldId) => parent.querySelector('[data-leantimelib-field="' + fieldId + '"]'));
                    const childNodes = sectionOrder.map((fieldId) => parent.querySelector('[data-leantimelib-field="' + fieldId + '"]')).filter(Boolean);
                    childNodes.forEach((node, index) => { if (parent.children[index] !== node) parent.insertBefore(node, parent.children[index] || null); });
                });
            }
        });
    }

    function wrapAuxiliary(details, kind) {
        const iconSelector = kind === 'subtasks' ? '.fa-sitemap' : '.fa-comments';
        const heading = Array.from(details.querySelectorAll('h4')).find((item) => item.querySelector(iconSelector));
        if (!heading) return null;
        const wrapper = document.createElement('section');
        wrapper.className = 'leantimelib-auxiliary-widget';
        heading.parentNode.insertBefore(wrapper, heading);
        wrapper.appendChild(heading);
        if (kind === 'subtasks') {
            const list = details.querySelector('#ticketSubtasks');
            const indicator = list && list.nextElementSibling;
            if (list) wrapper.appendChild(list);
            if (indicator && indicator.classList.contains('htmx-indicator')) wrapper.appendChild(indicator);
        } else {
            const content = heading.nextElementSibling;
            if (content) wrapper.appendChild(content);
        }
        return wrapper;
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
        applyFields(details, layout.fields || {});
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
