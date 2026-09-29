<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Leantime\Domain\Setting\Services\Setting as SettingService;

/** Layout registry for Leantime's native To-do form controls. */
class TodoFieldRegistry
{
    private const SETTING = 'leantimelib.todo.detail.fieldLayout';
    private const PROJECT_SUFFIX = '.leantimelib.todo.detail.fieldLayout';
    private const SECTION_WIDGETS = [
        'organization' => ['label' => 'Organization', 'zone' => 'sidebar', 'kind' => 'sectionHeader', 'children' => ['type', 'project', 'milestone', 'sprint', 'related']],
        'schedule' => ['label' => 'Schedule', 'zone' => 'sidebar', 'kind' => 'sectionHeader', 'children' => ['workStart', 'workEnd', 'plannedHours']],
    ];

    private const FIELDS = [
        'headline' => ['label' => 'Title', 'zone' => 'main', 'selector' => '[name="headline"]'],
        'status' => ['label' => 'Status', 'zone' => 'main', 'selector' => '#status-select'],
        'priority' => ['label' => 'Priority', 'zone' => 'main', 'selector' => '#priority'],
        'effort' => ['label' => 'Effort', 'zone' => 'main', 'selector' => '#storypoints'],
        'editor' => ['label' => 'Assignee', 'zone' => 'main', 'selector' => '#editorId'],
        'collaborators' => ['label' => 'Collaborators', 'zone' => 'main', 'selector' => '#collaborators'],
        'dueDate' => ['label' => 'Due date', 'zone' => 'main', 'selector' => '[name="dateToFinish"]'],
        'tags' => ['label' => 'Tags', 'zone' => 'main', 'selector' => '#tags'],
        'description' => ['label' => 'Description', 'zone' => 'main', 'selector' => '#descriptionEditor'],
        'subtasks' => ['label' => 'Subtasks', 'zone' => 'auxiliary', 'selector' => '@subtasks'],
        'discussion' => ['label' => 'Discussion', 'zone' => 'auxiliary', 'selector' => '@discussion'],
        'type' => ['label' => 'To-do type', 'zone' => 'sidebar', 'selector' => '#type'],
        'project' => ['label' => 'Project', 'zone' => 'sidebar', 'selector' => '[name="projectId"]'],
        'milestone' => ['label' => 'Milestone', 'zone' => 'sidebar', 'selector' => '[name="milestoneid"]'],
        'sprint' => ['label' => 'Sprint', 'zone' => 'sidebar', 'selector' => '#sprint-select'],
        'related' => ['label' => 'Related To-do', 'zone' => 'sidebar', 'selector' => '[name="dependingTicketId"]'],
        'workStart' => ['label' => 'Schedule start', 'zone' => 'sidebar', 'selector' => '[name="editFrom"]'],
        'workEnd' => ['label' => 'Schedule end', 'zone' => 'sidebar', 'selector' => '[name="editTo"]'],
        'plannedHours' => ['label' => 'Planned and remaining hours', 'zone' => 'sidebar', 'selector' => '[name="planHours"]'],
    ];

    public function __construct(private SettingService $settings) {}

    public function fields(?int $projectId = null): array
    {
        $fields = self::FIELDS;
        foreach (app(TodoSectionRegistry::class)->getSections(null, ['projectId' => $projectId ?? 0], true) as $section) {
            $fields[$section['id']] = [
                'label' => $section['label'], 'icon' => $section['icon'], 'zone' => 'sidebar',
                'kind' => 'sections', 'enabled' => $section['enabled'], 'defaultEnabled' => $section['defaultEnabled'],
            ];
        }
        foreach (self::SECTION_WIDGETS as $id => $section) $fields[$id] = $section + ['icon' => '', 'enabled' => true, 'defaultEnabled' => true];
        return $fields;
    }

