(function () {
    'use strict';

    function installWorkspace(workspace) {
        if (workspace.dataset.workspaceInstalled === '1') return;
        workspace.dataset.workspaceInstalled = '1';
        let dragged = null;
        const form = workspace.closest('form');

        function place(item, zone) {
            item.dataset.widgetZone = zone.dataset.libraryZone;
            item.classList.toggle('is-parked', zone.dataset.libraryZone === 'parked');
            item.querySelectorAll('input[type="hidden"]').forEach(function (input) {
                if (input.name === 'tabEnabled[]' || input.name === 'sectionEnabled[]') input.disabled = zone.dataset.libraryZone === 'parked';
                if (input.name.startsWith('fieldLayout[')) input.name = 'fieldLayout[' + (zone.dataset.libraryZone === 'parked' ? 'parked' : zone.dataset.libraryZone) + '][]';
            });
            const button = item.querySelector('[data-widget-park], [data-widget-add]');
            if (button) {
                if (zone.dataset.libraryZone === 'parked') {
                    button.dataset.widgetAdd = '';
                    button.removeAttribute('data-widget-park');
                    button.textContent = '+';
                    button.title = 'Show';
                } else {
                    button.dataset.widgetPark = '';
                    button.removeAttribute('data-widget-add');
                    button.textContent = '×';
                    button.title = 'Hide';
                }
            }
        }

        workspace.addEventListener('dragstart', function (event) {
            const item = event.target.closest('[data-widget-kind]');
            if (!item || !workspace.contains(item)) return;
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.widgetId);
        });
        workspace.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
        });
        workspace.addEventListener('dragover', function (event) {
            const zone = event.target.closest('[data-library-zone]');
            if (!dragged || !zone) return;
            const kind = dragged.dataset.widgetKind;
            const allowed = kind === 'fields'
                ? ['main', 'sidebar', 'parked'].includes(zone.dataset.libraryZone)
                : kind === 'tabs'
                    ? ['tabs', 'parked'].includes(zone.dataset.libraryZone)
                    : ['sidebar', 'parked'].includes(zone.dataset.libraryZone);
            if (!allowed) return;
            event.preventDefault();
            if (kind === 'sectionHeader' || kind === 'sections') {
                const zoneName = zone.dataset.libraryZone;
                const targetUnit = event.target.closest('[data-section-preview], [data-widget-kind="sections"]');
                if (targetUnit && targetUnit !== dragged) {
                    const after = event.clientY > targetUnit.getBoundingClientRect().top + targetUnit.getBoundingClientRect().height / 2;
                    targetUnit.parentElement.insertBefore(dragged, after ? targetUnit.nextSibling : targetUnit);
                } else {
                    const target = zoneName === 'sidebar'
                        ? workspace.querySelector('.lt-library-sidebar-units')
                        : workspace.querySelector('.lt-library-ticket__parked > [data-library-zone="parked"]');
                    if (target) {
                        target.appendChild(dragged);
                        if (kind === 'sections') place(dragged, target);
                    }
                }
                if (kind === 'sectionHeader') setSectionZone(dragged, zoneName);
                else {
                    const target = dragged.closest('[data-library-zone]');
                    if (target) place(dragged, target);
                }
                return;
            }
            const target = event.target.closest('[data-widget-kind]');
            if (target === dragged) return;
            if (!target || target.dataset.widgetKind !== kind || target.parentElement !== zone) {
                zone.appendChild(dragged);
                place(dragged, zone);
                return;
            }
            const after = event.clientY > target.getBoundingClientRect().top + target.getBoundingClientRect().height / 2;
            zone.insertBefore(dragged, after ? target.nextSibling : target);
            place(dragged, zone);
        });
        workspace.addEventListener('drop', function (event) { if (event.target.closest('[data-library-zone]')) event.preventDefault(); });
        workspace.addEventListener('click', function (event) {
            const sectionButton = event.target.closest('[data-section-park]');
            if (sectionButton) {
                const section = sectionButton.closest('[data-section-preview]');
                if (!section) return;
                const parked = section.dataset.sectionZone !== 'parked';
                const target = parked
                    ? workspace.querySelector('.lt-library-ticket__parked > [data-library-zone="parked"]')
                    : workspace.querySelector('.lt-library-sidebar-units');
                if (!target) return;
                target.appendChild(section);
                setSectionZone(section, parked ? 'parked' : 'sidebar');
                return;
            }
            const button = event.target.closest('[data-widget-park], [data-widget-add]');
            if (!button) return;
            const item = button.closest('[data-widget-kind]');
            if (!item) return;
            const defaultZone = item.dataset.defaultZone || (item.dataset.widgetKind === 'tabs' ? 'tabs' : 'sidebar');
            const name = button.hasAttribute('data-widget-add') ? defaultZone : 'parked';
            const zone = workspace.querySelector('[data-library-zone="' + name + '"]');
            if (zone) { zone.appendChild(item); place(item, zone); }
        });

        function setSectionZone(section, zoneName) {
            const parked = zoneName === 'parked';
            const sectionId = section.dataset.sectionPreview;
            section.dataset.sectionZone = zoneName;
            section.toggleAttribute('data-parked-section', parked);
            const button = section.querySelector('[data-section-park]');
            if (button) {
                button.textContent = parked ? '+' : '×';
                button.title = (parked ? 'Restore ' : 'Park ') + (sectionId === 'organization' ? 'Organization' : 'Schedule') + ' section';
            }
            const headerInput = section.querySelector('h3 input[name^="fieldLayout["]');
            if (headerInput) headerInput.name = 'fieldLayout[' + zoneName + '][]';
            const childZone = section.querySelector('[data-section-children]');
            if (childZone) childZone.dataset.libraryZone = zoneName;
            section.querySelectorAll('[data-widget-kind="fields"]').forEach(function (item) {
                if (childZone) place(item, childZone);
            });
        }
        if (form) form.addEventListener('submit', function () {
            workspace.querySelectorAll('[data-widget-kind]').forEach(function (item) {
                const zone = item.closest('[data-library-zone]');
                if (zone) place(item, zone);
            });
        });
    }

    function installLayoutEditor(editor) {
        if (editor.dataset.editorInstalled === '1') return;
        editor.dataset.editorInstalled = '1';
        let dragged = null;

        editor.addEventListener('dragstart', function (event) {
            const item = event.target.closest('[data-widget-id]');
            if (!item) return;
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.widgetId);
        });

        editor.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
        });

        editor.addEventListener('dragover', function (event) {
            event.preventDefault();
            if (!dragged) return;

            const lane = event.target.closest('[data-library-lane]');
            if (!lane || !editor.contains(lane)) return;
            const target = event.target.closest('[data-widget-id]');
            if (!target || target === dragged) {
                lane.appendChild(dragged);
                return;
            }

            const bounds = target.getBoundingClientRect();
            const vertical = lane.dataset.libraryLane === 'visible' || lane.dataset.libraryLane === 'hidden';
            const after = vertical
                ? event.clientY > bounds.top + bounds.height / 2
                : event.clientX > bounds.left + bounds.width / 2;
            lane.insertBefore(dragged, after ? target.nextSibling : target);
        });

        editor.addEventListener('click', function (event) {
            const button = event.target.closest('[data-widget-hide], [data-widget-show]');
            if (!button) return;
            const item = button.closest('[data-widget-id]');
            const laneName = button.hasAttribute('data-widget-hide') ? 'hidden' : 'visible';
            const lane = editor.querySelector('[data-library-lane="' + laneName + '"]');
            if (item && lane) lane.appendChild(item);
        });

        editor.addEventListener('drop', function (event) { event.preventDefault(); });

        const form = editor.closest('form');
        if (form) form.addEventListener('submit', function () {
            editor.querySelectorAll('[data-widget-enabled]').forEach(function (input) {
                input.disabled = input.closest('[data-library-lane]').dataset.libraryLane !== 'visible';
            });
        });
    }

    function installFieldEditor(editor) {
        if (editor.dataset.fieldEditorInstalled === '1') return;
        editor.dataset.fieldEditorInstalled = '1';
        let dragged = null;
        editor.addEventListener('dragstart', function (event) {
            const item = event.target.closest('[data-widget-id]');
            if (!item) return;
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.widgetId);
        });
        editor.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
        });
        editor.addEventListener('dragover', function (event) {
            const lane = event.target.closest('[data-field-lane]');
            if (!dragged || !lane) return;
            event.preventDefault();
            const target = event.target.closest('[data-widget-id]');
            if (!target || target === dragged) lane.appendChild(dragged);
            else {
                const bounds = target.getBoundingClientRect();
                lane.insertBefore(dragged, event.clientY > bounds.top + bounds.height / 2 ? target.nextSibling : target);
            }
            dragged.dataset.widgetZone = lane.dataset.fieldLane;
        });
        editor.addEventListener('drop', function (event) { if (event.target.closest('[data-field-lane]')) event.preventDefault(); });
        editor.addEventListener('click', function (event) {
            const button = event.target.closest('[data-field-toggle]');
            if (!button) return;
            const item = button.closest('[data-widget-id]');
            const hidden = item && item.dataset.widgetZone !== 'hidden';
            const lane = editor.querySelector('[data-field-lane="' + (hidden ? 'hidden' : (item.dataset.defaultZone || 'main')) + '"]');
            if (item && lane) {
                lane.appendChild(item);
                item.dataset.widgetZone = lane.dataset.fieldLane;
                button.textContent = lane.dataset.fieldLane === 'hidden' ? 'Show' : 'Hide';
            }
        });
    }

    function scan(root) {
        if (root.matches && root.matches('[data-library-workspace]')) installWorkspace(root);
        if (root.querySelectorAll) {
            root.querySelectorAll('[data-library-workspace]').forEach(installWorkspace);
            root.querySelectorAll('[data-library-layout-editor]').forEach(installLayoutEditor);
            root.querySelectorAll('[data-field-editor]').forEach(installFieldEditor);
        }
    }

    function start() {
        scan(document);
        if (!window.MutationObserver || !document.body) return;
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) scan(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
