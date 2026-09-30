<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Throwable;

/** Shared contribution registry for project-level integration settings panels. */
class ProjectIntegrationRegistry
{
    public const FILTER = 'leantime.plugins.leantimelib.project.integrations.panels';

    public function __construct(private SettingService $settings) {}

    public function renderPanels(int $projectId, bool $canEdit): string
    {
        $guiRegistry = app(GuiSurfaceRegistry::class);
        $guiSurfaces = $guiRegistry->getSurfaces();
        foreach ($guiSurfaces as &$surface) {
            $surface['editorHtml'] = $guiRegistry->renderEditor($surface, [
                'projectId' => $projectId,
                'canEdit' => $canEdit,
            ]);
        }
        unset($surface);
        $html = view()->file(__DIR__.'/../Templates/gui-settings-editor.blade.php', [
            'guiSurfaces' => $guiSurfaces,
            'hasContributions' => true,
            'showContributionMessage' => false,
            'editorTitle' => 'GUI Customization',
            'editorDescription' => 'Override the instance-level GUI customization for this project.',
        ])->render();
        $panels = $this->getPanels($projectId);
        if ($panels === []) return $html.'<p>No plugins have registered project integrations.</p>';

        foreach ($panels as $panel) {
            $id = $panel['id'];
            $label = $panel['label'];
            $render = $panel['render'];
            try {
                if (isset($panel['view'])) {
                    $viewData = is_callable($panel['data'] ?? null)
                        ? ($panel['data'])($projectId)
                        : ($panel['data'] ?? []);
                    if (! is_array($viewData)) throw new \UnexpectedValueException('Project integration view data must be an array.');
                    $content = view($panel['view'], $viewData)->render();
                } else {
                    $content = $render($projectId);
                }
                if (is_string($content)) {
                    $title = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html .= '<section class="leantimelib-integration-panel" data-integration="'.htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
                    $description = $panel['description'] !== ''
                        ? '<p class="leantimelib-integration-panel__description">'.htmlspecialchars($panel['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>'
                        : '';
                    $html .= '<header class="leantimelib-integration-panel__header"><h3>'.$title.'</h3>'.$description.'</header>';
                    $html .= '<div class="leantimelib-integration-panel__content">'.$content.'</div></section>';
                }
            } catch (Throwable $exception) {
                Log::error('Leantime Library integration panel failed.', [
                    'panel' => $id,
                    'project_id' => $projectId,
                    'exception_class' => $exception::class,
                    'exception_code' => (int) $exception->getCode(),
                    'exception_file' => basename($exception->getFile()),
                    'exception_line' => $exception->getLine(),
                ]);
                $html .= '<section class="leantimelib-integration-panel"><p>This integration panel could not be loaded.</p></section>';
            }
        }

        return $html;
    }

    /** Return registered panels in the project override or instance order. */
    public function getPanels(int $projectId = 0): array
    {
        $panels = EventDispatcher::dispatch_filter(
            'plugins.leantimelib.project.integrations.panels',
            [],
            ['projectId' => $projectId],
            'leantime'
        );
        if (! is_array($panels)) {
            Log::error('Leantime Library received an invalid project integration panel list.');
            return [];
        }

        $seen = [];
        $normalized = [];
        foreach ($panels as $index => $panel) {
            if (! is_array($panel)) {
                Log::error('Leantime Library skipped a malformed project integration contribution.', ['index' => $index]);
                continue;
            }
            $id = $panel['id'] ?? null;
            $label = $panel['label'] ?? null;
            $description = $panel['description'] ?? '';
            $render = $panel['render'] ?? null;
            $viewName = $panel['view'] ?? null;
            $data = $panel['data'] ?? [];
            $hasView = is_string($viewName) && trim($viewName) !== ''
                && (is_array($data) || is_callable($data));
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $id)
                || isset($seen[$id]) || ! is_string($label) || trim($label) === ''
                || ! is_string($description) || (! is_callable($render) && ! $hasView)) {
                Log::error('Leantime Library skipped an invalid project integration contribution.', [
                    'panel_id' => is_string($id) ? substr($id, 0, 80) : null,
                    'index' => $index,
                ]);
                continue;
            }
            $seen[$id] = true;
            $normalized[] = ['id' => $id, 'label' => trim($label), 'description' => trim($description), 'render' => $render,
                'view' => $hasView ? trim($viewName) : null, 'data' => $data,
                'order' => is_int($panel['order'] ?? null) ? $panel['order'] : 100];
        }

        $projectOrder = $projectId > 0 ? $this->projectOrder($projectId) : null;
        $savedOrder = $projectOrder ?? json_decode((string) $this->settings->getSetting('leantimelib.projectIntegrations.order', '[]'), true);
        $ranks = array_flip(is_array($savedOrder) ? $savedOrder : []);
        usort($normalized, static function (array $left, array $right) use ($ranks): int {
            $leftSaved = isset($ranks[$left['id']]);
            $rightSaved = isset($ranks[$right['id']]);
            if ($leftSaved && $rightSaved) return $ranks[$left['id']] <=> $ranks[$right['id']];
            if ($leftSaved !== $rightSaved) return $leftSaved ? -1 : 1;
            return ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id']);
        });

        return $normalized;
    }