    public function getLayout(?int $projectId = null): array
    {
        $value = $projectId && $projectId > 0
            ? $this->settings->getSetting('projectsettings.'.$projectId.self::PROJECT_SUFFIX, null)
            : null;
        if (! is_string($value) || $value === '') $value = $this->settings->getSetting(self::SETTING, null);
        $saved = is_string($value) ? json_decode($value, true) : null;
        $saved = is_array($saved) ? $saved : [];
        $available = $this->fields($projectId);
        $zones = ['main', 'sidebar', 'auxiliary', 'hidden'];
        $layout = array_fill_keys($zones, []);
        foreach ($zones as $zone) {
            $ids = is_array($saved[$zone] ?? null) ? $saved[$zone] : [];
            foreach ($ids as $id) {
                if (! is_string($id) || ! isset($available[$id])) continue;
                // Older Library versions stored Subtasks and Discussion in main. Keep existing
                // layouts, but route those fixed below-footer components to their real region.
                $targetZone = $zone === 'main' && ($available[$id]['zone'] ?? null) === 'auxiliary'
                    ? 'auxiliary'
                    : $zone;
                $alreadyPlaced = false;
                foreach ($zones as $existingZone) {
                    if (in_array($id, $layout[$existingZone], true)) {
                        $alreadyPlaced = true;
                        break;
                    }
                }
                if (! $alreadyPlaced) $layout[$targetZone][] = $id;
            }
        }
        $defaultOrder = ['organization', 'type', 'project', 'milestone', 'sprint', 'related', 'schedule', 'workStart', 'workEnd', 'plannedHours'];
        foreach (array_keys($available) as $id) if (! in_array($id, $defaultOrder, true)) $defaultOrder[] = $id;
        foreach ($defaultOrder as $id) {
            $field = $available[$id];
            if (! in_array($id, $layout['main'], true) && ! in_array($id, $layout['sidebar'], true)
                && ! in_array($id, $layout['auxiliary'], true) && ! in_array($id, $layout['hidden'], true)) {
                $defaultZone = in_array($field['kind'] ?? '', ['sections', 'sectionHeader'], true) && ! ($field['enabled'] ?? true) ? 'hidden' : $field['zone'];
                $layout[$defaultZone][] = $id;
            }
        }
        foreach (['organization' => ['type', 'project', 'milestone', 'sprint', 'related'], 'schedule' => ['workStart', 'workEnd', 'plannedHours']] as $header => $children) {
            $savedSidebar = is_array($saved['sidebar'] ?? null) ? $saved['sidebar'] : [];
            if (in_array($header, $savedSidebar, true) || ! in_array($header, $layout['sidebar'], true)) continue;
            $layout['sidebar'] = array_values(array_diff($layout['sidebar'], [$header]));
            $positions = array_map(static fn (string $child): int|false => array_search($child, $layout['sidebar'], true), $children);
            $positions = array_values(array_filter($positions, static fn (int|false $position): bool => $position !== false));
            $insertAt = $positions === [] ? count($layout['sidebar']) : min($positions);
            array_splice($layout['sidebar'], $insertAt, 0, [$header]);
        }
        return $layout;
    }

    public function hasProjectOverride(int $projectId): bool
    {
        if ($projectId < 1) return false;
        $value = $this->settings->getSetting('projectsettings.'.$projectId.self::PROJECT_SUFFIX, null);
        return is_string($value) && $value !== '' && is_array(json_decode($value, true));
    }

    public function saveLayout(array $layout, ?int $projectId = null): bool
    {
        $available = $this->fields($projectId);
        $zones = ['main', 'sidebar', 'auxiliary', 'hidden'];
        $normalized = array_fill_keys($zones, []);
        foreach ($zones as $zone) {
            foreach ($layout[$zone] ?? [] as $id) {
                if (! is_string($id) || ! isset($available[$id])) continue;
                $targetZone = $zone === 'main' && ($available[$id]['zone'] ?? null) === 'auxiliary'
                    ? 'auxiliary'
                    : $zone;
                $alreadyPlaced = false;
                foreach ($zones as $existingZone) {
                    if (in_array($id, $normalized[$existingZone], true)) {
                        $alreadyPlaced = true;
                        break;
                    }
                }
                if (! $alreadyPlaced) $normalized[$targetZone][] = $id;
            }
        }
        foreach ($available as $id => $field) {
            $placed = false;
            foreach ($zones as $zone) {
                if (in_array($id, $normalized[$zone], true)) {
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $defaultZone = in_array($field['kind'] ?? '', ['sections', 'sectionHeader'], true) && ! ($field['enabled'] ?? true) ? 'hidden' : $field['zone'];
                $normalized[$defaultZone][] = $id;
            }
        }
        if (! $projectId) {
            $sectionIds = array_keys(array_filter($available, static fn (array $field): bool => ($field['kind'] ?? '') === 'sections'));
            $enabledSectionIds = array_values(array_intersect($sectionIds, array_merge($normalized['main'], $normalized['sidebar'])));
            if (! app(TodoSectionRegistry::class)->saveEnabled($enabledSectionIds)) return false;
        }
        if ($projectId && $projectId > 0) {
            $key = 'projectsettings.'.$projectId.self::PROJECT_SUFFIX;
            if ($normalized === $this->getLayout()) {
                $this->settings->deleteSetting($key);
                return true;
            }
            return $this->settings->saveSetting($key, json_encode($normalized, JSON_THROW_ON_ERROR));
        }
        return $this->settings->saveSetting(self::SETTING, json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public function resetProjectLayout(int $projectId): void
    {
        if ($projectId > 0) $this->settings->deleteSetting('projectsettings.'.$projectId.self::PROJECT_SUFFIX);
    }

    public function resetLayout(): void
    {
        $this->settings->deleteSetting(self::SETTING);
        app(TodoSectionRegistry::class)->resetLayout();
    }
}
