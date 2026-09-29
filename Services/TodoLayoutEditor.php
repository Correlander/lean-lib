<?php

namespace Leantime\Plugins\LeantimeLib\Services;

/** Renders the Library's shared drag-and-drop widget editor for project overrides. */
class TodoLayoutEditor
{
    public function __construct(private TodoTabRegistry $tabs, private TodoSectionRegistry $sections) {}

    public function renderProjectControls(int $projectId, bool $canEdit): string
    {
        $endpoint = htmlspecialchars(rtrim(BASE_URL, '/').'/LeantimeLib/projectIntegrations/'.$projectId.'/todo-layout', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<section class="lt-library-layout-editor" data-project-layout data-endpoint="'.$endpoint.'" data-csrf="'.$csrf.'" data-can-edit="'.($canEdit ? '1' : '0').'">';
        $html .= '<h3>To-do modal layout</h3><p>Projects use the Library defaults until you save a project layout override. Drag widgets between visible and hidden, then arrange their order.</p>';
        $html .= '<p data-project-layout-status role="status"></p>';
        $html .= $this->renderProjectGroup('tabs', 'Modal tabs', $this->tabs->getTabs(null, true, $projectId), $canEdit);
        $html .= $this->renderProjectGroup('sections', 'Details sidebar sections', $this->sections->getSections(null, ['projectId' => $projectId], true), $canEdit);
        if ($canEdit) {
            $html .= '<div class="lt-library-layout-editor__actions"><button type="button" class="btn btn-primary" data-project-layout-save>Save project layout</button> <button type="button" class="btn btn-default" data-project-layout-reset>Use Library defaults</button></div>';
        }
        return $html.'</section>';
    }

    private function renderProjectGroup(string $kind, string $label, array $items, bool $canEdit): string
    {
        $html = '<div class="lt-library-layout-editor__project-group" data-library-layout-editor data-layout-kind="'.$kind.'"><h4>'.$label.'</h4><div class="lt-library-layout-editor__columns">';
        foreach (['visible', 'hidden'] as $laneName) {
            $title = $laneName === 'visible' ? 'Shown in modal' : 'Hidden from modal';
            $html .= '<div><h5>'.$title.'</h5><ol class="lt-library-layout-editor__lane" data-library-lane="'.$laneName.'">';
            foreach ($items as $item) {
                if ((bool) $item['enabled'] !== ($laneName === 'visible')) continue;
                $id = htmlspecialchars($item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $itemLabel = htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $icon = htmlspecialchars($item['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $category = $item['builtin'] ? 'Leantime' : 'Plugin';
                $html .= '<li class="lt-library-layout-editor__item" data-widget-id="'.$id.'" draggable="'.($canEdit ? 'true' : 'false').'">';
                $html .= '<span class="lt-library-layout-editor__grip" aria-hidden="true">⠿</span>';
                if ($icon !== '') $html .= '<i class="'.$icon.'" aria-hidden="true"></i>';
                $html .= '<span>'.$itemLabel.'</span><small>'.$category.' '.($kind === 'tabs' ? 'tab' : 'section').'</small>';
                if ($canEdit) {
                    $html .= $laneName === 'visible'
                        ? '<button type="button" class="btn btn-default" data-widget-hide aria-label="Hide '.$itemLabel.'">Hide</button>'
                        : '<button type="button" class="btn btn-default" data-widget-show aria-label="Show '.$itemLabel.'">Show</button>';
                }
                $html .= '</li>';
            }
            $html .= '</ol></div>';
        }
        return $html.'</div></div>';
    }
}
