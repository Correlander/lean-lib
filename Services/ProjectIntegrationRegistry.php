<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Throwable;

/** Shared contribution registry for project-level integration settings panels. */
class ProjectIntegrationRegistry
{
    public const FILTER = 'leantime.plugins.leantimelib.project.integrations.panels';

    public function renderPanels(int $projectId): string
    {
        $panels = EventDispatcher::dispatch_filter(
            'plugins.leantimelib.project.integrations.panels',
            [],
            ['projectId' => $projectId],
            'leantime'
        );
        if (! is_array($panels)) {
            Log::error('Leantime Library received an invalid project integration panel list.');
            return '<p>No plugins have registered project integrations.</p>';
        }

        $html = '';
        $seen = [];
        foreach ($panels as $index => $panel) {
            if (! is_array($panel)) {
                Log::error('Leantime Library skipped a malformed project integration contribution.', ['index' => $index]);
                continue;
            }
            $id = $panel['id'] ?? null;
            $label = $panel['label'] ?? null;
            $render = $panel['render'] ?? null;
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $id)
                || isset($seen[$id]) || ! is_string($label) || trim($label) === '' || ! is_callable($render)) {
                Log::error('Leantime Library skipped an invalid project integration contribution.', [
                    'panel_id' => is_string($id) ? substr($id, 0, 80) : null,
                    'index' => $index,
                ]);
                continue;
            }
            $seen[$id] = true;
            try {
                $content = $render($projectId);
                if (is_string($content)) {
                    $title = htmlspecialchars(trim($label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html .= '<section class="leantimelib-integration-panel" data-integration="'.htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
                    $html .= '<h3>'.$title.'</h3>'.$content.'</section>';
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

        return $html !== '' ? $html : '<p>No enabled plugins have registered project integrations yet.</p>';
    }
}
