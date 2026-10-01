<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Services\Registration;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Plugins\LeanLib\Services\TodoSectionRegistry;
use Leantime\Plugins\LeanLib\Services\TodoTabRegistry;
use Leantime\Plugins\LeanLib\Services\UserSchedulePanel;

$registration = app()->makeWith(Registration::class, ['pluginId' => 'LeanLib']);
$fastOnboardingValue = app(SettingService::class)->getSetting('leantimelib.ui.fastOnboarding', null);
$fastOnboarding = ($fastOnboardingValue === null || $fastOnboardingValue === false)
    ? filter_var(app(SettingService::class)->getSetting('leantimelib.ui.hideOnboardingSteps', '0'), FILTER_VALIDATE_BOOLEAN)
        || filter_var(app(SettingService::class)->getSetting('leantimelib.ui.disableStarterProject', '0'), FILTER_VALIDATE_BOOLEAN)
    : filter_var($fastOnboardingValue, FILTER_VALIDATE_BOOLEAN);

if ($fastOnboarding) {
    // Leantime 3.10.0 has no exposed cancellation hook around automatic starter
    // project creation; use a narrow request-scoped service replacement.
    app()->bind(\Leantime\Domain\Help\Services\Helper::class, \Leantime\Plugins\LeanLib\Services\NoDefaultProjectHelper::class);
    $registration->registerMiddleware([\Leantime\Plugins\LeanLib\Services\NoProjectRedirect::class]);
}

EventDispatcher::add_event_listener('leantime.*.afterLinkTags', function () use ($fastOnboarding): void {
    $hideExploreApps = filter_var(
        app(SettingService::class)->getSetting('leantimelib.ui.hideExploreApps', '0'),
        FILTER_VALIDATE_BOOLEAN
    );
    $preferences = json_encode(
        ['hideExploreApps' => $hideExploreApps, 'fastOnboarding' => $fastOnboarding, 'appUrl' => rtrim(BASE_URL, '/')],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    echo '<script>window.leanLibraryPreferences='.$preferences.';</script>';
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

if ($fastOnboarding) {
    EventDispatcher::add_event_listener('leantime.domain.users.templates.editOwn.tabs', [UserSchedulePanel::class, 'renderTabHeader']);
    EventDispatcher::add_event_listener('leantime.domain.users.templates.editOwn.tabsContent', [UserSchedulePanel::class, 'renderTabContent']);
}


EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showTicketModal.ticketTabsContent',
    [TodoTabRegistry::class, 'renderTabPanels']
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.*.beforeEndRightColumn',
    [TodoSectionRegistry::class, 'renderSections']
);
