(function () {
    'use strict';

    function installWorkspace(workspace) {
        if (workspace.dataset.workspaceInstalled === '1') return;
        workspace.dataset.workspaceInstalled = '1';
        let dragged = null;
        const form = workspace.closest('form');
        const isGlobal = !!(form && form.hasAttribute('data-library-settings-form'));
        const autosaveStatus = isGlobal ? form.querySelector('[data-autosave-status]') : null;
        if (autosaveStatus && !autosaveStatus.dataset.saveState) {
            autosaveStatus.textContent = 'Changes automatically saved.';
        }
        let saveTimer = null;
        let saving = false;
        let saveAgain = false;
        let draggedChanged = false;

        const iconChoices = [
            'fas fa-folder-open', 'fas fa-calendar-days', 'fas fa-code-branch', 'fas fa-gear',
            'fas fa-list-check', 'fas fa-flag', 'fas fa-clock', 'fas fa-link', 'fas fa-book',
            'fas fa-circle-info', 'fas fa-tags', 'fas fa-users', 'fas fa-comments', 'fas fa-paperclip',
            'fas fa-chart-line', 'fas fa-rocket', 'fas fa-shield-halved', 'fas fa-clipboard',
            'fas fa-sitemap', 'fas fa-layer-group', 'fas fa-bug', 'fas fa-check', 'fas fa-box'
        ];

        function setStatus(message, state) {
            if (!autosaveStatus) return;
            autosaveStatus.textContent = message;
            autosaveStatus.dataset.saveState = state || '';
        }

        function hasBlankNewSection() {
            return Array.from(workspace.querySelectorAll('[data-section-new] [data-section-label-input]'))
                .some((input) => !input.value.trim());
        }

        function initializeIconPicker(button) {
            if (!button || button.dataset.iconPickerReady === '1') return;
            const id = button.dataset.sectionIconButton;
            const pickerElement = button.closest('[data-icon-picker]');
            const input = workspace.querySelector('[data-section-icon-input="' + CSS.escape(id) + '"]');
            const menu = pickerElement && pickerElement.querySelector('[data-icon-picker-menu]');
            const options = pickerElement && pickerElement.querySelector('[data-icon-options]');
            const search = pickerElement && pickerElement.querySelector('[data-icon-search]');
            if (!input || !menu || !options || !search) return;
            button.dataset.iconPickerReady = '1';
            iconChoices.forEach(function (iconClass) {
                const choice = document.createElement('button');
                choice.type = 'button';
                choice.className = 'lt-library-icon-picker__choice';
                choice.dataset.iconClass = iconClass;
                choice.title = iconClass;
                choice.setAttribute('role', 'option');
                choice.setAttribute('aria-label', iconClass.replace(/^\w+\s+fa-/, '').replace(/-/g, ' '));
                choice.setAttribute('aria-selected', String(iconClass === input.value));
                choice.innerHTML = '<i class="' + iconClass + '" aria-hidden="true"></i>';
                options.appendChild(choice);
            });

            function setOpen(open) {
                menu.hidden = !open;
                button.setAttribute('aria-expanded', String(open));
                pickerElement.classList.toggle('is-open', open);
                if (open) {
                    const buttonRect = button.getBoundingClientRect();
                    const menuRect = menu.getBoundingClientRect();
                    const left = Math.max(8, Math.min(buttonRect.right - menuRect.width, window.innerWidth - menuRect.width - 8));
                    let top = buttonRect.bottom + 6;
                    if (top + menuRect.height > window.innerHeight - 8) {
                        top = Math.max(8, buttonRect.top - menuRect.height - 6);
                    }
                    menu.style.left = left + 'px';
                    menu.style.top = top + 'px';
                    search.focus();
                } else {
                    menu.style.left = '';
                    menu.style.top = '';
                }
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                setOpen(menu.hidden);
            });
            menu.addEventListener('click', function (event) {
                const choice = event.target.closest('[data-icon-class]');
                if (!choice) return;
                input.value = choice.dataset.iconClass;
                const icon = button.querySelector('.iconPlaceholder > i');
                if (icon) {
                    icon.className = input.value;
                    icon.hidden = !input.value;
                }
                options.querySelectorAll('[data-icon-class]').forEach(function (option) {
                    option.setAttribute('aria-selected', String(option === choice));
                });
                input.dispatchEvent(new Event('change', { bubbles: true }));
                setOpen(false);
            });
            search.addEventListener('input', function () {
                const query = search.value.trim().toLowerCase();
                options.querySelectorAll('[data-icon-class]').forEach(function (choice) {
                    choice.hidden = query !== '' && !choice.dataset.iconClass.toLowerCase().includes(query);
                });
            });
        }

        workspace.querySelectorAll('[data-section-icon-button]').forEach(initializeIconPicker);

        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-icon-picker]')) return;
            workspace.querySelectorAll('[data-icon-picker-menu]:not([hidden])').forEach(function (menu) {
                menu.hidden = true;
                const picker = menu.closest('[data-icon-picker]');
                picker.classList.remove('is-open');
                picker.querySelector('[data-section-icon-button]').setAttribute('aria-expanded', 'false');
            });
        });
        window.addEventListener('scroll', function () {
            workspace.querySelectorAll('[data-icon-picker-menu]:not([hidden])').forEach(function (menu) {
                menu.hidden = true;
                menu.style.left = '';
                menu.style.top = '';
                const picker = menu.closest('[data-icon-picker]');
                picker.classList.remove('is-open');
                picker.querySelector('[data-section-icon-button]').setAttribute('aria-expanded', 'false');
            });
        }, true);
        workspace.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            const picker = event.target.closest('[data-icon-picker]');
            if (!picker) return;
            const menu = picker.querySelector('[data-icon-picker-menu]');
            const button = picker.querySelector('[data-section-icon-button]');
            menu.hidden = true;
            menu.style.left = '';
            menu.style.top = '';
            picker.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            button.focus();
        });

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

        function scheduleSave(immediate) {
            if (!isGlobal) return;
            if (hasBlankNewSection()) {
                setStatus('Name the new section or leave it blank to discard it.', 'pending');
                return;
            }
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(saveSettings, immediate ? 0 : 450);
        }

        function syncPlacementInputs() {
            workspace.querySelectorAll('[data-widget-kind]').forEach(function (item) {
                const zone = item.closest('[data-library-zone]');
                if (zone) place(item, zone);
            });
        }

        async function saveSettings() {
            if (!isGlobal) return;
            if (saving) { saveAgain = true; return; }
            if (hasBlankNewSection()) return;
            syncPlacementInputs();
            saving = true;
            setStatus('Saving…', 'saving');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                const result = await response.json().catch(function () { return {}; });
                if (!response.ok || result.saved !== true) {
                    const details = result.errors && typeof result.errors === 'object'
                        ? Object.values(result.errors).flatMap((messages) => Array.isArray(messages) ? messages : [messages]).join(' ')
                        : '';
                    throw new Error([result.error || result.message || 'The server did not confirm the save.', details].filter(Boolean).join(' '));
                }
                workspace.querySelectorAll('[data-section-new]').forEach(function (section) {
                    const label = section.querySelector('[data-section-label-input]');
                    if (label) label.dataset.originalLabel = label.value.trim();
                    delete section.dataset.sectionNew;
                });
                setStatus('All changes saved.', 'saved');
            } catch (error) {
                setStatus(error.message, 'error');
                console.error('[LeantimeLib settings autosave]', error);
            } finally {
                saving = false;
                if (saveAgain) {
                    saveAgain = false;
                    scheduleSave(true);
                }
            }
        }

        function resetLayout(button) {
            if (!isGlobal || !button) return;
            window.clearTimeout(saveTimer);
            if (saving) {
                saveAgain = false;
                window.setTimeout(function () { resetLayout(button); }, 100);
                return;
            }
            button.disabled = true;
            setStatus('Restoring To-do layout defaults…', 'saving');
            const body = new FormData();
            const csrf = form.querySelector('input[name="_token"]');
            if (csrf) body.append('_token', csrf.value);
            body.append('resetLayout', '1');
            fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body
            }).then(async function (response) {
                const result = await response.json().catch(function () { return {}; });
                if (!response.ok || result.reset !== true) throw new Error(result.error || result.message || 'The server did not confirm the reset.');
                window.location.reload();
            }).catch(function (error) {
                button.disabled = false;
                setStatus(error.message, 'error');
                console.error('[LeantimeLib settings reset]', error);
            });
        }

        function discardNewSection(section) {
            if (!section || !section.hasAttribute('data-section-new')) return;
            const label = section.querySelector('[data-section-label-input]');
            if (label && label.value.trim() !== '') return;
            section.remove();
            setStatus('Empty section discarded.', 'saved');
            scheduleSave();
        }

        function addSection() {
            const id = 'sidebar-' + (window.crypto && window.crypto.randomUUID
                ? window.crypto.randomUUID().replace(/-/g, '')
                : Math.random().toString(36).slice(2, 14));
            const section = document.createElement('section');
            section.className = 'lt-library-preview-section';
            section.dataset.widgetKind = 'sectionHeader';
            section.dataset.widgetId = id;
            section.dataset.sectionPreview = id;
            section.dataset.sectionZone = 'sidebar';
            section.dataset.sectionNew = '1';
            section.draggable = true;

            const heading = document.createElement('h3');
            const caret = document.createElement('i');
            caret.className = 'fa fa-angle-down';
            caret.setAttribute('aria-hidden', 'true');
            const title = document.createElement('input');
            title.type = 'text';
            title.maxLength = 80;
            title.placeholder = 'Section name';
            title.setAttribute('aria-label', 'Section name');
            title.dataset.sectionLabelInput = id;
            title.dataset.originalLabel = '';
            title.name = 'sidebarSections[' + id + '][label]';
            title.required = true;
            const sectionEditor = document.createElement('div');
            sectionEditor.className = 'lt-library-section-editor';

            const picker = document.createElement('div');
            picker.className = 'lt-library-icon-picker';
            picker.dataset.iconPicker = '';
            const iconButton = document.createElement('button');
            iconButton.type = 'button';
            iconButton.className = 'btn btn-default iconpicker-container';
            iconButton.dataset.sectionIconButton = id;
            iconButton.setAttribute('aria-label', 'Choose section icon');
            iconButton.setAttribute('aria-haspopup', 'listbox');
            iconButton.setAttribute('aria-expanded', 'false');
            iconButton.title = 'Choose icon';
            iconButton.innerHTML = '<span class="iconPlaceholder"><i hidden></i></span><span class="caret"></span>';
            const menu = document.createElement('div');
            menu.className = 'lt-library-icon-picker__menu';
            menu.dataset.iconPickerMenu = '';
            menu.hidden = true;
            menu.innerHTML = '<label class="lt-library-icon-picker__search"><span class="sr-only">Search icons</span><input type="search" class="form-control" data-icon-search placeholder="Search icons"></label><div class="lt-library-icon-picker__options" data-icon-options role="listbox" aria-label="Available icons"></div>';
            picker.append(iconButton, menu);
            const iconInput = document.createElement('input');
            iconInput.type = 'hidden';
            iconInput.id = 'sidebar-icon-' + id;
            iconInput.name = 'sidebarSections[' + id + '][icon]';
            iconInput.dataset.sectionIconInput = id;

            const grip = document.createElement('button');
            grip.type = 'button';
            grip.className = 'lt-library-section-handle';
            grip.setAttribute('aria-hidden', 'true');
            grip.tabIndex = -1;
            grip.textContent = '⠿';
            const park = document.createElement('button');
            park.type = 'button';
            park.dataset.sectionPark = id;
            park.title = 'Park section';
            park.setAttribute('aria-label', 'Park section');
            park.textContent = '×';
            const placement = document.createElement('input');
            placement.type = 'hidden';
            placement.dataset.placementInput = '';
            placement.name = 'fieldLayout[sidebar][]';
            placement.value = id;

            sectionEditor.append(picker, iconInput, title);
            heading.append(caret, sectionEditor, grip, park, placement);
            const children = document.createElement('ol');
            children.className = 'lt-library-zone';
            children.dataset.libraryZone = 'sidebar';
            children.dataset.sectionChildren = id;
            section.append(heading, children);
            workspace.querySelector('.lt-library-sidebar-units').appendChild(section);
            initializeIconPicker(iconButton);
            title.focus();
        }

        let pointerStartedInControl = false;
        workspace.addEventListener('pointerdown', function (event) {
            pointerStartedInControl = !!event.target.closest('button:not(.lt-library-section-handle), input, [data-icon-picker-menu]');
        }, true);
        workspace.addEventListener('pointerup', function () {
            pointerStartedInControl = false;
        }, true);
        workspace.addEventListener('dragstart', function (event) {
            if (pointerStartedInControl || event.target.closest('button:not(.lt-library-section-handle), input, [data-icon-picker-menu]')) {
                event.preventDefault();
                return;
            }
            const item = event.target.closest('[data-widget-kind]');
            if (!item || !workspace.contains(item)) return;
            dragged = item;
            draggedChanged = false;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.widgetId);
        });
        workspace.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
            if (draggedChanged) scheduleSave();
            draggedChanged = false;
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
            if (kind === 'tabs' && zone.dataset.libraryZone === 'parked'
                && workspace.querySelectorAll('[data-library-zone="tabs"] > [data-widget-kind="tabs"]').length <= 1) {
                setStatus('Keep at least one To-do tab visible.', 'error');
                return;
            }
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
                draggedChanged = true;
                return;
            }
            const destination = zone;
            const target = event.target.closest('[data-widget-kind]');
            if (target === dragged) return;
            if (!target || target.dataset.widgetKind !== kind || target.parentElement !== destination) {
                destination.appendChild(dragged);
                place(dragged, destination);
                draggedChanged = true;
                return;
            }
            const after = event.clientY > target.getBoundingClientRect().top + target.getBoundingClientRect().height / 2;
            destination.insertBefore(dragged, after ? target.nextSibling : target);
            place(dragged, destination);
            draggedChanged = true;
        });
        workspace.addEventListener('drop', function (event) { if (event.target.closest('[data-library-zone]')) event.preventDefault(); });
        workspace.addEventListener('click', function (event) {
            const resetButton = event.target.closest('[data-layout-reset-preview]');
            if (resetButton) { resetLayout(resetButton); return; }
            const addSectionButton = event.target.closest('[data-sidebar-section-add]');
            if (addSectionButton) { addSection(); return; }
            const deleteSection = event.target.closest('[data-section-delete]');
            if (deleteSection) {
                const id = deleteSection.dataset.sectionDelete;
                const section = deleteSection.closest('[data-section-preview]');
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
                scheduleSave();
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
                if (parked && isGlobal && !section.querySelector('[data-section-delete]')) {
                    const trash = document.createElement('button');
                    trash.type = 'button';
                    trash.className = 'lt-library-section-trash';
                    trash.dataset.sectionDelete = section.dataset.sectionPreview;
                    trash.title = 'Delete this section';
                    trash.setAttribute('aria-label', 'Delete ' + (section.querySelector('[data-section-label-input]')?.value || 'section'));
                    trash.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
                    section.querySelector('h3').appendChild(trash);
                } else if (!parked) {
                    const trash = section.querySelector('[data-section-delete]');
                    if (trash) trash.remove();
                }
                scheduleSave();
                return;
            }
            const button = event.target.closest('[data-widget-park], [data-widget-add]');
            if (!button) return;
            const item = button.closest('[data-widget-kind]');
            if (!item) return;
            const defaultZone = item.dataset.defaultZone || (item.dataset.widgetKind === 'tabs' ? 'tabs' : 'sidebar');
            const name = button.hasAttribute('data-widget-add') ? defaultZone : 'parked';
            if (item.dataset.widgetKind === 'tabs' && name === 'parked'
                && workspace.querySelectorAll('[data-library-zone="tabs"] > [data-widget-kind="tabs"]').length <= 1) {
                setStatus('Keep at least one To-do tab visible.', 'error');
                return;
            }
            const zone = name === 'sidebar' && item.dataset.parentSection
                ? groupZone(item.dataset.parentSection)
                : workspace.querySelector('[data-library-zone="' + name + '"]');
            if (zone) { zone.appendChild(item); place(item, zone); scheduleSave(); }
        });

        workspace.addEventListener('input', function (event) {
            const labelInput = event.target.closest('[data-section-label-input]');
            const iconInput = event.target.closest('[data-section-icon-input]');
            const input = labelInput || iconInput;
            if (!input) return;
            const section = workspace.querySelector('[data-section-preview="' + CSS.escape(input.dataset[labelInput ? 'sectionLabelInput' : 'sectionIconInput']) + '"]');
            if (!section) return;
            if (labelInput) {
                section.querySelector('[data-section-title]')?.replaceChildren(document.createTextNode(labelInput.value));
                const name = labelInput.value.trim() || 'section';
                const park = section.querySelector('[data-section-park]');
                const trash = section.querySelector('[data-section-delete]');
                if (park) {
                    park.title = (section.dataset.sectionZone === 'parked' ? 'Restore ' : 'Park ') + name + ' section';
                    park.setAttribute('aria-label', park.title);
                }
                if (trash) trash.setAttribute('aria-label', 'Delete ' + name + ' section');
            }
            if (iconInput) {
                const icon = section.querySelector('[data-section-icon]');
                if (icon) { icon.className = input.value; icon.hidden = !input.value.trim(); }
            }
            scheduleSave();
        });

        workspace.addEventListener('focusout', function (event) {
            const input = event.target.closest('[data-section-label-input]');
            if (!input) return;
            const section = input.closest('[data-section-preview]');
            if (section && section.hasAttribute('data-section-new') && !input.value.trim()) {
                discardNewSection(section);
                return;
            }
            if (!input.value.trim()) input.value = input.dataset.originalLabel || 'Section';
            if (section && section.hasAttribute('data-section-new')) delete section.dataset.sectionNew;
            scheduleSave();
        });

        if (form && isGlobal && form.dataset.autosaveInstalled !== '1') {
            form.dataset.autosaveInstalled = '1';
            form.addEventListener('change', function (event) {
                if (!event.target.closest('[data-layout-reset-preview]')) scheduleSave();
            });
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                scheduleSave(true);
            });
        }

        function setSectionZone(section, zoneName) {
            const parked = zoneName === 'parked';
            const sectionId = section.dataset.sectionPreview;
            section.dataset.sectionZone = zoneName;
            section.toggleAttribute('data-parked-section', parked);
            const button = section.querySelector('[data-section-park]');
            if (button) {
                button.textContent = parked ? '+' : '×';
                const label = section.querySelector('[data-section-label-input]')?.value || section.querySelector('[data-section-title]')?.textContent || 'sidebar';
                button.title = (parked ? 'Restore ' : 'Park ') + label + ' section';
            }
            const headerInput = section.querySelector('h3 [data-placement-input]');
            if (headerInput) headerInput.name = 'fieldLayout[' + zoneName + '][]';
            if (isGlobal && parked && !section.querySelector('[data-section-delete]')) {
                const trash = document.createElement('button');
                trash.type = 'button';
                trash.className = 'lt-library-section-trash';
                trash.dataset.sectionDelete = sectionId;
                trash.title = 'Delete this section';
                trash.setAttribute('aria-label', 'Delete section');
                trash.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';
                section.querySelector('h3').appendChild(trash);
            } else if (!parked) {
                const trash = section.querySelector('[data-section-delete]');
                if (trash) trash.remove();
            }
        }
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

    function installPluginMetadataSync() {
        const button = document.querySelector('[data-plugin-metadata-sync]');
        if (!button || button.dataset.installed === '1') return;
        button.dataset.installed = '1';
        const status = document.querySelector('[data-plugin-metadata-status]');
        button.addEventListener('click', async function () {
            if (button.disabled) return;
            const originalLabel = button.querySelector('span');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            if (originalLabel) originalLabel.textContent = 'Checking…';
            if (status) {
                status.hidden = true;
                status.removeAttribute('data-state');
                status.textContent = '';
            }

            try {
                const response = await fetch(button.dataset.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': button.dataset.csrf
                    }
                });
                const result = await response.json().catch(function () { return {}; });
                if (!response.ok) throw new Error(result.error || result.message || 'Plugin metadata refresh failed.');
                if (status) {
                    status.textContent = result.message || 'Plugin metadata is up to date.';
                    status.hidden = false;
                }
            } catch (error) {
                if (status) {
                    status.textContent = error.message || 'Plugin metadata refresh failed.';
                    status.dataset.state = 'error';
                    status.hidden = false;
                }
                console.error('[LeantimeLib plugin metadata refresh]', error);
            } finally {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                if (originalLabel) originalLabel.textContent = 'Check for updates';
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
        installPluginMetadataSync();
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
