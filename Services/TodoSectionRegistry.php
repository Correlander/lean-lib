<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Throwable;

/** Central registry for native and plugin To-do sidebar sections. */
class TodoSectionRegistry
{
    public const FILTER = 'leantime.plugins.leantimelib.todo.detail.sections';

    private const ORDER_SETTING = 'leantimelib.todo.detail.sectionOrder';
    private const DISABLED_SETTING = 'leantimelib.todo.detail.disabledSections';
    private const PROJECT_OVERRIDES_PREFIX = 'projectsettings.';
    private const PROJECT_OVERRIDES_SUFFIX = '.leantimelib.todo.detail.sectionOverrides';

    public function __construct(private SettingService $settings) {}

    public function renderSections(array $params): void
    {
        if (! is_array($params)) return;
        $ticket = $params['ticket'] ?? null;

        foreach ($this->getSections($ticket, $params) as $section) {
            if ($section['builtin']) continue;
            try {
                $content = ($section['render'])($ticket, $params);
                if (! is_string($content) || trim($content) === '') continue;
                $id = htmlspecialchars($section['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $label = htmlspecialchars($section['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $icon = htmlspecialchars($section['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo '<section class="leantimelib-todo-section" data-leantimelib-section="'.$id.'">';
                echo '<h4>'.($icon !== '' ? '<i class="'.$icon.'" aria-hidden="true"></i> ' : '').$label.'</h4>'.$content;
                echo '</section>';
            } catch (Throwable $exception) {
                Log::error('Leantime Library To-do section renderer failed.', [
                    'section' => $section['id'],
                    'ticket_id' => is_object($ticket) ? (int) ($ticket->id ?? 0) : null,
                    'exception_class' => $exception::class,
                    'exception_code' => (int) $exception->getCode(),
                    'exception_file' => basename($exception->getFile()),
                    'exception_line' => $exception->getLine(),
                ]);
                echo '<section class="leantimelib-todo-section"><p>This plugin section could not be loaded.</p></section>';
            }
        }
    }

    /** @return array<int, array{id:string,label:string,icon:string,order:int,render:callable,builtin:bool}> */
    public function getSections(mixed $ticket = null, array $params = [], bool $includeDisabled = false): array
    {
        $sections = EventDispatcher::dispatch_filter('plugins.leantimelib.todo.detail.sections', [], ['ticket' => $ticket] + $params, 'leantime');
        if (! is_array($sections)) {
            Log::error('Leantime Library received an invalid To-do section contribution list.');
            $sections = [];
        }

        $normalized = [];
        $seen = ['organization' => true, 'schedule' => true];
        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                Log::error('Leantime Library skipped a malformed To-do section contribution.', ['index' => $index]);
                continue;
            }
            $id = $section['id'] ?? null;
            $label = $section['label'] ?? null;
            $icon = $section['icon'] ?? '';
            $render = $section['render'] ?? null;
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,119}$/', $id)
                || isset($seen[$id]) || ! is_string($label) || trim($label) === ''
                || ! is_string($icon) || ! preg_match('/^[a-zA-Z0-9 _-]*$/', $icon)
                || ! is_callable($render)) {
                Log::error('Leantime Library skipped an invalid To-do section contribution.', ['section_id' => is_string($id) ? substr($id, 0, 120) : null, 'index' => $index]);
                continue;
            }
            $seen[$id] = true;
            $normalized[] = [
                'id' => $id, 'label' => trim($label), 'icon' => trim($icon),
                'order' => is_int($section['order'] ?? null) ? $section['order'] : 100,
                'render' => $render, 'builtin' => false,
            ];
        }

        $normalized = array_merge([
            ['id' => 'organization', 'label' => __('subtitles.organization'), 'icon' => 'fa fa-folder-open', 'order' => 0, 'render' => static fn () => '', 'builtin' => true],
            ['id' => 'schedule', 'label' => __('subtitles.schedule'), 'icon' => 'fa fa-calendar', 'order' => 10, 'render' => static fn () => '', 'builtin' => true],
        ], $normalized);

        $savedRanks = array_flip($this->readSavedOrder());
        usort($normalized, static function (array $left, array $right) use ($savedRanks): int {
            $leftHasSavedRank = isset($savedRanks[$left['id']]);
            $rightHasSavedRank = isset($savedRanks[$right['id']]);
            if ($leftHasSavedRank && $rightHasSavedRank) return $savedRanks[$left['id']] <=> $savedRanks[$right['id']];
            if ($leftHasSavedRank !== $rightHasSavedRank) return $leftHasSavedRank ? -1 : 1;
            return ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id']);
        });

        $disabled = $this->readDisabledIds();
        $projectId = (int) (is_object($ticket) ? ($ticket->projectId ?? 0) : ($params['projectId'] ?? 0));
        $overrides = $projectId > 0 ? $this->readProjectOverrides($projectId) : [];
        foreach ($normalized as &$section) {
            $section['defaultEnabled'] = $section['builtin'] || ! in_array($section['id'], $disabled, true);
            $section['overridden'] = ! $section['builtin'] && array_key_exists($section['id'], $overrides);
            $section['enabled'] = $section['builtin']
                || ($section['overridden'] ? $overrides[$section['id']] : $section['defaultEnabled']);
        }
        unset($section);
        if (! $includeDisabled) $normalized = array_values(array_filter($normalized, static fn (array $section): bool => $section['enabled']));
        return $normalized;
    }

