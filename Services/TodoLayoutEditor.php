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

        $html .= '<section class="lt-library-workspace"'.$attributes.'><header><h2>To-do modal layout</h2><p>Arrange the modal’s tabs, detail fields, sidebar sections, and below-save widgets. Drag unwanted items into the parked rail on the right.</p></header>';
        if (! $global) $html .= '<p data-project-layout-status role="status">Projects use Library defaults until this layout is saved.</p>';
        $html .= '<div class="lt-library-layout-stage"><div class="lt-library-ticket">';
        $html .= '<div class="lt-library-ticket__chrome"><span>☑ #25</span><strong>Shop Tokens - Beta</strong><small>Created by Example User | Last Updated: 09/24/2026</small><button type="button" aria-label="Close preview" disabled>×</button></div>';
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

        $nativeGroups = [
            'organization' => ['label' => 'Organization', 'children' => ['type', 'project', 'milestone', 'sprint', 'related']],
            'schedule' => ['label' => 'Schedule', 'children' => ['workStart', 'workEnd', 'plannedHours']],
        ];
        $renderedGroups = [];
        $nativeChildIds = array_merge(...array_column($nativeGroups, 'children'));
        foreach ($layout['sidebar'] as $id) {
            if (isset($nativeGroups[$id]) && ! isset($renderedGroups[$id])) {
                $children = array_values(array_intersect($layout['sidebar'], $nativeGroups[$id]['children']));
                $html .= $this->nativeSectionWidget($id, $nativeGroups[$id]['label'], $children, 'sidebar', $global, $canEdit);
                $renderedGroups[$id] = true;
                continue;
            }
            if (in_array($id, $nativeChildIds, true)) continue;
            $html .= $this->fieldWidget($id, 'sidebar', $global, $canEdit);
        }
        $html .= '</div></aside></div></div><aside class="lt-library-ticket__parked"><header><strong>Parked Widgets</strong><small>Hidden from the To-do modal</small></header><ol class="lt-library-zone" data-library-zone="parked">';
        foreach ($layout['hidden'] as $id) {
            if (in_array($id, ['organization', 'schedule'], true) || in_array($id, $nativeChildIds, true)) continue;
            $html .= $this->fieldWidget($id, 'parked', $global, $canEdit);
        }
        foreach ($allTabs as $tab) {
            if (! $tab['enabled']) $html .= $this->widget('tabs', $tab['id'], $tab['label'], $tab['icon'], 'parked', $tab['builtin'] ? 'Leantime tab' : 'Plugin tab', $global, $canEdit);
        }
        foreach ($nativeGroups as $id => $group) {
            if (in_array($id, $layout['sidebar'], true)) continue;
            $children = array_values(array_intersect($layout['hidden'], $group['children']));
            $html .= $this->nativeSectionWidget($id, $group['label'], $children, 'parked', $global, $canEdit);
        }
        $html .= '</ol></aside></div>';
        if ($global) $html .= '<p class="lt-library-workspace__hint">Widgets use their saved visibility and order in every To-do modal. Projects can override this layout in Project Settings → Integrations. Save controls stay fixed so a To-do can always be saved.</p>';
        if (! $global && $canEdit) $html .= '<div class="lt-library-layout-editor__actions"><button type="button" class="btn btn-primary" data-project-layout-save>Save project layout</button> <button type="button" class="btn btn-default" data-project-layout-reset>Use Library defaults</button></div>';
        $html .= '</section>';
        if (! $global) $html .= '</div></section>';
        return $html;
    }

    public function renderProjectControls(int $projectId, bool $canEdit): string
    {
        return $this->renderWorkspace($projectId, $canEdit);
    }

    private function nativeSectionWidget(string $id, string $label, array $childIds, string $zone, bool $global, bool $canEdit): string
    {
        $parked = $zone === 'parked';
        $html = '<section class="lt-library-preview-section" data-widget-kind="sectionHeader" data-widget-id="'.$id.'" data-section-preview="'.$id.'" data-section-zone="'.$zone.'" draggable="'.($canEdit ? 'true' : 'false').'"'.($parked ? ' data-parked-section="1"' : '').'><h3><i class="fa fa-angle-down" aria-hidden="true"></i> '.$label;
        if ($canEdit) $html .= '<button type="button" class="lt-library-section-handle" aria-hidden="true" tabindex="-1">⠿</button><button type="button" data-section-park="'.$id.'" title="'.($parked ? 'Restore ' : 'Park ').$label.' section">'.($parked ? '+' : '×').'</button>';
        if ($global) $html .= '<input type="hidden" name="fieldLayout['.$zone.'][]" value="'.$id.'">';
        $html .= '</h3><ol class="lt-library-zone" data-library-zone="'.$zone.'" data-section-children="'.$id.'">';
        foreach ($childIds as $childId) $html .= $this->fieldWidget($childId, $zone, $global, $canEdit);
        return $html.'</ol></section>';
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
