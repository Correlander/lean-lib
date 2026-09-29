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

    function applyFields(details, layout, sectionDefinitions) {
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
        const definitions = Array.isArray(sectionDefinitions) ? sectionDefinitions : [];
        const sectionById = new Map(definitions.map((section) => [section.id, section]));
        const groupFor = new Map();
        Object.entries(layout.groups || {}).forEach(([sectionId, fields]) => {
            if (sectionById.has(sectionId) && Array.isArray(fields)) fields.forEach((id) => groupFor.set(id, sectionId));
        });
        const nativeSections = {
            organization: details.querySelector('#accordion_content-tickets-organization'),
            schedule: details.querySelector('#accordion_content-tickets-dates')
        };
        const nativeRows = {};
        const groupTargets = {};
        const nativeHeaderIds = { organization: 'organization', schedule: 'dates' };
        Object.entries(nativeHeaderIds).forEach(([id, suffix]) => {
            const heading = details.querySelector('#accordion_link_tickets-' + suffix);
            const row = heading && heading.closest('.row.marginBottom');
            const definition = sectionById.get(id);
            if (!row) return;
            nativeRows[id] = row;
            if (!definition) {
                setLayoutHidden(row, true);
                return;
            }
            const toggle = row.querySelector('#accordion_toggle_tickets-' + suffix) || heading;
            if (toggle) {
                let icon = toggle.querySelector('[data-leantimelib-heading-icon]')
                    || Array.from(toggle.querySelectorAll('span')).find((span) => /(^|\s)fa[-\w]+/.test(span.className));
                if (!icon) {
                    icon = document.createElement('span');
                    toggle.insertBefore(icon, toggle.querySelector('i')?.nextSibling || toggle.firstChild);
                }
                icon.dataset.leantimelibHeadingIcon = '';
                icon.className = definition.icon || '';
                setLayoutHidden(icon, !definition.icon);
                let label = toggle.querySelector('[data-leantimelib-heading-label]');
                if (!label) {
                    label = document.createElement('span');
                    label.dataset.leantimelibHeadingLabel = '';
                    Array.from(toggle.childNodes).forEach((node) => {
                        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) node.remove();
                    });
                    toggle.appendChild(label);
                }
                label.textContent = definition.label;
            }
            groupTargets[id] = nativeSections[id];
        });

        definitions.forEach((definition) => {
            if (Object.prototype.hasOwnProperty.call(nativeHeaderIds, definition.id)) return;
            let group = details.querySelector('.leantimelib-sidebar-group[data-leantimelib-group="' + CSS.escape(definition.id) + '"]');
            if (!group) {
                group = document.createElement('details');
                group.className = 'leantimelib-sidebar-group';
                group.open = true;
                group.dataset.leantimelibGroup = definition.id;
                const summary = document.createElement('summary');
                const icon = document.createElement('i');
                icon.dataset.leantimelibGroupIcon = '';
                icon.setAttribute('aria-hidden', 'true');
                const label = document.createElement('span');
                label.dataset.leantimelibGroupLabel = '';
                summary.append(icon, label);
                const content = document.createElement('div');
                content.dataset.leantimelibGroupContent = '';
                group.append(summary, content);
                sidebar.appendChild(group);
            }
            const summary = group.querySelector('summary');
            let chevron = summary.querySelector('[data-leantimelib-group-chevron]');
            if (!chevron) {
                chevron = document.createElement('i');
                chevron.className = 'fa fa-angle-down leantimelib-sidebar-group__chevron';
                chevron.dataset.leantimelibGroupChevron = '';
                chevron.setAttribute('aria-hidden', 'true');
                summary.insertBefore(chevron, summary.firstChild);
            }
            const icon = summary.querySelector('[data-leantimelib-group-icon]');
            icon.className = definition.icon || '';
            setLayoutHidden(icon, !definition.icon);
            summary.querySelector('[data-leantimelib-group-label]').textContent = definition.label;
            groupTargets[definition.id] = group.querySelector('[data-leantimelib-group-content]');
        });
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
                : (layout.sidebar || []).includes(id) ? 'sidebar'
                    : (layout.auxiliary || []).includes(id) ? 'auxiliary' : 'hidden';
            const assignedSection = groupFor.get(id);
            const target = ['subtasks', 'discussion'].includes(id) && auxiliaryZone
                ? auxiliaryZone
                : (assignedSection && groupTargets[assignedSection]
                    ? groupTargets[assignedSection]
                    : (requestedZone === 'sidebar' ? sidebarZone : mainZone));
            if (field.parentElement !== target) target.appendChild(field);
            setLayoutHidden(field, requestedZone === 'hidden');
        });

        const pluginSections = new Map();
        details.querySelectorAll('.leantimelib-todo-section[data-leantimelib-section]').forEach((section) => {
            pluginSections.set(section.dataset.leantimelibSection, section);
            const id = section.dataset.leantimelibSection;
            const assignedSection = groupFor.get(id);
            const target = assignedSection && groupTargets[assignedSection] ? groupTargets[assignedSection] : sidebarZone;
            if (section.parentElement !== target) target.appendChild(section);
            setLayoutHidden(section, !(layout.sidebar || []).includes(id));
        });

        const sidebarUnits = new Map();
        Object.entries(nativeRows).forEach(([id, row]) => sidebarUnits.set(id, row));
        definitions.forEach((definition) => {
            if (!Object.prototype.hasOwnProperty.call(nativeHeaderIds, definition.id)) {
                const group = details.querySelector('.leantimelib-sidebar-group[data-leantimelib-group="' + CSS.escape(definition.id) + '"]');
                if (group) sidebarUnits.set(definition.id, group);
            }
        });
        pluginSections.forEach((section, id) => sidebarUnits.set(id, section));
        Object.entries(nativeFields).forEach(([id]) => {
            if (!(layout.sidebar || []).includes(id) || groupFor.has(id)) return;
            const field = details.querySelector('[data-leantimelib-field="' + id + '"]');
            if (field) sidebarUnits.set(id, field);
        });
        definitions.forEach((definition) => {
            const unit = sidebarUnits.get(definition.id);
            if (unit) setLayoutHidden(unit, !(layout.sidebar || []).includes(definition.id));
        });
        let sidebarAnchor = sidebarZone.nextSibling;
        (layout.sidebar || []).forEach((id) => {
            if (groupFor.has(id)) return;
            const unit = sidebarUnits.get(id);
            if (!unit) return;
            setLayoutHidden(unit, false);
            if (unit !== sidebarAnchor) sidebar.insertBefore(unit, sidebarAnchor);
            sidebarAnchor = unit.nextSibling;
        });
        setLayoutHidden(sidebarZone, true);
        Object.entries(layout.groups || {}).forEach(([id, childIds]) => {
            const parent = groupTargets[id];
            if (!parent || !Array.isArray(childIds)) return;
            const childNodes = childIds.map((fieldId) =>
                details.querySelector('[data-leantimelib-field="' + CSS.escape(fieldId) + '"]')
                || pluginSections.get(fieldId)
            ).filter(Boolean);
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
        (layout.auxiliary || []).map((id) => auxiliaryNodes.get(id)).filter(Boolean).forEach((node) => {
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
        if (!applyFields(details, layout.fields || {}, layout.sidebarSections || [])) {
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