    /** Render per-project controls for plugin sections; global Library defaults are inherited until changed. */
    public function renderProjectVisibilityControls(int $projectId, bool $canEdit): string
    {
        $sections = array_values(array_filter(
            $this->getSections(null, ['projectId' => $projectId], true),
            static fn (array $section): bool => ! $section['builtin']
        ));
        $endpoint = htmlspecialchars(rtrim(BASE_URL, '/').'/LeantimeLib/projectIntegrations/'.$projectId.'/section-visibility', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<section class="leantimelib-project-todo-visibility" data-leantimelib-visibility data-endpoint="'.$endpoint.'" data-csrf="'.$csrf.'">';
        $html .= '<h3>To-do section visibility</h3>';
        $html .= '<p>These settings control plugin sections in this project’s To-do modals. By default, projects use the visibility set in Leantime Library settings.</p>';
        if ($sections === []) {
            $html .= '<p>No enabled plugins have contributed To-do sections.</p>';
        } else {
            foreach ($sections as $section) {
                $id = htmlspecialchars($section['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $label = htmlspecialchars($section['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $checked = $section['enabled'] ? ' checked' : '';
                $disabled = $canEdit ? '' : ' disabled';
                $status = $section['overridden'] ? 'Project override' : 'Using Library default';
                $status = htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $resetDisabled = (! $canEdit || ! $section['overridden']) ? ' disabled' : '';
                $html .= '<div class="leantimelib-project-todo-visibility__row" data-section-row="'.$id.'">';
                $html .= '<label><input type="checkbox" data-section-toggle="'.$id.'"'.$checked.$disabled.'> Show '.$label.' in this project’s To-do modals</label>';
                $html .= '<span data-section-status>'.$status.'</span>';
                if ($canEdit) $html .= '<button type="button" class="btn btn-default" data-section-reset="'.$id.'"'.$resetDisabled.'>Use default</button>';
                $html .= '</div>';
            }
        }
        $html .= '<p class="leantimelib-project-todo-visibility__status" data-visibility-status role="status"></p></section>';
        return $html;
    }

    /** @return array{enabled:bool,defaultEnabled:bool,overridden:bool}|null */
    public function setProjectSectionVisibility(int $projectId, string $sectionId, ?bool $enabled): ?array
    {
        $available = array_column($this->getSections(null, [], true), null, 'id');
        $section = $available[$sectionId] ?? null;
        if ($projectId < 1 || ! is_array($section) || $section['builtin']) return null;

        $overrides = $this->readProjectOverrides($projectId);
        if ($enabled === null || $enabled === $section['defaultEnabled']) {
            unset($overrides[$sectionId]);
        } else {
            $overrides[$sectionId] = $enabled;
        }
        $key = self::PROJECT_OVERRIDES_PREFIX.$projectId.self::PROJECT_OVERRIDES_SUFFIX;
        if (! $this->settings->saveSetting($key, json_encode($overrides, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('The project To-do section override could not be saved.');
        }

        $isOverridden = array_key_exists($sectionId, $overrides);
        $effectiveEnabled = $isOverridden ? $overrides[$sectionId] : $section['defaultEnabled'];
        return ['enabled' => $effectiveEnabled, 'defaultEnabled' => $section['defaultEnabled'], 'overridden' => $isOverridden];
    }

    private function readProjectOverrides(int $projectId): array
    {
        $value = $this->settings->getSetting(self::PROJECT_OVERRIDES_PREFIX.$projectId.self::PROJECT_OVERRIDES_SUFFIX, '{}');
        if (! is_string($value)) return [];
        $overrides = json_decode($value, true);
        if (! is_array($overrides)) return [];
        return array_filter($overrides, static fn ($enabled, $id): bool => is_string($id) && is_bool($enabled), ARRAY_FILTER_USE_BOTH);
    }

    public function saveOrder(array $requestedOrder): bool
    {
        $availableIds = array_column($this->getSections(null, [], true), 'id');
        return $this->settings->saveSetting(self::ORDER_SETTING, json_encode($this->normalizeOrder($requestedOrder, $availableIds), JSON_THROW_ON_ERROR));
    }

    public function saveEnabled(array $enabledIds): bool
    {
        $contributionIds = array_column(array_filter($this->getSections(null, [], true), static fn (array $section): bool => ! $section['builtin']), 'id');
        $enabledIds = array_values(array_intersect($contributionIds, array_filter($enabledIds, 'is_string')));
        return $this->settings->saveSetting(self::DISABLED_SETTING, json_encode(array_values(array_diff($contributionIds, $enabledIds)), JSON_THROW_ON_ERROR));
    }

    private function normalizeOrder(array $requested, array $available): array
    {
        $order = [];
        foreach ($requested as $id) if (is_string($id) && in_array($id, $available, true) && ! in_array($id, $order, true)) $order[] = $id;
        foreach ($available as $id) if (! in_array($id, $order, true)) $order[] = $id;
        return $order;
    }

    private function readSavedOrder(): array
    {
        $value = $this->settings->getSetting(self::ORDER_SETTING, '[]');
        if (! is_string($value)) return [];
        $order = json_decode($value, true);
        return is_array($order) ? array_values(array_filter($order, 'is_string')) : [];
    }

    private function readDisabledIds(): array
    {
        $value = $this->settings->getSetting(self::DISABLED_SETTING, '[]');
        if (! is_string($value)) return [];
        $ids = json_decode($value, true);
        return is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }
}
