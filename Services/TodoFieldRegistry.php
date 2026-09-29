<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Leantime\Domain\Setting\Services\Setting as SettingService;

/** Layout registry for Leantime's native To-do form controls. */
class TodoFieldRegistry
{
    private const SETTING = 'leantimelib.todo.detail.fieldLayout';
    private const PROJECT_SUFFIX = '.leantimelib.todo.detail.fieldLayout';
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

    /** Stable IDs occupied by native detail controls and their section headers. */
    public static function reservedWidgetIds(): array
    {
        return array_merge(array_keys(self::FIELDS), ['organization', 'schedule']);
    }

    public function __construct(private SettingService $settings, private TodoSidebarSectionRegistry $sidebarSections) {}

    public function fields(?int $projectId = null): array
    {
        $fields = self::FIELDS;
        foreach (app(TodoSectionRegistry::class)->getSections(null, ['projectId' => $projectId ?? 0], true) as $section) {
            $fields[$section['id']] = [
                'label' => $section['label'], 'icon' => $section['icon'], 'zone' => 'sidebar',
                'kind' => 'sections', 'enabled' => $section['enabled'], 'defaultEnabled' => $section['defaultEnabled'],
            ];
        }
        foreach ($this->sidebarSections->definitions() as $section) {
            $fields[$section['id']] = [
                'label' => $section['label'], 'icon' => $section['icon'], 'zone' => 'sidebar',
                'kind' => 'sectionHeader', 'children' => $section['defaultChildren'] ?? [],
                'enabled' => true, 'defaultEnabled' => true,
            ];
        }
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
        $layout['groups'] = [];
        $groupMembership = [];
        $hasSavedGroups = is_array($saved['groups'] ?? null);
        if ($hasSavedGroups) {
            foreach ($saved['groups'] as $sectionId => $childIds) {
                if (! is_string($sectionId) || ! isset($available[$sectionId]) || ($available[$sectionId]['kind'] ?? '') !== 'sectionHeader' || ! is_array($childIds)) continue;
                foreach ($childIds as $childId) {
                    if (! is_string($childId) || ! isset($available[$childId]) || ($available[$childId]['zone'] ?? '') !== 'sidebar'
                        || ($available[$childId]['kind'] ?? '') === 'sectionHeader'
                        || (! in_array($childId, $layout['sidebar'], true) && ! in_array($childId, $layout['hidden'], true))
                        || isset($groupMembership[$childId])) continue;
                    $layout['groups'][$sectionId][] = $childId;
                    $groupMembership[$childId] = $sectionId;
                }
            }
        }
        $defaultOrder = ['organization', 'type', 'project', 'milestone', 'sprint', 'related', 'schedule', 'workStart', 'workEnd', 'plannedHours'];
        foreach (array_keys($available) as $id) if (! in_array($id, $defaultOrder, true)) $defaultOrder[] = $id;
        foreach ($defaultOrder as $id) {
            if (! isset($available[$id])) continue;
            $field = $available[$id];
            if (! in_array($id, $layout['main'], true) && ! in_array($id, $layout['sidebar'], true)
                && ! in_array($id, $layout['auxiliary'], true) && ! in_array($id, $layout['hidden'], true)) {
                $defaultZone = in_array($field['kind'] ?? '', ['sections', 'sectionHeader'], true) && ! ($field['enabled'] ?? true) ? 'hidden' : $field['zone'];
                $layout[$defaultZone][] = $id;
            }
        }
        if (! $hasSavedGroups) {
            foreach ($this->sidebarSections->definitions() as $section) {
                $defaultChildren = $section['defaultChildren'] ?? [];
                $sequence = array_merge($layout['sidebar'], $layout['hidden']);
                foreach ($sequence as $childId) {
                    if (in_array($childId, $defaultChildren, true) && isset($available[$childId]) && ! isset($groupMembership[$childId])) {
                        $layout['groups'][$section['id']][] = $childId;
                        $groupMembership[$childId] = $section['id'];
                    }
                }
            }
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
        $normalized['groups'] = [];
        $grouped = [];
        foreach ($layout['groups'] ?? [] as $sectionId => $childIds) {
            if (! is_string($sectionId) || ! isset($available[$sectionId]) || ($available[$sectionId]['kind'] ?? '') !== 'sectionHeader' || ! is_array($childIds)) continue;
            foreach ($childIds as $id) {
                if (! is_string($id) || ! isset($available[$id]) || ($available[$id]['zone'] ?? '') !== 'sidebar'
                    || ($available[$id]['kind'] ?? '') === 'sectionHeader'
                    || (! in_array($id, $normalized['sidebar'], true) && ! in_array($id, $normalized['hidden'], true))
                    || isset($grouped[$id])) continue;
                $normalized['groups'][$sectionId][] = $id;
                $grouped[$id] = true;
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
            $defaultLayout = $this->getLayout();
            if ($normalized === $defaultLayout) {
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
