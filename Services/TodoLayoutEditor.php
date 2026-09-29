<?php

namespace Leantime\Plugins\LeantimeLib\Services;

/** Renders the Library's shared drag-and-drop widget editor for project overrides. */
class TodoLayoutEditor
{
    public function __construct(
        private TodoTabRegistry $tabs,
        private TodoSectionRegistry $sections,
        private TodoFieldRegistry $fields,
        private TodoSidebarSectionRegistry $sidebarSections,
    ) {}

    /** One ticket-like canvas for instance defaults; parked items stay in the canvas. */
    public function renderGlobalControls(array $tabs, array $sections): string
    {
        return $this->renderWorkspace(null, true);
    }

    private function renderWorkspace(?int $projectId, bool $canEdit): string
    {
        $global = $projectId === null;
        $layout = $this->fields->getLayout($projectId);
        $allTabs = $this->tabs->getTabs(null, true, $projectId);
        $sectionDefinitions = $this->sidebarSections->definitions();
        $sectionMap = array_column($sectionDefinitions, null, 'id');
        $hasProjectOverride = ! $global && ($this->fields->hasProjectOverride($projectId) || $this->tabs->hasProjectLayout($projectId));
        $attributes = ' data-library-workspace';
        if (! $global) $attributes .= ' data-can-edit="'.($canEdit ? '1' : '0').'"';

        $html = '';
        if (! $global) {
            $html .= '<section class="lt-library-project-layout" data-project-layout data-endpoint="'.htmlspecialchars(rtrim(BASE_URL, '/').'/LeantimeLib/projectIntegrations/'.$projectId.'/todo-layout', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" data-csrf="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
            $html .= '<label class="lt-library-sortable__toggle lt-library-project-layout__toggle"><input type="checkbox" data-project-layout-toggle'.($hasProjectOverride ? ' checked' : '').(!$canEdit ? ' disabled' : '').'> Override Library To-do layout for this project</label>';
            if (! $hasProjectOverride) $html .= '<p>By default, this project uses the Library layout. Enable the override to arrange its To-do modal.</p>';
            $html .= '<div data-project-layout-panel'.($hasProjectOverride ? '' : ' hidden').'>';
        }

        $html .= '<section class="lt-library-workspace"'.$attributes.'>';
        if ($global) $html .= '<input type="hidden" name="sidebarSectionsPresent" value="1">';
        if (! $global) {
            $html .= '<header class="lt-library-project-layout__heading"><h3>To-do modal layout</h3><p>Arrange tabs, detail fields, sidebar sections, and below-save widgets for this project.</p></header>';
            $html .= '<p data-project-layout-status role="status">Projects use Library defaults until this layout is saved.</p>';
        }
        $html .= '<div class="lt-library-layout-stage"><div class="lt-library-ticket">';
        $html .= '<div class="lt-library-ticket__chrome"><span aria-hidden="true">☐</span><strong>To-do title</strong><small>Created by a team member</small>';
        if ($global) {
            $html .= '<button type="button" class="lt-library-ticket__reset-button" data-layout-reset-preview data-tooltip="Reset the To-do layout to defaults" aria-label="Reset the To-do layout to defaults">×</button>';
        } else {
            $html .= '<button type="button" class="lt-library-ticket__reset-button" data-project-layout-reset'.(!$canEdit || !$hasProjectOverride ? ' disabled' : '').' data-tooltip="Use Library defaults for this project" aria-label="Use Library defaults for this project">×</button>';
        }
        $html .= '</div>';
        $html .= '<div class="lt-library-ticket__tabstrip"><ol class="lt-library-zone" data-library-zone="tabs">';
        foreach ($allTabs as $tab) {
            if ($tab['enabled']) $html .= $this->widget('tabs', $tab['id'], $tab['label'], $tab['icon'], 'tabs', $tab['builtin'] ? 'Leantime tab' : 'Plugin tab', $global, $canEdit);
        }
        $html .= '</ol></div><div class="lt-library-ticket__body"><div class="lt-library-ticket__main">';
        $html .= '<ol class="lt-library-zone lt-library-ticket__main-zone" data-library-zone="main">';
        foreach ($layout['main'] as $id) $html .= $this->fieldWidget($id, 'main', $global, $canEdit);
        $html .= '</ol><div class="lt-library-ticket__actions" aria-label="To-do save controls"><button type="button" class="btn btn-primary" disabled>Save</button> <button type="button" class="btn btn-default" disabled>Save &amp; Close</button></div>';
        $html .= '<section class="lt-library-ticket__auxiliary"><ol class="lt-library-zone" data-library-zone="auxiliary">';
        foreach ($layout['auxiliary'] as $id) $html .= $this->fieldWidget($id, 'auxiliary', $global, $canEdit);
        $html .= '</ol></section></div><aside class="lt-library-ticket__sidebar"><div class="lt-library-sidebar-units lt-library-zone" data-library-zone="sidebar">';

        $groupedIds = [];
        foreach ($layout['groups'] as $sectionId => $children) foreach ($children as $childId) $groupedIds[$childId] = $sectionId;
        foreach ($layout['sidebar'] as $id) {
            if (isset($sectionMap[$id])) {
                $children = array_values(array_intersect($layout['sidebar'], $layout['groups'][$id] ?? []));
                $html .= $this->sidebarSectionWidget($sectionMap[$id], $children, 'sidebar', $global, $canEdit);
                continue;
            }
            if (isset($groupedIds[$id])) continue;
            $html .= $this->fieldWidget($id, 'sidebar', $global, $canEdit);
        }
        $html .= '</div>';
        if ($global) $html .= '<button type="button" class="lt-library-add-section" data-sidebar-section-add><span aria-hidden="true">+</span> Add sidebar section</button>';
        $html .= '</aside></div></div><aside class="lt-library-ticket__parked"><header><strong>Parked Widgets</strong><small>Hidden from the To-do modal</small></header><ol class="lt-library-zone" data-library-zone="parked">';
        foreach ($layout['hidden'] as $id) {
            if (isset($sectionMap[$id])) {
                $children = array_values(array_intersect($layout['sidebar'], $layout['groups'][$id] ?? []));
                $html .= $this->sidebarSectionWidget($sectionMap[$id], $children, 'parked', $global, $canEdit);
                continue;
            }
            $html .= $this->fieldWidget($id, 'parked', $global, $canEdit, $groupedIds[$id] ?? null);
        }
        foreach ($allTabs as $tab) {
            if (! $tab['enabled']) $html .= $this->widget('tabs', $tab['id'], $tab['label'], $tab['icon'], 'parked', $tab['builtin'] ? 'Leantime tab' : 'Plugin tab', $global, $canEdit);
        }
        $html .= '</ol></aside></div>';
        if ($global) $html .= '<p class="lt-library-workspace__hint">Changes save automatically. Projects use this layout unless they have a project override. Save controls stay fixed so a To-do can always be saved.</p>';
        if (! $global && $canEdit) $html .= '<div class="lt-library-layout-editor__actions"><button type="button" class="btn btn-primary" data-project-layout-save>Save project layout</button></div>';
        $html .= '</section>';
        if (! $global) $html .= '</div></section>';
        return $html;
    }

    public function renderProjectControls(int $projectId, bool $canEdit): string
    {
        return $this->renderWorkspace($projectId, $canEdit);
    }

    private function sidebarSectionWidget(array $definition, array $childIds, string $zone, bool $global, bool $canEdit): string
    {
        $id = $definition['id'];
        $label = $definition['label'];
        $parked = $zone === 'parked';
        $icon = trim((string) ($definition['icon'] ?? ''));
        $html = '<section class="lt-library-preview-section" data-widget-kind="sectionHeader" data-widget-id="'.$this->e($id).'" data-section-preview="'.$this->e($id).'" data-section-zone="'.$zone.'" draggable="'.($canEdit ? 'true' : 'false').'"'.($parked ? ' data-parked-section="1"' : '').'><h3><i class="fa fa-angle-down" aria-hidden="true"></i> ';
        if ($global) {
            $html .= '<input class="lt-library-section-title" type="text" maxlength="80" required name="sidebarSections['.$this->e($id).'][label]" value="'.$this->e($label).'" data-section-label-input="'.$this->e($id).'" data-original-label="'.$this->e($label).'" aria-label="Section name">';
            $html .= $this->iconPicker($id, $icon);
        } else {
            $html .= '<i data-section-icon class="'.$this->e($icon).'" aria-hidden="true"'.($icon === '' ? ' hidden' : '').'></i><span data-section-title>'.$this->e($label).'</span>';
        }
        if ($canEdit) {
            $html .= '<button type="button" class="lt-library-section-handle" aria-hidden="true" tabindex="-1">⠿</button><button type="button" data-section-park="'.$this->e($id).'" title="'.($parked ? 'Restore ' : 'Park ').$this->e($label).' section">'.($parked ? '+' : '×').'</button>';
            if ($global && $parked) $html .= '<button type="button" class="lt-library-section-trash" data-section-delete="'.$this->e($id).'" title="Delete this section" aria-label="Delete '.$this->e($label).' section"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>';
        }
        if ($global) $html .= '<input type="hidden" data-placement-input name="fieldLayout['.$zone.'][]" value="'.$this->e($id).'">';
        $html .= '</h3><ol class="lt-library-zone" data-library-zone="sidebar" data-section-children="'.$this->e($id).'">';
        foreach ($childIds as $childId) $html .= $this->fieldWidget($childId, 'sidebar', $global, $canEdit, $id);
        return $html.'</ol></section>';
    }

    private function fieldWidget(string $id, string $zone, bool $global, bool $canEdit, ?string $parentSection = null): string
    {
        $field = $this->fields->fields()[$id] ?? null;
        if (! $field) return '';
        $kind = $field['kind'] ?? 'fields';
        $type = $kind === 'sections' ? 'Plugin section' : ($kind === 'sectionHeader' ? 'Sidebar section' : 'To-do field');
        return $this->widget($kind, $id, $field['label'], $field['icon'] ?? '', $zone, $type, $global, $canEdit, $parentSection);
    }

    private function widget(string $kind, string $id, string $label, string $icon, string $zone, string $type, bool $global, bool $canEdit, ?string $parentSection = null): string
    {
        $escapedId = $this->e($id);
        $button = ! $canEdit ? '' : ($zone === 'parked'
            ? '<button type="button" data-widget-add aria-label="Show '.$this->e($label).'" title="Show">+</button>'
            : '<button type="button" data-widget-park aria-label="Hide '.$this->e($label).'" title="Hide">×</button>');
        $defaultZone = $kind === 'fields' ? ($this->fields->fields()[$id]['zone'] ?? 'main') : ($kind === 'tabs' ? 'tabs' : 'sidebar');
        $html = '<li class="lt-library-widget" data-widget-kind="'.$kind.'" data-widget-id="'.$escapedId.'" data-default-zone="'.$defaultZone.'" data-widget-zone="'.$zone.'"'.($parentSection !== null ? ' data-parent-section="'.$this->e($parentSection).'"' : '').' draggable="'.($canEdit ? 'true' : 'false').'"'.($zone === 'parked' ? ' aria-label="Hidden widget"' : '').'><span class="lt-library-widget__grip" aria-hidden="true">⠿</span>';
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
        $html = '<div class="btn-group inlineDropDownContainerLeft lt-library-icon-picker">';
        $html .= '<button type="button" class="icp icp-dd btn btn-default dropdown-toggle iconpicker-container" data-toggle="dropdown" data-section-icon-button="'.$safeId.'" aria-label="Choose section icon" title="Choose icon">';
        $html .= '<span class="iconPlaceholder"><i class="'.$this->e($icon).'"'.($icon === '' ? ' hidden' : '').'></i></span><span class="caret"></span></button><div class="dropdown-menu"></div></div>';
        $html .= '<input type="hidden" id="'.$inputId.'" name="sidebarSections['.$safeId.'][icon]" value="'.$this->e($icon).'" data-section-icon-input="'.$safeId.'">';
        return $html;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

}