    public function saveGlobalOrder(array $requestedOrder): bool
    {
        $available = array_column($this->getPanels(), 'id');
        $order = array_values(array_intersect($requestedOrder, $available));
        foreach ($available as $id) if (! in_array($id, $order, true)) $order[] = $id;
        return $this->settings->saveSetting('leantimelib.projectIntegrations.order', json_encode($order, JSON_THROW_ON_ERROR));
    }

    public function renderGlobalOrderEditor(): string
    {
        $panels = $this->getPanels();
        if ($panels === []) return '<div class="alert alert-info" role="status">No enabled plugins have contributed project integration panels yet.</div>';
        $html = '<ol class="lt-library-integration-order" data-integration-order-editor aria-label="Project integrations order">';
        foreach ($panels as $panel) {
            $id = htmlspecialchars($panel['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars($panel['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<li draggable="true" data-integration-order-item="'.$id.'"><span class="lt-library-widget__grip" aria-hidden="true">⠿</span><strong>'.$label.'</strong><input type="hidden" name="projectIntegrationOrder[]" value="'.$id.'"></li>';
        }
        return $html.'</ol><p class="lt-library-workspace__hint">Drag panels to set their order in Project Settings → Integrations.</p>';
    }

    public function renderProjectOrderControls(int $projectId, bool $canEdit): string
    {
        $panels = $this->getPanels($projectId);
        if ($panels === []) return '<div class="alert alert-info" role="status">No enabled plugins have contributed project integration panels yet.</div>';
        $overridden = $this->projectOrder($projectId) !== null;
        $endpoint = htmlspecialchars(rtrim(BASE_URL, '/').'/LeantimeLib/projectIntegrations/'.$projectId.'/panel-order', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<section class="leantimelib-project-order" data-project-integration-order data-endpoint="'.$endpoint.'" data-csrf="'.$csrf.'">';
        $html .= '<label><input type="checkbox" data-project-integration-order-toggle'.($overridden ? ' checked' : '').(!$canEdit ? ' disabled' : '').'> Override instance integration order for this project</label>';
        if (! $overridden) $html .= '<p>This project uses the instance order. Enable the override to arrange its panels.</p>';
        $html .= '<div data-project-integration-order-editor'.($overridden ? '' : ' hidden').'><ol class="lt-library-integration-order" data-project-integration-sorter>';
        foreach ($panels as $panel) {
            $id = htmlspecialchars($panel['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars($panel['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<li draggable="'.($canEdit ? 'true' : 'false').'" data-project-integration-item="'.$id.'"><span class="lt-library-widget__grip" aria-hidden="true">⠿</span><strong>'.$label.'</strong></li>';
        }
        $html .= '</ol><p data-project-integration-order-status role="status">Using instance defaults.</p>';
        if ($canEdit) $html .= '<button type="button" class="btn btn-primary" data-project-integration-order-save>Save project order</button> <button type="button" class="btn btn-default" data-project-integration-order-reset>Use instance defaults</button>';
        return $html.'</div></section>';
    }

    public function saveProjectOrder(int $projectId, array $requestedOrder, bool $useDefault = false): bool
    {
        if ($projectId < 1) return false;
        $key = 'projectsettings.'.$projectId.'.leantimelib.projectIntegrations.order';
        if ($useDefault) {
            $this->settings->deleteSetting($key);
            return true;
        }
        $available = array_column($this->getPanels($projectId), 'id');
        $order = array_values(array_intersect($requestedOrder, $available));
        foreach ($available as $id) if (! in_array($id, $order, true)) $order[] = $id;
        return $this->settings->saveSetting($key, json_encode($order, JSON_THROW_ON_ERROR));
    }

    private function projectOrder(int $projectId): ?array
    {
        $saved = $this->settings->getSetting('projectsettings.'.$projectId.'.leantimelib.projectIntegrations.order', null);
        if (! is_string($saved) || $saved === '') return null;
        $decoded = json_decode($saved, true);
        return is_array($decoded) ? $decoded : null;
    }
}
