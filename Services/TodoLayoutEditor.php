<?php

namespace Leantime\Plugins\LeanLib\Services;

/** Renders the Library's shared drag-and-drop widget editor for project overrides. */
class TodoLayoutEditor
{
    public function __construct(
        private TodoTabRegistry $tabs,
        private TodoSectionRegistry $sections,
        private TodoFieldRegistry $fields,
        private TodoSidebarSectionRegistry $sidebarSections,
        private TodoWidgetRegistry $contentWidgets,
    ) {}

    /** One ticket-like canvas for instance defaults; parked items are shared across tabs. */
    public function renderGlobalControls(array $tabs, array $sections): string
    {
        return $this->renderWorkspace(null, true);
    }

    private function renderWorkspace(?int $projectId, bool $canEdit): string
    {
        $global = $projectId === null;
        $layout = $this->fields->getLayout($projectId);
        $allTabs = $this->tabs->getTabs(null, true, $projectId);
        $tabWidgets = $this->tabs->getTabWidgets($projectId);
        $contentWidgetLayout = $this->contentWidgets->layout(null, $projectId);
        $sectionDefinitions = $this->sidebarSections->definitions();
        $sectionMap = array_column($sectionDefinitions, null, 'id');
        $hasProjectOverride = ! $global && ($this->fields->hasProjectOverride($projectId) || $this->tabs->hasProjectLayout($projectId) || $this->contentWidgets->hasProjectLayout($projectId));
        $attributes = ' data-library-workspace';
        if (! $global) $attributes .= ' data-can-edit="'.($canEdit ? '1' : '0').'"';

        $html = '';
        if (! $global) {
            $html .= '<section class="lt-library-project-layout" data-project-layout data-endpoint="'.htmlspecialchars(rtrim(BASE_URL, '/').'/LeanLib/projectIntegrations/'.$projectId.'/todo-layout', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" data-csrf="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
            $html .= '<label class="lt-library-sortable__toggle lt-library-project-layout__toggle"><input type="checkbox" data-project-layout-toggle'.($hasProjectOverride ? ' checked' : '').(!$canEdit ? ' disabled' : '').'> Override instance To-do layout for this project</label>';
            if (! $hasProjectOverride) $html .= '<p>This project uses the instance layout. Enable the override to arrange its To-do modal.</p>';
            $html .= '<div data-project-layout-panel'.($hasProjectOverride ? '' : ' hidden').'>';
        }

        $html .= '<section class="lt-library-workspace"'.$attributes.'>';
        if ($global) $html .= '<input type="hidden" name="sidebarSectionsPresent" value="1">';
        if (! $global) {
            $html .= '<header class="lt-library-project-layout__heading"><h3>To-do modal layout</h3><p>Switch tabs to preview their content. Detail fields and sidebar sections stay on Details; parked widgets are available across tabs.</p></header>';
            $html .= '<p data-project-layout-status role="status">Projects use instance defaults until this layout is saved.</p>';
        }
        $html .= '<div class="lt-library-layout-stage"><div class="lt-library-ticket">';
        $resetLabel = $global ? 'reset-to-defaults' : 'reset-to-instance-defaults';
        $html .= '<div class="lt-library-ticket__chrome"><span aria-hidden="true">☐</span><strong>To-do title</strong><small>'.$resetLabel.'</small>';
        if ($global) {
            $html .= '<button type="button" class="lt-library-ticket__reset-button" data-layout-reset-preview data-tooltip="Reset the To-do layout to defaults" aria-label="Reset the To-do layout to defaults">×</button>';
        } else {
            $html .= '<button type="button" class="lt-library-ticket__reset-button" data-project-layout-reset'.(!$canEdit || !$hasProjectOverride ? ' disabled' : '').' data-tooltip="Reset this project to instance-level defaults" aria-label="Reset this project to instance-level defaults">×</button>';
        }
        $html .= '</div>';
        $previewTabs = [];
        foreach ($allTabs as $tab) if ($tab['enabled']) $previewTabs[] = $tab['id'];
        $activePreviewTab = in_array($tabWidgets['activeTab'] ?? null, $previewTabs, true) ? $tabWidgets['activeTab'] : ($previewTabs[0] ?? 'ticketdetails');
        $html .= '<div class="lt-library-ticket__tabstrip"><ol class="lt-library-zone" data-library-zone="tabs">';
        foreach ($allTabs as $tab) {
            if ($tab['enabled']) $html .= $this->widget('tabs', $tab['id'], $tab['label'], $tab['icon'], 'tabs', $tab['builtin'] ? 'Leantime tab' : 'Plugin tab', $global, $canEdit, null, $tab['id']);
        }
        $html .= '</ol></div><div class="lt-library-ticket__preview-tabs">';
        $groupedIds = [];
        foreach ($layout['groups'] as $sectionId => $children) foreach ($children as $childId) $groupedIds[$childId] = $sectionId;
        $tabPanels = [
            'ticketdetails' => $this->renderTodoDetailsPreview($layout, $global, $canEdit, $sectionMap, $groupedIds, $tabWidgets['ticketdetails'] ?? null),
            'files' => '<div class="lt-library-preview-native">Attachment upload, file list, and file actions. Leantime owns this tab’s component layout.</div>',
            'timesheet' => '<div class="lt-library-preview-native">Time entry form, logged time, and timer controls. Leantime owns this tab’s component layout.</div>',
        ];
        foreach ($allTabs as $tab) {
            if (!$tab['builtin']) $tabPanels[$tab['id']] = '<div class="lt-library-preview-native">Plugin provided tab content. The tab can be selected and ordered here; the provider owns its component layout.</div>';
        }
        foreach ($allTabs as $tab) {
            if (!$tab['enabled']) continue;
            $html .= '<section data-todo-preview-panel="'.$this->e($tab['id']).'"'.($tab['id'] === $activePreviewTab ? '' : ' hidden').'><header>'.$this->e($tab['label']).'</header>';
            $html .= $tabPanels[$tab['id']] ?? '<div class="lt-library-preview-native">Tab content</div>';
            $html .= $this->renderContentWidgetZones($contentWidgetLayout, $tab['id'], $global, $canEdit);
            $html .= '</section>';
        }
        $html .= '</div>';
        if ($global || $canEdit) {
            $html .= '<aside class="lt-library-ticket__parked"><header><strong>Parked widgets</strong><small>Available across all tabs</small></header><ol class="lt-library-zone" data-library-zone="parked">';
            foreach ($layout['hidden'] as $id) {
                if (isset($sectionMap[$id])) $html .= $this->sidebarSectionWidget($sectionMap[$id], [], 'parked', $global, $canEdit);
                else $html .= $this->fieldWidget($id, 'parked', $global, $canEdit, null, 'ticketdetails');
            }
            foreach ($contentWidgetLayout['parked'] as $id) {
                $target = $contentWidgetLayout['parkedTargets'][$id] ?? [];
                $html .= $this->contentWidget($contentWidgetLayout['definitions'][$id], 'parked', $canEdit, $target['tab'] ?? null, $target['region'] ?? null);
            }
            $html .= '</ol></aside>';
        }
        if ($global) $html .= '<div hidden data-todo-content-widget-inputs></div>';
        if ($global) $html .= '<input type="hidden" name="tabWidgetsActive" data-todo-active-tab value="'.$this->e($activePreviewTab).'">';
        $detailWidgets = $tabWidgets['ticketdetails'] ?? array_merge($layout['main'], $layout['sidebar'], $layout['auxiliary']);
        $detailWidgets = array_values(array_diff($detailWidgets, $layout['hidden']));
        foreach ($detailWidgets as $widgetId) $html .= '<input type="hidden" name="tabWidgets[ticketdetails][]" value="'.$this->e($widgetId).'" data-todo-tab-widget-input="ticketdetails">';
        if (! $global && $canEdit) $html .= '<div class="lt-library-layout-editor__actions"><button type="button" class="btn btn-primary" data-project-layout-save>Save project layout</button></div>';
        $html .= '</div></section>';
        if (! $global) $html .= '</div></section>';
        return $html;
    }

    public function renderProjectControls(int $projectId, bool $canEdit): string
    {
        return $this->renderWorkspace($projectId, $canEdit);
    }

    private function renderContentWidgetZones(array $layout, string $tabId, bool $global, bool $canEdit): string
    {
        $regions = $layout['regions'][$tabId] ?? [];
        $names = array_values(array_unique(array_merge(['content', 'main', 'sidebar', 'auxiliary'], array_keys($regions))));
        $html = '<section class="lt-library-generic-widgets"><h4>Generic widgets</h4><div class="lt-library-generic-widgets__grid">';
        foreach ($names as $region) {
            $html .= '<div class="lt-library-generic-widgets__slot"><strong>'.$this->e(ucfirst($region)).'</strong><ol data-todo-generic-region="'.$this->e($tabId).':'.$this->e($region).'">';
            foreach ($regions[$region] ?? [] as $id) {
                if (isset($layout['definitions'][$id])) $html .= $this->contentWidget($layout['definitions'][$id], $tabId, $canEdit);
            }
            $html .= '</ol></div>';
        }
        return $html.'</div></section>';
    }

    private function contentWidget(array $definition, string $tabId, bool $canEdit, ?string $returnTab = null, ?string $returnRegion = null): string
    {
        $id = $this->e((string) ($definition['id'] ?? ''));
        $label = $this->e((string) ($definition['label'] ?? 'Widget'));
        $zone = $tabId === 'parked' ? 'parked' : $tabId;
        $parked = $tabId === 'parked';
        return '<li class="lt-library-widget lt-library-content-widget" data-todo-content-widget="'.$id.'" data-default-tab="'.$this->e((string) ($definition['tab'] ?? 'ticketdetails')).'" data-widget-tab="'.$this->e($zone).'" data-default-region="'.$this->e((string) ($definition['region'] ?? 'content')).'" data-return-tab="'.$this->e($returnTab ?? (string) ($definition['tab'] ?? 'ticketdetails')).'" data-return-region="'.$this->e($returnRegion ?? (string) ($definition['region'] ?? 'content')).'" data-placement="'.$this->e((string) ($definition['placement'] ?? 'region')).'" draggable="'.($canEdit ? 'true' : 'false').'">'
            .'<span class="lt-library-widget__grip" aria-hidden="true">⠿</span><span class="lt-library-widget__label">'.$label.'</span><small>Generic plugin widget</small>'
            .($canEdit ? '<button type="button" data-todo-content-park>'.($parked ? '+' : '×').'</button>' : '').'</li>';
    }

    private function renderTodoDetailsPreview(array $layout, bool $global, bool $canEdit, array $sectionMap, array $groupedIds, ?array $orderedWidgets = null): string
    {
        $main = is_array($orderedWidgets) ? array_values(array_intersect($orderedWidgets, $layout['main'])) : $layout['main'];
        $sidebar = is_array($orderedWidgets) ? array_values(array_intersect($orderedWidgets, $layout['sidebar'])) : $layout['sidebar'];
        $auxiliary = is_array($orderedWidgets) ? array_values(array_intersect($orderedWidgets, $layout['auxiliary'])) : $layout['auxiliary'];
        if (is_array($orderedWidgets)) {
            $ranks = array_flip($orderedWidgets);
            foreach ([$main, $sidebar, $auxiliary] as &$orderedIds) usort($orderedIds, static fn ($left, $right) => ($ranks[$left] ?? PHP_INT_MAX) <=> ($ranks[$right] ?? PHP_INT_MAX));
            unset($orderedIds);
        }
        $html = '<div class="lt-library-ticket__body"><div class="lt-library-ticket__main"><ol class="lt-library-zone lt-library-ticket__main-zone" data-library-zone="main">';
        foreach ($main as $id) $html .= $this->fieldWidget($id, 'main', $global, $canEdit, null, 'ticketdetails');
        $html .= '</ol><div class="lt-library-ticket__actions" aria-label="To-do save controls"><button type="button" class="btn btn-primary" disabled>Save</button> <button type="button" class="btn btn-default" disabled>Save &amp; Close</button></div><section class="lt-library-ticket__auxiliary"><ol class="lt-library-zone" data-library-zone="auxiliary">';
        foreach ($auxiliary as $id) $html .= $this->fieldWidget($id, 'auxiliary', $global, $canEdit, null, 'ticketdetails');
        $html .= '</ol></section></div><aside class="lt-library-ticket__sidebar"><div class="lt-library-sidebar-units lt-library-zone" data-library-zone="sidebar">';
        foreach ($sidebar as $id) {
            if (isset($sectionMap[$id])) {
                $children = array_values(array_intersect($layout['sidebar'], $layout['groups'][$id] ?? []));
                $html .= $this->sidebarSectionWidget($sectionMap[$id], $children, 'sidebar', $global, $canEdit, 'ticketdetails');
                continue;
            }
            if (isset($groupedIds[$id])) continue;
            $html .= $this->fieldWidget($id, 'sidebar', $global, $canEdit, null, 'ticketdetails');
        }
        $html .= '</div>';
        if ($global) $html .= '<button type="button" class="lt-library-add-section" data-sidebar-section-add><span aria-hidden="true">+</span> Add sidebar section</button>';
        return $html.'</aside></div>';
    }

    private function sidebarSectionWidget(array $definition, array $childIds, string $zone, bool $global, bool $canEdit, string $tabId = 'ticketdetails'): string
    {
        $id = $definition['id'];
        $label = $definition['label'];
        $parked = $zone === 'parked';
        $icon = trim((string) ($definition['icon'] ?? ''));
        $html = '<section class="lt-library-preview-section" data-widget-kind="sectionHeader" data-widget-id="'.$this->e($id).'" data-section-preview="'.$this->e($id).'" data-section-zone="'.$zone.'" draggable="'.($canEdit ? 'true' : 'false').'"'.($parked ? ' data-parked-section="1"' : '').'><h3><i class="fa fa-angle-down" aria-hidden="true"></i><div class="'.($global ? 'lt-library-section-editor' : 'lt-library-section-display').'">';
        if ($global) {
            $html .= $this->iconPicker($id, $icon);
            $html .= '<input class="lt-library-section-title" type="text" maxlength="80" required name="sidebarSections['.$this->e($id).'][label]" value="'.$this->e($label).'" data-section-label-input="'.$this->e($id).'" data-original-label="'.$this->e($label).'" aria-label="Section name">';
        } else {
            $html .= '<i data-section-icon class="'.$this->e($icon).'" aria-hidden="true"'.($icon === '' ? ' hidden' : '').'></i><span data-section-title>'.$this->e($label).'</span>';
        }
        $html .= '</div>';
        if ($canEdit) {
            $html .= '<button type="button" class="lt-library-section-handle" aria-hidden="true" tabindex="-1">⠿</button><button type="button" data-section-park="'.$this->e($id).'" title="'.($parked ? 'Restore ' : 'Park ').$this->e($label).' section">'.($parked ? '+' : '×').'</button>';
            if ($global && $parked) $html .= '<button type="button" class="lt-library-section-trash" data-section-delete="'.$this->e($id).'" title="Delete this section" aria-label="Delete '.$this->e($label).' section"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>';
        }
        if ($global) $html .= '<input type="hidden" data-placement-input name="fieldLayout['.$zone.'][]" value="'.$this->e($id).'">';
        $html .= '</h3><ol class="lt-library-zone" data-library-zone="sidebar" data-section-children="'.$this->e($id).'">';
        foreach ($childIds as $childId) $html .= $this->fieldWidget($childId, 'sidebar', $global, $canEdit, $id, $tabId);
        return $html.'</ol></section>';
    }

    private function fieldWidget(string $id, string $zone, bool $global, bool $canEdit, ?string $parentSection = null, string $tabId = 'ticketdetails'): string
    {
        $field = $this->fields->fields()[$id] ?? null;
        if (! $field) return '';
        $kind = $field['kind'] ?? 'fields';
        $type = $kind === 'sections' ? 'Plugin section' : ($kind === 'sectionHeader' ? 'Sidebar section' : 'To-do field');
        return $this->widget($kind, $id, $field['label'], $field['icon'] ?? '', $zone, $type, $global, $canEdit, $parentSection, $tabId);
    }

    private function widget(string $kind, string $id, string $label, string $icon, string $zone, string $type, bool $global, bool $canEdit, ?string $parentSection = null, ?string $tabId = null): string
    {
        $escapedId = $this->e($id);
        $button = ! $canEdit ? '' : ($zone === 'parked'
            ? '<button type="button" data-widget-add aria-label="Show '.$this->e($label).'" title="Show">+</button>'
            : '<button type="button" data-widget-park aria-label="Hide '.$this->e($label).'" title="Hide">×</button>');
        $defaultZone = $kind === 'fields' ? ($this->fields->fields()[$id]['zone'] ?? 'main') : ($kind === 'tabs' ? 'tabs' : 'sidebar');
        $html = '<li class="lt-library-widget" data-widget-kind="'.$kind.'" data-widget-id="'.$escapedId.'" data-default-zone="'.$defaultZone.'" data-widget-zone="'.$zone.'"'.($tabId !== null ? ' data-default-tab="'.$this->e($tabId).'" data-widget-tab="'.$this->e($tabId).'"' : '').($parentSection !== null ? ' data-parent-section="'.$this->e($parentSection).'"' : '').' draggable="'.($canEdit ? 'true' : 'false').'"'.($zone === 'parked' ? ' aria-label="Hidden widget"' : '').'><span class="lt-library-widget__grip" aria-hidden="true">⠿</span>';
        if ($icon !== '') $html .= '<i class="'.$this->e($icon).'" aria-hidden="true"></i>';
        $html .= '<span class="lt-library-widget__label">'.$this->e($label).'</span>';
        if ($kind === 'fields') {
            $examples = [
                'headline' => 'To-do title', 'status' => 'Choose a status', 'priority' => 'Choose a priority',
                'effort' => 'Set effort', 'editor' => 'Assign a user', 'collaborators' => 'Add collaborators',
                'dueDate' => 'Choose a due date', 'tags' => 'Add tags', 'description' => 'Describe the work to be done…',
                'subtasks' => 'Add a subtask', 'discussion' => 'Write a comment', 'type' => 'Choose a type',
                'project' => 'Choose a project', 'milestone' => 'Choose a milestone', 'sprint' => 'Choose a sprint',
                'related' => 'Choose a related To-do', 'workStart' => 'Choose a work start date',
                'workEnd' => 'Choose a work end date', 'plannedHours' => 'Planned hours / hours left',
            ];
            $html .= '<span class="lt-library-widget__mock">'.$this->e($examples[$id] ?? '').'</span>';
        } else {
            $html .= '<small>'.$this->e($type).'</small>';
        }
        $html .= $button;
        if (! $global) return $html.'</li>';
        if ($kind === 'tabs') {
            $html .= '<input type="hidden" name="tabOrder[]" value="'.$escapedId.'"><input type="hidden" name="tabEnabled[]" value="'.$escapedId.'"'.($zone === 'parked' ? ' disabled' : '').'>';
        } else {
            $html .= '<input type="hidden" data-placement-input name="fieldLayout['.$zone.'][]" value="'.$escapedId.'">';
            if ($parentSection !== null) $html .= '<input type="hidden" data-group-input name="fieldLayout[groups]['.$this->e($parentSection).'][]" value="'.$escapedId.'">';
        }
        return $html.'</li>';
    }

    private function iconPicker(string $id, string $icon): string
    {
        $safeId = $this->e($id);
        $inputId = 'sidebar-icon-'.$safeId;
        $html = '<div class="lt-library-icon-picker" data-icon-picker>';
        $html .= '<button type="button" class="btn btn-default iconpicker-container" data-section-icon-button="'.$safeId.'" aria-label="Choose section icon" aria-haspopup="listbox" aria-expanded="false" title="Choose icon">';
        $html .= '<span class="iconPlaceholder"><i class="'.$this->e($icon).'"'.($icon === '' ? ' hidden' : '').'></i></span><span class="caret" aria-hidden="true"></span></button>';
        $html .= '<div class="lt-library-icon-picker__menu" data-icon-picker-menu hidden><label class="lt-library-icon-picker__search"><span class="sr-only">Search icons</span><input type="search" class="form-control" data-icon-search placeholder="Search icons"></label><div class="lt-library-icon-picker__options" data-icon-options role="listbox" aria-label="Available icons"></div></div></div>';
        $html .= '<input type="hidden" id="'.$inputId.'" name="sidebarSections['.$safeId.'][icon]" value="'.$this->e($icon).'" data-section-icon-input="'.$safeId.'">';
        return $html;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

}
