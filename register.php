<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Services\Registration;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Plugins\LeantimeLib\Services\TodoSectionRegistry;
use Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry;

$registration = app()->makeWith(Registration::class, ['pluginId' => 'LeantimeLib']);

EventDispatcher::add_event_listener('leantime.*.afterLinkTags', function (): void {
    $hideExploreApps = filter_var(
        app(SettingService::class)->getSetting('leantimelib.ui.hideExploreApps', '0'),
        FILTER_VALIDATE_BOOLEAN
    );
    $preferences = json_encode(
        ['hideExploreApps' => $hideExploreApps, 'appUrl' => rtrim(BASE_URL, '/')],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    echo '<script>window.leantimeLibraryPreferences='.$preferences.';</script>';
});

$registration->addHeaderJs(['app-preferences.js']);
$registration->addFooterJs(['library-settings.js', 'project-integrations.js', 'todo-layout.js']);
$registration->addCss(['library-settings.css', 'project-integrations.css']);

EventDispatcher::add_filter_listener(
    'leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures.company',
    function (array $menu, array $params): array {
        $hideExploreApps = filter_var(
            app(SettingService::class)->getSetting('leantimelib.ui.hideExploreApps', '0'),
            FILTER_VALIDATE_BOOLEAN
        );
        if (! $hideExploreApps) {
            return $menu;
        }

        foreach ($menu as &$menuItem) {
            if (($menuItem['id'] ?? null) !== 'administration' || ! is_array($menuItem['submenu'] ?? null)) {
                continue;
            }
            foreach ($menuItem['submenu'] as &$child) {
                if (($child['module'] ?? null) === 'plugins') {
                    $child['href'] = '/plugins/myapps';
                }
            }
            unset($child);
        }
        unset($menuItem);

        return $menu;
    }
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showTicketModal.ticketTabs',
    [TodoTabRegistry::class, 'renderTabHeaders']
);


EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showTicketModal.ticketTabsContent',
    [TodoTabRegistry::class, 'renderTabPanels']
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.*.beforeEndRightColumn',
    [TodoSectionRegistry::class, 'renderSections']
);
