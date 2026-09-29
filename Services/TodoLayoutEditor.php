<?php

namespace Leantime\Plugins\LeantimeLib\Services;

/** Renders the Library's shared drag-and-drop widget editor for project overrides. */
class TodoLayoutEditor
{
    public function __construct(
        private TodoTabRegistry $tabs,
        private TodoSectionRegistry $sections,
        private TodoFieldRegistry $fields,
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
        $attributes = ' data-library-workspace';
        if (! $global) {
            $attributes .= ' data-project-layout data-endpoint="'.htmlspecialchars(rtrim(BASE_URL, '/').'/LeantimeLib/projectIntegrations/'.$projectId.'/todo-layout', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" data-csrf="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" data-can-edit="'.($canEdit ? '1' : '0').'"';
        }
        $html = '<section class="lt-library-workspace"'.$attributes.'><header><h2>To-do modal layout</h2><p>Arrange widgets in a preview of Leantime’s To-do modal. Drag a field or whole section into Parked Widgets to hide it; drag individual fields within a section or move them between sections.</p></header>';
        if (! $global) $html .= '<p data-project-layout-status role="status">Projects use Library defaults until this layout is saved.</p>';
        $html .= '<div class="lt-library-ticket"><div class="lt-library-ticket__chrome"><span>☑ #25</span><strong>Shop Tokens - Beta</strong><small>Created by Example User | Last Updated: 09/24/2026</small><button type="button" aria-label="Close preview" disabled>×</button></div>';
        $html .= '<div class="lt-library-ticket__tabstrip"><ol class="lt-library-zone" data-library-zone="tabs">';
        foreach ($allTabs as $tab) if ($tab['enabled']) $html .= $this->widget('tabs', $tab['id'], $tab['label'], $tab['icon'], 'tabs', $tab['builtin'] ? 'Leantime tab' : 'Plugin tab', $global, $canEdit);
        $html .= '</ol></div><div class="lt-library-ticket__body"><div class="lt-library-ticket__main">';
        foreach ($layout['main'] as $id) $html .= $this->fieldWidget($id, 'main', $global, $canEdit);
        $html .= '</div><aside class="lt-library-ticket__sidebar">';
        $sidebarSections = ['organization' => [], 'schedule' => [], 'other' => []];
        foreach ($layout['sidebar'] as $id) {
            $widget = $this->fields->fields()[$id] ?? [];
            if (($widget['kind'] ?? '') === 'sectionHeader') continue;
            $group = match (true) {
                in_array($id, ['type', 'project', 'milestone', 'sprint', 'related'], true) => 'organization',
                in_array($id, ['workStart', 'workEnd', 'plannedHours'], true) => 'schedule',
                default => 'other',
            };
            $sidebarSections[$group][] = $id;
        }
        $parkedSectionWidgets = '';
        foreach (['organization' => 'Organization', 'schedule' => 'Schedule'] as $sectionId => $sectionLabel) {
            $headerInSidebar = in_array($sectionId, $layout['sidebar'], true);
            $childIds = $sidebarSections[$sectionId];
            $sectionMarkup = '<section class="lt-library-preview-section" data-widget-kind="sectionHeader" data-widget-id="'.$sectionId.'" data-section-preview="'.$sectionId.'" data-section-zone="'.($headerInSidebar ? 'sidebar' : 'parked').'"'.($headerInSidebar ? '' : ' data-parked-section="1"').'><h3><i class="fa fa-angle-down" aria-hidden="true"></i> '.$sectionLabel;
            if ($canEdit) $sectionMarkup .= '<button type="button" class="lt-library-section-handle" aria-hidden="true" tabindex="-1">⠿</button><button type="button" data-section-park="'.$sectionId.'" title="'.($headerInSidebar ? 'Park ' : 'Restore ').$sectionLabel.' section">'.($headerInSidebar ? '×' : '+').'</button><input type="hidden" name="fieldLayout['.($headerInSidebar ? 'sidebar' : 'parked').'][]" value="'.$sectionId.'">';
            $sectionMarkup .= '</h3><ol class="lt-library-zone" data-library-zone="'.($headerInSidebar ? 'sidebar' : 'parked').'" data-section-children="'.$sectionId.'">';
            foreach ($childIds as $id) $sectionMarkup .= $this->fieldWidget($id, $headerInSidebar ? 'sidebar' : 'parked', $global, $canEdit);
            $sectionMarkup .= '</ol></section>';
            if ($headerInSidebar) $html .= $sectionMarkup;
            else $parkedSectionWidgets .= $sectionMarkup;
        }
        if ($sidebarSections['other'] !== []) {
            $html .= '<ol class="lt-library-zone lt-library-ticket__plugin-sections" data-library-zone="sidebar">';
            foreach ($sidebarSections['other'] as $id) $html .= $this->fieldWidget($id, 'sidebar', $global, $canEdit);
            $html .= '</ol>';
        }
        $html .= '</aside></div><div class="lt-library-ticket__parked"><strong>Parked Widgets</strong><ol class="lt-library-zone" data-library-zone="parked">';
        foreach ($layout['hidden'] as $id) {
            if (in_array($id, ['organization', 'schedule'], true)) continue;
            $html .= $this->fieldWidget($id, 'parked', $global, $canEdit);
        }
        foreach ($allTabs as $tab) if (! $tab['enabled']) $html .= $this->widget('tabs', $tab['id'], $tab['label'], $tab['icon'], 'parked', $tab['builtin'] ? 'Leantime tab' : 'Plugin tab', $global, $canEdit);
        $html .= $parkedSectionWidgets;
        $html .= '</ol></div></div>';
        if ($global) $html .= '<p class="lt-library-workspace__hint">Widgets use their saved visibility and order in every To-do modal. Projects can override this layout in Project Settings → Integrations.</p>';
        if (! $global && $canEdit) $html .= '<div class="lt-library-layout-editor__actions"><button type="button" class="btn btn-primary" data-project-layout-save>Save project layout</button> <button type="button" class="btn btn-default" data-project-layout-reset>Use Library defaults</button></div>';
        $html .= '</section>';
        return $html;
    }

    public function renderProjectControls(int $projectId, bool $canEdit): string
    {
        return $this->renderWorkspace($projectId, $canEdit);
    }

    private function fieldWidget(string $id, string $zone, bool $global, bool $canEdit): string
    {
        $field = $this->fields->fields()[$id] ?? null;
        if (! $field) return '';
        $kind = $field['kind'] ?? 'fields';
        $type = $kind === 'sections' ? 'Plugin section' : ($kind === 'sectionHeader' ? 'Sidebar section' : 'To-do field');
        return $this->widget($kind, $id, $field['label'], $field['icon'] ?? '', $zone, $type, $global, $canEdit);
    }

    private function widget(string $kind, string $id, string $label, string $icon, string $zone, string $type, bool $global, bool $canEdit): string
    {
        $escapedId = $this->e($id);
        $button = ! $canEdit ? '' : ($zone === 'parked'
            ? '<button type="button" data-widget-add aria-label="Show '.$this->e($label).'" title="Show">+</button>'
            : '<button type="button" data-widget-park aria-label="Hide '.$this->e($label).'" title="Hide">×</button>');
        $defaultZone = $kind === 'fields' ? ($this->fields->fields()[$id]['zone'] ?? 'main') : ($kind === 'tabs' ? 'tabs' : 'sidebar');
        $html = '<li class="lt-library-widget" data-widget-kind="'.$kind.'" data-widget-id="'.$escapedId.'" data-default-zone="'.$defaultZone.'" data-widget-zone="'.$zone.'" draggable="'.($canEdit ? 'true' : 'false').'"'.($zone === 'parked' ? ' aria-label="Hidden widget"' : '').'><span class="lt-library-widget__grip" aria-hidden="true">⠿</span>';
        if ($icon !== '') $html .= '<i class="'.$this->e($icon).'" aria-hidden="true"></i>';
        $html .= '<span class="lt-library-widget__label">'.$this->e($label).'</span>';
        if ($kind === 'fields') {
            $examples = [
                'headline' => 'Shop Tokens - Beta', 'status' => 'Selected for work', 'priority' => 'Low',
                'effort' => 'Effort not defined', 'editor' => 'Example User', 'collaborators' => 'Filter by user',
                'dueDate' => '10/29/2026  11:59 PM', 'tags' => 'add a tag', 'description' => 'Design basic shop tokens. Optional is making the shop menu.',
                'subtasks' => '+ Add Task', 'discussion' => 'Add a new comment', 'type' => 'Task',
                'project' => 'PickWitch', 'milestone' => 'Art Designs', 'sprint' => 'Backlog',
                'related' => 'Not related to any other To-Dos', 'workStart' => 'm/d/Y  --:--',
                'workEnd' => 'm/d/Y  --:--', 'plannedHours' => '0  /  0',
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
            $html .= '<input type="hidden" name="fieldLayout['.$zone.'][]" value="'.$escapedId.'">';
        }
        return $html.'</li>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

}
