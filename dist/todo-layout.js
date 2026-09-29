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

    // Leantime's CSS assigns display values to .form-group, .row, .ui-tabs-tab and
    // .ui-tabs-panel. Those author rules override the browser's [hidden] presentation.
    function setLayoutHidden(element, hidden) {
        if (!element) return;
        const shouldHide = Boolean(hidden);
        element.hidden = shouldHide;
        element.classList.toggle('leantimelib-layout-hidden', shouldHide);
        element.setAttribute('aria-hidden', shouldHide ? 'true' : 'false');
    }

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
            setLayoutHidden(item, !visible);
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
            setLayoutHidden(panel, !visible);
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
        if (!details || !layout || typeof layout !== 'object') return false;
        const form = details.querySelector('form.formModal') || details;
        const main = form.querySelector(':scope > .row > .col-md-9 > .row.marginBottom > .col-md-12');
        const sidebar = form.querySelector(':scope > .row > .col-md-3');
        if (!main || !sidebar) return false;
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
        const mainColumn = details.querySelector('.col-md-9');
        let auxiliaryZone = mainColumn && mainColumn.querySelector(':scope > [data-leantimelib-field-zone="auxiliary"]');
        if (!auxiliaryZone && mainColumn) {
            auxiliaryZone = document.createElement('div');
            auxiliaryZone.className = 'leantimelib-auxiliary-fields';
            auxiliaryZone.dataset.leantimelibFieldZone = 'auxiliary';
            const footer = mainColumn.querySelector('.sticky-modal-footer');
            let anchor = footer ? footer.nextSibling : null;
            // Retain Leantime's whitespace, line break and divider after the Save buttons.
            while (anchor && ((anchor.nodeType === Node.TEXT_NODE && !anchor.textContent.trim()) || ['BR', 'HR'].includes(anchor.tagName))) {
                anchor = anchor.nextSibling;
            }
            mainColumn.insertBefore(auxiliaryZone, anchor);
        }

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
            const target = ['subtasks', 'discussion'].includes(id) && auxiliaryZone
                ? auxiliaryZone
                : (requestedZone === 'sidebar' && sectionId && nativeSections[sectionId]
                    ? nativeSections[sectionId]
                    : (requestedZone === 'sidebar' ? sidebarZone : mainZone));
            if (field.parentElement !== target) target.appendChild(field);
            setLayoutHidden(field, requestedZone === 'hidden');
        });

        (layout.sidebar || []).forEach((id) => {
            if (Object.prototype.hasOwnProperty.call(nativeFields, id)) return;
            const section = details.querySelector('.leantimelib-todo-section[data-leantimelib-section="' + CSS.escape(id) + '"]');
            if (section) {
                setLayoutHidden(section, false);
            }
        });
        (layout.hidden || []).forEach((id) => {
            if (Object.prototype.hasOwnProperty.call(nativeFields, id)) return;
            const section = details.querySelector('.leantimelib-todo-section[data-leantimelib-section="' + CSS.escape(id) + '"]');
            if (section && section.parentElement !== sidebarZone) sidebarZone.appendChild(section);
            if (section) {
                setLayoutHidden(section, true);
            }
        });

        ['organization', 'schedule'].forEach((sectionId) => {
            const coreId = sectionId === 'organization' ? 'organization' : 'dates';
            const heading = details.querySelector('#accordion_link_tickets-' + coreId);
            const row = heading && heading.closest('.row.marginBottom');
            if (!row) return;
            const visible = (layout.sidebar || []).includes(sectionId);
            setLayoutHidden(row, !visible);
        });

        // Order whole sidebar units by the same saved sequence shown in the Library editor.
        // Core accordion rows and plugin sections are siblings in this sequence, so drag order
        // changes their actual placement relative to one another.
        const sidebarUnits = new Map();
        ['organization', 'schedule'].forEach((id) => {
            const header = details.querySelector('#accordion_link_tickets-' + (id === 'organization' ? 'organization' : 'dates'));
            const row = header && header.closest('.row.marginBottom');
            if (row) sidebarUnits.set(id, row);
        });
        Object.entries(nativeFields).forEach(([id]) => {
            if (['type', 'project', 'milestone', 'sprint', 'related', 'workStart', 'workEnd', 'plannedHours'].includes(id)) return;
            if (!(layout.sidebar || []).includes(id)) return;
            const field = details.querySelector('[data-leantimelib-field="' + id + '"]');
            if (field) sidebarUnits.set(id, field);
        });
        details.querySelectorAll('.leantimelib-todo-section[data-leantimelib-section]').forEach((section) => {
            sidebarUnits.set(section.dataset.leantimelibSection, section);
        });
        let sidebarAnchor = sidebarZone.nextSibling;
        (layout.sidebar || []).forEach((id) => {
            const unit = sidebarUnits.get(id);
            if (!unit) return;
            setLayoutHidden(unit, false);
            if (unit !== sidebarAnchor) sidebar.insertBefore(unit, sidebarAnchor);
            sidebarAnchor = unit.nextSibling;
        });
        setLayoutHidden(sidebarZone, true);
        ['organization', 'schedule'].forEach((id) => {
            const parent = nativeSections[id];
            if (!parent) return;
            const childNodes = (layout.sidebar || [])
                .map((fieldId) => parent.querySelector('[data-leantimelib-field="' + fieldId + '"]'))
                .filter(Boolean);
            let childAnchor = parent.firstChild;
            childNodes.forEach((node) => {
                if (node !== childAnchor) parent.insertBefore(node, childAnchor);
                childAnchor = node.nextSibling;
            });
        });

        // Keep main fields in saved order. Hidden fields stay in the form but are not shown.
        const mainNodes = new Map(Array.from(mainZone.children).map((node) => [node.dataset.leantimelibField, node]));
        let mainAnchor = mainZone.firstChild;
        (layout.main || []).map((id) => mainNodes.get(id)).filter(Boolean).forEach((node) => {
            if (node !== mainAnchor) mainZone.insertBefore(node, mainAnchor);
            mainAnchor = node.nextSibling;
        });
        const auxiliaryNodes = new Map(Array.from(auxiliaryZone ? auxiliaryZone.children : []).map((node) => [node.dataset.leantimelibField, node]));
        let auxiliaryAnchor = auxiliaryZone ? auxiliaryZone.firstChild : null;
        (layout.main || []).filter((id) => ['subtasks', 'discussion'].includes(id)).map((id) => auxiliaryNodes.get(id)).filter(Boolean).forEach((node) => {
            if (node !== auxiliaryAnchor) auxiliaryZone.insertBefore(node, auxiliaryAnchor);
            auxiliaryAnchor = node.nextSibling;
        });
        return true;
    }

    function wrapAuxiliary(details, kind) {
        const iconSelector = kind === 'subtasks' ? '.fa-sitemap' : '.fa-comments';
        const heading = Array.from(details.querySelectorAll('h4')).find((item) => item.querySelector(iconSelector));
        if (!heading) return null;
        const nextContent = heading.nextElementSibling;
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
            if (nextContent) wrapper.appendChild(nextContent);
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
        if (!applyFields(details, layout.fields || {})) {
            if (container.dataset.leantimelibLayoutWarning !== '1') {
                console.warn('[LeantimeLib] To-do modal layout metadata was found, but the native form structure did not match.');
                container.dataset.leantimelibLayoutWarning = '1';
            }
            return false;
        }
        delete container.dataset.leantimelibLayoutWarning;
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
