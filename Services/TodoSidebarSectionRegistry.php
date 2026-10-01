<?php

namespace Leantime\Plugins\LeanLib\Services;

use Leantime\Domain\Setting\Services\Setting as SettingService;

/** Instance-wide definitions for Library-owned To-do sidebar dropdowns. */
class TodoSidebarSectionRegistry
{
    private const SETTING = 'leantimelib.todo.detail.sidebarSections';
    private ?array $requestDefinitions = null;

    private const DEFAULTS = [
        'organization' => [
            'label' => 'Organization',
            'icon' => 'fa-solid fa-folder-open',
            'defaultChildren' => ['type', 'project', 'milestone', 'sprint', 'related'],
        ],
        'schedule' => [
            'label' => 'Schedule',
            'icon' => 'fa-solid fa-calendar-days',
            'defaultChildren' => ['workStart', 'workEnd', 'plannedHours'],
        ],
    ];

    public function __construct(private SettingService $settings) {}

    /** @return array<int, array{id:string,label:string,icon:string,defaultChildren?:array}> */
    public function definitions(): array
    {
        if ($this->requestDefinitions !== null) return $this->requestDefinitions;
        $saved = $this->settings->getSetting(self::SETTING, null);
        if (! is_string($saved) || $saved === '') {
            return $this->defaultDefinitions();
        }

        $decoded = json_decode($saved, true);
        if (! is_array($decoded)) {
            return $this->defaultDefinitions();
        }

        $definitions = [];
        foreach ($decoded as $definition) {
            if (! is_array($definition)) continue;
            $id = $definition['id'] ?? null;
            $label = $definition['label'] ?? null;
            $icon = $definition['icon'] ?? '';
            if (! $this->validId($id) || ! is_string($label) || trim($label) === '' || ! is_string($icon)) continue;
            $definitions[] = [
                'id' => $id,
                'label' => trim($label),
                'icon' => trim($icon),
                'defaultChildren' => self::DEFAULTS[$id]['defaultChildren'] ?? [],
            ];
        }
        return $definitions;
    }

    /** Store only editor-submitted fields; omitted IDs represent deliberate deletions. */
    public function saveDefinitions(array $submitted): bool
    {
        $definitions = [];
        $seen = [];
        foreach ($submitted as $id => $definition) {
            if (! is_string($id) || ! $this->validId($id) || ! is_array($definition) || isset($seen[$id])) continue;
            $label = $definition['label'] ?? null;
            $icon = $definition['icon'] ?? '';
            if (! is_string($label) || trim($label) === '' || mb_strlen(trim($label)) > 80
                || ! is_string($icon) || mb_strlen(trim($icon)) > 120
                || ($icon !== '' && ! preg_match('/^[a-zA-Z0-9 _-]+$/', trim($icon)))) {
                return false;
            }
            $seen[$id] = true;
            $definitions[] = ['id' => $id, 'label' => trim($label), 'icon' => trim($icon)];
        }
        if (count($definitions) > 40) return false;
        if (! $this->settings->saveSetting(self::SETTING, json_encode($definitions, JSON_THROW_ON_ERROR))) return false;
        $this->requestDefinitions = array_map(static function (array $definition): array {
            return $definition + ['defaultChildren' => self::DEFAULTS[$definition['id']]['defaultChildren'] ?? []];
        }, $definitions);
        return true;
    }

    public function resetDefinitions(): void
    {
        $this->requestDefinitions = $this->defaultDefinitions();
        $this->settings->deleteSetting(self::SETTING);
    }

    private function defaultDefinitions(): array
    {
        $definitions = [];
        foreach (self::DEFAULTS as $id => $definition) {
            $definitions[] = ['id' => $id] + $definition;
        }
        return $definitions;
    }

    private function validId(mixed $id): bool
    {
        if (! is_string($id) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,119}$/', $id)) return false;
        if (in_array($id, ['organization', 'schedule'], true)) return true;
        return str_starts_with($id, 'sidebar-')
            && ! in_array($id, TodoFieldRegistry::reservedWidgetIds(), true);
    }
}
