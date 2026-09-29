(function () {
    'use strict';

    function installWorkspace(workspace) {
        if (workspace.dataset.workspaceInstalled === '1') return;
        workspace.dataset.workspaceInstalled = '1';
        let dragged = null;
        const form = workspace.closest('form');

        function groupZone(sectionId) {
            return sectionId ? workspace.querySelector('[data-section-children="' + CSS.escape(sectionId) + '"]') : null;
        }

        function setGroup(item, sectionId) {
            if (sectionId) item.dataset.parentSection = sectionId;
            else delete item.dataset.parentSection;
            let input = item.querySelector('[data-group-input]');
            if (sectionId && form && !input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.dataset.groupInput = '';
                item.appendChild(input);
            }
            if (input) {
                if (!sectionId) input.remove();
                else {
                    input.name = 'fieldLayout[groups][' + sectionId + '][]';
                    input.value = item.dataset.widgetId;
                }
            }
        }

        function place(item, zone) {
            if (!item || !zone) return;
            const zoneName = zone.dataset.libraryZone;
            if (zoneName !== 'parked' && item.dataset.widgetKind !== 'sectionHeader') {
                setGroup(item, zone.dataset.sectionChildren || null);
            }
            item.dataset.widgetZone = zone.dataset.libraryZone;
            item.classList.toggle('is-parked', zone.dataset.libraryZone === 'parked');
            if (item.dataset.widgetKind === 'tabs') {
                const enabled = item.querySelector('input[name="tabEnabled[]"]');
                if (enabled) enabled.disabled = zoneName === 'parked';
            } else {
                const placement = item.querySelector('[data-placement-input]');
                if (placement) placement.name = 'fieldLayout[' + (zoneName === 'parked' ? 'parked' : zoneName) + '][]';
            }
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
                ? (dragged.dataset.defaultZone === 'auxiliary'
                    ? ['auxiliary', 'parked'].includes(zone.dataset.libraryZone)
                    : ['main', 'sidebar', 'parked'].includes(zone.dataset.libraryZone))
                : kind === 'tabs'
                    ? ['tabs', 'parked'].includes(zone.dataset.libraryZone)
                    : ['sidebar', 'parked'].includes(zone.dataset.libraryZone);
            if (!allowed) return;
            event.preventDefault();
            if (kind === 'sectionHeader') {
                const zoneName = zone.dataset.libraryZone;
                const targetUnit = event.target.closest('[data-section-preview], [data-widget-kind="sections"], [data-widget-kind="fields"]');
                const destination = workspace.querySelector(zoneName === 'sidebar' ? '.lt-library-sidebar-units' : '.lt-library-ticket__parked > [data-library-zone="parked"]');
                if (targetUnit && targetUnit !== dragged && targetUnit.parentElement === destination) {
                    const after = event.clientY > targetUnit.getBoundingClientRect().top + targetUnit.getBoundingClientRect().height / 2;
                    destination.insertBefore(dragged, after ? targetUnit.nextSibling : targetUnit);
                } else {
                    if (destination) {
                        destination.appendChild(dragged);
                    }
                }
                if (kind === 'sectionHeader') setSectionZone(dragged, zoneName);
                return;
            }
            const destination = zone;
            const target = event.target.closest('[data-widget-kind]');
            if (target === dragged) return;
            if (!target || target.dataset.widgetKind !== kind || target.parentElement !== destination) {
                destination.appendChild(dragged);
                place(dragged, destination);
                return;
            }
            const after = event.clientY > target.getBoundingClientRect().top + target.getBoundingClientRect().height / 2;
            destination.insertBefore(dragged, after ? target.nextSibling : target);
            place(dragged, destination);
        });
        workspace.addEventListener('drop', function (event) { if (event.target.closest('[data-library-zone]')) event.preventDefault(); });
        workspace.addEventListener('click', function (event) {
            const addSection = event.target.closest('[data-sidebar-section-add]');
            if (addSection) return; // The section-manager listener handles this control.
            const deleteSection = event.target.closest('[data-sidebar-section-delete]');
            if (deleteSection) {
                const id = deleteSection.dataset.sidebarSectionDelete;
                const section = workspace.querySelector('[data-section-preview="' + CSS.escape(id) + '"]');
                const raw = workspace.querySelector('.lt-library-sidebar-units');
                const children = section && section.querySelector('[data-section-children]');
                if (section && children && raw) {
                    Array.from(children.querySelectorAll(':scope > [data-widget-kind]')).forEach(function (item) {
                        raw.appendChild(item);
                        place(item, raw);
                    });
                    workspace.querySelectorAll('[data-parent-section="' + CSS.escape(id) + '"]').forEach(function (item) { setGroup(item, null); });
                    section.remove();
                }
                deleteSection.closest('[data-sidebar-section-row]').remove();
                return;
            }
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
            const zone = name === 'sidebar' && item.dataset.parentSection
                ? groupZone(item.dataset.parentSection)
                : workspace.querySelector('[data-library-zone="' + name + '"]');
            if (zone) { zone.appendChild(item); place(item, zone); }
        });

        workspace.parentElement.addEventListener('input', function (event) {
            const labelInput = event.target.closest('[data-section-label-input]');
            const iconInput = event.target.closest('[data-section-icon-input]');
            const input = labelInput || iconInput;
            if (!input) return;
            const section = workspace.querySelector('[data-section-preview="' + CSS.escape(input.dataset[labelInput ? 'sectionLabelInput' : 'sectionIconInput']) + '"]');
            if (!section) return;
            if (labelInput) section.querySelector('[data-section-title]').textContent = labelInput.value || 'Untitled section';
            if (iconInput) {
                const icon = section.querySelector('[data-section-icon]');
                icon.className = input.value;
                icon.hidden = !input.value.trim();
            }
        });

        const sectionManager = workspace.querySelector('[data-sidebar-section-settings]');
        if (sectionManager) sectionManager.addEventListener('click', function (event) {
            const button = event.target.closest('[data-sidebar-section-add]');
            if (!button) return;
            event.preventDefault();
            const id = 'sidebar-' + (window.crypto && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Math.random().toString(36).slice(2, 14));
            const row = document.createElement('div');
            row.className = 'lt-library-sidebar-manager__row';
            row.dataset.sidebarSectionRow = id;
            row.innerHTML = '<label>Heading<input type="text" maxlength="80" required data-section-label-input="' + id + '"></label><label>Icon classes<input type="text" maxlength="120" placeholder="fa-solid fa-folder" data-section-icon-input="' + id + '"></label><button type="button" class="btn btn-default" data-sidebar-section-delete="' + id + '">Remove section</button>';
            row.querySelector('[data-section-label-input]').name = 'sidebarSections[' + id + '][label]';
            row.querySelector('[data-section-label-input]').value = 'New section';
            row.querySelector('[data-section-icon-input]').name = 'sidebarSections[' + id + '][icon]';
            sectionManager.appendChild(row);

            const section = document.createElement('section');
            section.className = 'lt-library-preview-section';
            section.dataset.widgetKind = 'sectionHeader';
            section.dataset.widgetId = id;
            section.dataset.sectionPreview = id;
            section.dataset.sectionZone = 'sidebar';
            section.draggable = true;
            const title = document.createElement('h3');
            const caret = document.createElement('i');
            caret.className = 'fa fa-angle-down';
            caret.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.dataset.sectionIcon = '';
            icon.setAttribute('aria-hidden', 'true');
            icon.hidden = true;
            const label = document.createElement('span');
            label.dataset.sectionTitle = '';
            label.textContent = 'New section';
            title.append(caret, icon, label);
            const grip = document.createElement('button');
            grip.type = 'button';
            grip.className = 'lt-library-section-handle';
            grip.setAttribute('aria-hidden', 'true');
            grip.tabIndex = -1;
            grip.textContent = '⠿';
            const park = document.createElement('button');
            park.type = 'button';
            park.dataset.sectionPark = id;
            park.title = 'Park New section';
            park.textContent = '×';
            title.append(grip, park);
            const placement = document.createElement('input');
            placement.type = 'hidden';
            placement.dataset.placementInput = '';
            placement.name = 'fieldLayout[sidebar][]';
            placement.value = id;
            title.appendChild(placement);
            const children = document.createElement('ol');
            children.className = 'lt-library-zone';
            children.dataset.libraryZone = 'sidebar';
            children.dataset.sectionChildren = id;
            section.append(title, children);
            workspace.querySelector('.lt-library-sidebar-units').appendChild(section);
        });

        function setSectionZone(section, zoneName) {
            const parked = zoneName === 'parked';
            const sectionId = section.dataset.sectionPreview;
            section.dataset.sectionZone = zoneName;
            section.toggleAttribute('data-parked-section', parked);
            const button = section.querySelector('[data-section-park]');
            if (button) {
                button.textContent = parked ? '+' : '×';
                const label = section.querySelector('[data-section-title]')?.textContent || 'sidebar';
                button.title = (parked ? 'Restore ' : 'Park ') + label + ' section';
            }
            const headerInput = section.querySelector('h3 [data-placement-input]');
            if (headerInput) headerInput.name = 'fieldLayout[' + zoneName + '][]';
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
