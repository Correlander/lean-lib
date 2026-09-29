<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Throwable;

/** Central registry and layout controller for native and plugin To-do tabs. */
class TodoTabRegistry
{
    public const FILTER = 'leantime.plugins.leantimelib.todo.detail.tabs';

    private const ORDER_SETTING = 'leantimelib.todo.detail.tabOrder';
    private const DISABLED_SETTING = 'leantimelib.todo.detail.disabledTabs';
    private const PROJECT_LAYOUT_SUFFIX = '.leantimelib.todo.detail.tabLayout';

    public function __construct(private SettingService $settings) {}

    public function renderTabHeaders(array $params): void
    {
        if (! is_array($params)) return;
        $ticket = $params['ticket'] ?? null;

        foreach ($this->getTabs($ticket) as $tab) {
            if ($tab['builtin']) continue;
            $id = htmlspecialchars($tab['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars($tab['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $icon = htmlspecialchars($tab['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<li data-leantimelib-tab="'.$id.'"><a href="#'.$id.'"><span class="'.$icon.'"></span> '.$label.'</a></li>';
        }

        // The native showTicketModal dispatches ticketTabs while building its
        // header list, before ticketTabsContent panels and before JS tab init.
        $layout = [
            'tabs' => array_column($this->getTabs($ticket), 'id'),
            'fields' => app(TodoFieldRegistry::class)->getLayout(is_object($ticket) ? (int) ($ticket->projectId ?? 0) : null),
            'sidebarSections' => app(TodoSidebarSectionRegistry::class)->definitions(),
        ];
        $layoutJson = json_encode($layout, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        echo '<li class="leantimelib-todo-layout-order" data-layout="'.htmlspecialchars($layoutJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" hidden aria-hidden="true"></li>';
    }

    public function renderTabPanels(array $params): void
    {
        if (! is_array($params)) return;
        $ticket = $params['ticket'] ?? null;

        foreach ($this->getTabs($ticket) as $tab) {
            if ($tab['builtin']) continue;
            $id = htmlspecialchars($tab['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<div id="'.$id.'" data-leantimelib-panel="'.$id.'">'.$this->renderContribution($tab, $ticket, $params).'</div>';
        }

    }

    public function renderContribution(array $tab, mixed $ticket, array $params = []): string
    {
        try {
            $content = ($tab['render'])($ticket, $params);
            return is_string($content) ? $content : '';
        } catch (Throwable $exception) {
            Log::error('Leantime Library To-do tab renderer failed.', [
                'tab' => $tab['id'] ?? null,
                'ticket_id' => is_object($ticket) ? (int) ($ticket->id ?? 0) : null,
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            return '<p>Unable to load this plugin tab.</p>';
        }
    }

    /** @return array<int, array{id:string,label:string,icon:string,order:int,render:callable,builtin:bool}> */
    public function getTabs(mixed $ticket = null, bool $includeDisabled = false, ?int $projectId = null): array
    {
        $projectId ??= (int) (is_object($ticket) ? ($ticket->projectId ?? 0) : 0);
        $tabs = EventDispatcher::dispatch_filter('plugins.leantimelib.todo.detail.tabs', [], ['ticket' => $ticket], 'leantime');
        if (! is_array($tabs)) {
            Log::error('Leantime Library received an invalid To-do tab contribution list.');
            $tabs = [];
        }

        $normalized = [];
        $seen = ['ticketdetails' => true, 'files' => true, 'timesheet' => true];
        foreach ($tabs as $index => $tab) {
            if (! is_array($tab)) {
                Log::error('Leantime Library skipped a malformed To-do tab contribution.', ['index' => $index]);
                continue;
            }
            $id = $tab['id'] ?? null;
            $label = $tab['label'] ?? null;
            $icon = $tab['icon'] ?? '';
            $render = $tab['render'] ?? null;
            if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,119}$/', $id)
                || isset($seen[$id]) || ! is_string($label) || trim($label) === ''
                || ! is_string($icon) || ! preg_match('/^[a-zA-Z0-9 _-]*$/', $icon)
                || ! is_callable($render)) {
                Log::error('Leantime Library skipped an invalid To-do tab contribution.', [
                    'tab_id' => is_string($id) ? substr($id, 0, 120) : null,
                    'index' => $index,
                ]);
                continue;
            }
            $seen[$id] = true;
            $normalized[] = [
                'id' => $id, 'label' => trim($label), 'icon' => trim($icon),
                'order' => is_int($tab['order'] ?? null) ? $tab['order'] : 100,
                'render' => $render, 'builtin' => false,
            ];
        }

        $normalized = array_merge([
            ['id' => 'ticketdetails', 'label' => __('tabs.ticketDetails'), 'icon' => 'fa fa-star', 'order' => 0, 'render' => static fn () => '', 'builtin' => true],
            ['id' => 'files', 'label' => __('tabs.files'), 'icon' => 'fa fa-file', 'order' => 10, 'render' => static fn () => '', 'builtin' => true],
            ['id' => 'timesheet', 'label' => __('tabs.time_tracking'), 'icon' => 'fa fa-clock', 'order' => 20, 'render' => static fn () => '', 'builtin' => true],
        ], $normalized);

        $projectLayout = $projectId > 0 ? $this->readProjectLayout($projectId) : [];
        $savedRanks = array_flip(is_array($projectLayout['order'] ?? null) ? $projectLayout['order'] : $this->readSavedOrder());
        usort($normalized, static function (array $left, array $right) use ($savedRanks): int {
            $leftHasSavedRank = isset($savedRanks[$left['id']]);
            $rightHasSavedRank = isset($savedRanks[$right['id']]);
            if ($leftHasSavedRank && $rightHasSavedRank) return $savedRanks[$left['id']] <=> $savedRanks[$right['id']];
            if ($leftHasSavedRank !== $rightHasSavedRank) return $leftHasSavedRank ? -1 : 1;
            return ($left['order'] <=> $right['order']) ?: strcmp($left['id'], $right['id']);
        });

        $disabled = $this->readDisabledIds();
        $projectVisible = is_array($projectLayout['visible'] ?? null) ? $projectLayout['visible'] : null;
        foreach ($normalized as &$tab) {
            $tab['defaultEnabled'] = ! in_array($tab['id'], $disabled, true);
            $tab['overridden'] = $projectVisible !== null && in_array($tab['id'], $projectVisible, true) !== $tab['defaultEnabled'];
            $tab['enabled'] = $projectVisible === null ? $tab['defaultEnabled'] : in_array($tab['id'], $projectVisible, true);
        }
        unset($tab);
        if (! $includeDisabled) $normalized = array_values(array_filter($normalized, static fn (array $tab): bool => $tab['enabled']));
        return $normalized;
    }

    public function saveOrder(array $requestedOrder): bool
    {
        $availableIds = array_column($this->getTabs(null, true), 'id');
        return $this->settings->saveSetting(self::ORDER_SETTING, json_encode($this->normalizeOrder($requestedOrder, $availableIds), JSON_THROW_ON_ERROR));
    }

    public function saveEnabled(array $enabledIds): bool
    {
        $availableIds = array_column($this->getTabs(null, true), 'id');
        $enabledIds = array_values(array_intersect($availableIds, array_filter($enabledIds, 'is_string')));
        if ($enabledIds === []) return false;
        $disabledIds = array_values(array_diff($availableIds, $enabledIds));
        return $this->settings->saveSetting(self::DISABLED_SETTING, json_encode($disabledIds, JSON_THROW_ON_ERROR));
    }

    public function setProjectLayout(int $projectId, array $requestedOrder, array $visibleIds): bool
    {
        if ($projectId < 1) return false;
        $tabs = $this->getTabs(null, true);
        $available = array_column($tabs, 'id');
        $order = $this->normalizeOrder($requestedOrder, $available);
        $defaults = array_column($tabs, 'id');
        $visibleIds = array_values(array_intersect($available, array_filter($visibleIds, 'is_string')));
        if ($visibleIds === []) return false;
        $defaultVisible = array_column(array_filter($tabs, static fn (array $tab): bool => $tab['defaultEnabled']), 'id');
        $layout = [];
        if ($order !== $defaults) $layout['order'] = $order;
        $visibleSet = $visibleIds;
        $defaultVisibleSet = $defaultVisible;
        sort($visibleSet);
        sort($defaultVisibleSet);
        if ($visibleSet !== $defaultVisibleSet) $layout['visible'] = $visibleIds;
        $key = 'projectsettings.'.$projectId.self::PROJECT_LAYOUT_SUFFIX;
        if ($layout === []) {
            $this->settings->deleteSetting($key);
            return true;
        }
        return $this->settings->saveSetting($key, json_encode($layout, JSON_THROW_ON_ERROR));
    }

    public function resetProjectLayout(int $projectId): void
    {
        if ($projectId > 0) $this->settings->deleteSetting('projectsettings.'.$projectId.self::PROJECT_LAYOUT_SUFFIX);
    }

    public function hasProjectLayout(int $projectId): bool
    {
        if ($projectId < 1) return false;
        $value = $this->settings->getSetting('projectsettings.'.$projectId.self::PROJECT_LAYOUT_SUFFIX, null);
        return is_string($value) && $value !== '' && is_array(json_decode($value, true));
    }

    public function resetLayout(): void
    {
        $this->settings->deleteSetting(self::ORDER_SETTING);
        $this->settings->deleteSetting(self::DISABLED_SETTING);
    }

    private function readProjectLayout(int $projectId): array
    {
        $value = $this->settings->getSetting('projectsettings.'.$projectId.self::PROJECT_LAYOUT_SUFFIX, '{}');
        if (! is_string($value)) return [];
        $layout = json_decode($value, true);
        return is_array($layout) ? $layout : [];
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
