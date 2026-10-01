<?php

namespace Leantime\Plugins\LeanLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Throwable;

/** Generic provider content that can move between To-do tabs and compatible regions. */
class TodoWidgetRegistry
{
    public const FILTER = 'plugins.leantimelib.gui.todo.widgets';
    private const SETTING = 'leantimelib.todo.detail.contentWidgets';
    private const PROJECT_SUFFIX = '.leantimelib.todo.detail.contentWidgets';

    public function __construct(private SettingService $settings, private TodoTabRegistry $tabs) {}

    public function definitions(mixed $ticket = null, ?int $projectId = null): array
    {
        $tabs = array_column($this->tabs->getTabs($ticket, false, $projectId), 'id');
        $contributions = EventDispatcher::dispatch_filter(self::FILTER, [], ['ticket' => $ticket], 'leantime');
        if (!is_array($contributions)) return [];
        $reserved = array_fill_keys(array_merge(TodoFieldRegistry::reservedWidgetIds(), $tabs), true);
        $widgets = [];
        foreach ($contributions as $index => $widget) {
            if (!is_array($widget)) continue;
            $id = $widget['id'] ?? null;
            $label = $widget['label'] ?? null;
            $region = $widget['region'] ?? 'content';
            $render = $widget['render'] ?? null;
            $template = $widget['template'] ?? null;
            $data = $widget['data'] ?? null;
            if (!is_string($id) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,119}$/', $id) || isset($reserved[$id])
                || !is_string($label) || trim($label) === ''
                || !is_string($region) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/', $region)
                || (!is_callable($render) && (!in_array($template, ['content', 'notice', 'link'], true) || !is_callable($data)))) {
                Log::error('Leantime Library skipped an invalid To-do widget contribution.', ['index' => $index]);
                continue;
            }
            $tab = is_string($widget['tab'] ?? null) && in_array($widget['tab'], $tabs, true) ? $widget['tab'] : ($tabs[0] ?? 'ticketdetails');
            if (!in_array($region, $this->regionsForTab($tab), true)) {
                Log::error('Leantime Library skipped a To-do widget with an unsupported home region.', [
                    'widget_id' => $id,
                    'tab' => $tab,
                    'region' => $region,
                ]);
                continue;
            }
            $widgets[$id] = [
                'id' => $id,
                'label' => trim($label),
                'region' => $region,
                'tab' => $tab,
                'placement' => ($widget['placement'] ?? 'region') === 'any' ? 'any' : 'region',
                'render' => $render,
                'template' => $template,
                'data' => $data,
            ];
            $reserved[$id] = true;
        }
        return $widgets;
    }

    /** Native regions reflect the actual To-do tab: only Details has form/sidebar slots. */
    private function regionsForTab(string $tab): array
    {
        return $tab === 'ticketdetails' ? ['content', 'main', 'sidebar', 'auxiliary'] : ['content'];
    }

    public function layout(mixed $ticket = null, ?int $requestedProjectId = null): array
    {
        $projectId = $requestedProjectId ?? (int) (is_object($ticket) ? ($ticket->projectId ?? 0) : 0);
        $key = $projectId > 0 ? 'projectsettings.'.$projectId.self::PROJECT_SUFFIX : self::SETTING;
        $raw = $this->settings->getSetting($key, null);
        if (!is_string($raw) && $projectId > 0) $raw = $this->settings->getSetting(self::SETTING, '{}');
        $saved = is_string($raw) ? json_decode($raw, true) : null;
        $saved = is_array($saved) ? $saved : [];
        $tabs = array_column($this->tabs->getTabs($ticket, false, $projectId), 'id');
        $widgets = $this->definitions($ticket, $projectId);
        $parked = array_values(array_intersect(array_filter($saved['parked'] ?? [], 'is_string'), array_keys($widgets)));
        $parkedTargets = is_array($saved['parkedTargets'] ?? null) ? $saved['parkedTargets'] : [];
        $regions = [];
        foreach ($tabs as $tab) $regions[$tab] = array_fill_keys($this->regionsForTab($tab), []);
        $savedRegions = is_array($saved['regions'] ?? null) ? $saved['regions'] : [];
        $owners = [];
        foreach ($savedRegions as $tab => $tabRegions) {
            if (!is_array($tabRegions)) continue;
            foreach ($tabRegions as $ids) if (is_array($ids)) foreach ($ids as $id) if (is_string($id) && !isset($owners[$id])) $owners[$id] = $tab;
        }
        foreach ($tabs as $tab) {
            foreach ($regions[$tab] as $region => $_) {
                $ids = is_array($savedRegions[$tab][$region] ?? null) ? $savedRegions[$tab][$region] : [];
                $ids = array_values(array_intersect(array_filter($ids, 'is_string'), array_keys($widgets)));
                $ids = array_values(array_filter($ids, fn ($id) => ($owners[$id] ?? $tab) === $tab
                    && (($widgets[$id]['placement'] ?? 'region') === 'any' || $widgets[$id]['region'] === $region)
                    && !in_array($id, $parked, true)));
                $regions[$tab][$region] = $ids;
            }
        }
        foreach ($widgets as $id => $widget) {
            if (in_array($id, $parked, true)) continue;
            $tab = $owners[$id] ?? $widget['tab'];
            if (!in_array($tab, $tabs, true)) $tab = $widget['tab'];
            if ($widget['placement'] !== 'any' && !isset($regions[$tab][$widget['region']])) {
                $tab = in_array($widget['tab'], $tabs, true) ? $widget['tab'] : ($tabs[0] ?? 'ticketdetails');
            }
            $found = false;
            foreach ($regions[$tab] as $ids) if (in_array($id, $ids, true)) { $found = true; break; }
            if (!$found) {
                $region = isset($regions[$tab][$widget['region']]) ? $widget['region'] : 'content';
                $regions[$tab][$region][] = $id;
            }
        }
        foreach ($parked as $id) {
            $target = is_array($parkedTargets[$id] ?? null) ? $parkedTargets[$id] : [];
            $tab = is_string($target['tab'] ?? null) && in_array($target['tab'], $tabs, true) ? $target['tab'] : $widgets[$id]['tab'];
            if ($widgets[$id]['placement'] !== 'any' && !isset($regions[$tab][$widgets[$id]['region']])) {
                $tab = in_array($widgets[$id]['tab'], $tabs, true) ? $widgets[$id]['tab'] : ($tabs[0] ?? 'ticketdetails');
            }
            $region = is_string($target['region'] ?? null) && isset($regions[$tab][$target['region']])
                && ($widgets[$id]['placement'] === 'any' || $target['region'] === $widgets[$id]['region'])
                ? $target['region'] : (isset($regions[$tab][$widgets[$id]['region']]) ? $widgets[$id]['region'] : 'content');
            $parkedTargets[$id] = ['tab' => $tab, 'region' => $region];
        }
        return ['regions' => $regions, 'parked' => $parked, 'parkedTargets' => $parkedTargets, 'definitions' => $widgets];
    }

    public function saveLayout(array $regions, array $parked, ?int $projectId = null, array $requestedParkedTargets = []): bool
    {
        $widgets = $this->definitions(null, $projectId);
        $tabs = array_column($this->tabs->getTabs(null, false, $projectId), 'id');
        $parked = array_values(array_unique(array_intersect(array_filter($parked, 'is_string'), array_keys($widgets))));
        $parkedTargets = [];
        foreach ($parked as $id) {
            $target = is_array($requestedParkedTargets[$id] ?? null) ? $requestedParkedTargets[$id] : [];
            $tab = is_string($target['tab'] ?? null) && in_array($target['tab'], $tabs, true) ? $target['tab'] : $widgets[$id]['tab'];
            $supportedRegions = $this->regionsForTab($tab);
            if ($widgets[$id]['placement'] !== 'any' && !in_array($widgets[$id]['region'], $supportedRegions, true)) {
                $tab = in_array($widgets[$id]['tab'], $tabs, true) ? $widgets[$id]['tab'] : ($tabs[0] ?? 'ticketdetails');
                $supportedRegions = $this->regionsForTab($tab);
            }
            $region = is_string($target['region'] ?? null) && in_array($target['region'], $supportedRegions, true)
                && ($widgets[$id]['placement'] === 'any' || $target['region'] === $widgets[$id]['region'])
                ? $target['region'] : (in_array($widgets[$id]['region'], $supportedRegions, true) ? $widgets[$id]['region'] : 'content');
            $parkedTargets[$id] = ['tab' => $tab, 'region' => $region];
        }
        $normalized = [];
        $placed = [];
        foreach ($tabs as $tab) {
            foreach (($regions[$tab] ?? []) as $region => $ids) {
                if (!is_string($region) || !in_array($region, $this->regionsForTab($tab), true) || !is_array($ids)) continue;
                foreach (array_values(array_unique(array_intersect(array_filter($ids, 'is_string'), array_keys($widgets)))) as $id) {
                    if (isset($placed[$id]) || in_array($id, $parked, true)) continue;
                    $widget = $widgets[$id];
                    if ($widget['placement'] !== 'any' && $widget['region'] !== $region) continue;
                    $normalized[$tab][$region][] = $id;
                    $placed[$id] = true;
                }
            }
        }
        foreach ($widgets as $id => $widget) {
            if (isset($placed[$id]) || in_array($id, $parked, true)) continue;
            $tab = in_array($widget['tab'], $tabs, true) ? $widget['tab'] : ($tabs[0] ?? 'ticketdetails');
            $region = $widget['region'];
            $normalized[$tab][$region][] = $id;
        }
        if ($projectId && $projectId > 0) {
            $compact = static function (array $regions): array {
                foreach ($regions as $tab => $tabRegions) {
                    foreach ($tabRegions as $region => $ids) if ($ids === []) unset($regions[$tab][$region]);
                    if (($regions[$tab] ?? []) === []) unset($regions[$tab]);
                }
                return $regions;
            };
            $global = $this->layout();
            if ($compact($normalized) === $compact($global['regions']) && $parked === $global['parked'] && $parkedTargets === $global['parkedTargets']) {
                $this->reset($projectId);
                return true;
            }
        }
        $key = $projectId && $projectId > 0 ? 'projectsettings.'.$projectId.self::PROJECT_SUFFIX : self::SETTING;
        return $this->settings->saveSetting($key, json_encode(['regions' => $normalized, 'parked' => $parked, 'parkedTargets' => $parkedTargets], JSON_THROW_ON_ERROR));
    }

    public function reset(?int $projectId = null): void
    {
        $key = $projectId && $projectId > 0 ? 'projectsettings.'.$projectId.self::PROJECT_SUFFIX : self::SETTING;
        $this->settings->deleteSetting($key);
    }

    public function hasProjectLayout(int $projectId): bool
    {
        if ($projectId < 1) return false;
        $value = $this->settings->getSetting('projectsettings.'.$projectId.self::PROJECT_SUFFIX, null);
        return is_string($value) && $value !== '' && is_array(json_decode($value, true));
    }

    public function renderWidgets(array $params): void
    {
        $ticket = $params['ticket'] ?? null;
        $layout = $this->layout($ticket);
        $ids = [];
        foreach ($layout['regions'] as $regions) foreach ($regions as $regionIds) foreach ($regionIds as $id) $ids[] = $id;
        $html = '';
        foreach (array_values(array_unique($ids)) as $id) {
            $widget = $layout['definitions'][$id] ?? null;
            if (!is_array($widget)) continue;
            try {
                $renderContext = $params + ['widgetId' => $id];
                if (is_callable($widget['render'] ?? null)) {
                    $content = ($widget['render'])($ticket, $renderContext);
                } else {
                    $data = ($widget['data'])($ticket, $renderContext);
                    $content = $this->renderGenericTemplate((string) $widget['template'], is_array($data) ? $data : []);
                }
                if (is_string($content)) $html .= '<div data-leantimelib-todo-widget="'.htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.$content.'</div>';
            } catch (Throwable $exception) {
                Log::error('Leantime Library To-do widget renderer failed.', [
                    'widget_id' => $id,
                    'ticket_id' => is_object($ticket) ? (int) ($ticket->id ?? 0) : null,
                    'exception_class' => $exception::class,
                    'exception_file' => basename($exception->getFile()),
                    'exception_line' => $exception->getLine(),
                ]);
            }
        }
        echo '<div hidden data-leantimelib-todo-widget-stash>'.$html.'</div>';
    }

    private function renderGenericTemplate(string $template, array $data): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars(is_string($value) ? $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $escape($data['title'] ?? '');
        $body = $escape($data['body'] ?? '');
        if ($template === 'notice') return '<div class="alert alert-info"><strong>'.$title.'</strong><p>'.$body.'</p></div>';
        if ($template === 'link') {
            $url = is_string($data['url'] ?? null) ? trim($data['url']) : '';
            $safe = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $url
                : ((str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')) ? $url : '');
            return '<div class="leantimelib-generic-widget"><strong>'.$title.'</strong>'.($safe !== '' ? ' <a href="'.$escape($safe).'">'.$escape($data['label'] ?? 'Open').'</a>' : '').'<p>'.$body.'</p></div>';
        }
        return '<div class="leantimelib-generic-widget"><strong>'.$title.'</strong><p>'.$body.'</p></div>';
    }
}
