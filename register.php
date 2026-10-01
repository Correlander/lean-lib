<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Services\Registration;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Plugins\LeanLib\Services\AccountIntegrationRegistry;
use Leantime\Plugins\LeanLib\Services\TodoSectionRegistry;
use Leantime\Plugins\LeanLib\Services\TodoTabRegistry;
use Leantime\Plugins\LeanLib\Services\UserSchedulePanel;
use Leantime\Plugins\LeanLib\Services\CompanySettingsEditor;
use Leantime\Plugins\LeanLib\Services\PluginManagerRegistry;
use Leantime\Plugins\LeanLib\Services\TodoWidgetRegistry;

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

$registration->registerMiddleware([\Leantime\Plugins\LeanLib\Services\PluginManagementRedirect::class]);

// Replace the native Apps entry with the Library manager while preserving
// Leantime's plugin lifecycle routes and all other Administration links.
EventDispatcher::add_filter_listener(
    'leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures.company',
    static function (array $menu): array {
        foreach ($menu as $menuKey => $item) {
            if (($item['id'] ?? null) !== 'administration' || ! is_array($item['submenu'] ?? null)) {
                continue;
            }

            foreach ($item['submenu'] as $key => $entry) {
                if (($entry['module'] ?? null) === 'plugins'
                    && ($entry['href'] ?? null) === '/plugins/marketplace') {
                    unset($menu[$menuKey]['submenu'][$key]);
                }
            }
        }

        return $menu;
    }
);

EventDispatcher::add_event_listener('leantime.*.afterLinkTags', function () use ($fastOnboarding): void {
    $preferences = json_encode(
        ['fastOnboarding' => $fastOnboarding, 'appUrl' => rtrim(BASE_URL, '/')],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    echo '<script>window.leanLibraryPreferences='.$preferences.';</script>';
});

$registration->addHeaderJs(['app-preferences.js']);
$registration->addFooterJs(['library-settings.js', 'project-integrations.js', 'todo-layout.js', 'company-settings.js']);
$registration->addCss(['library-settings.css', 'project-integrations.css', 'company-settings.css', 'integrations-manager.css']);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showTicketModal.ticketTabs',
    [TodoTabRegistry::class, 'renderTabHeaders']
);

EventDispatcher::add_event_listener('leantime.domain.setting.templates.editCompanySettings.tabs', [CompanySettingsEditor::class, 'renderTabHeader']);
EventDispatcher::add_event_listener('leantime.domain.setting.templates.editCompanySettings.tabsContent', [CompanySettingsEditor::class, 'renderTabContent']);
EventDispatcher::add_filter_listener(
    PluginManagerRegistry::FILTER,
    static function (array $entries, array $context): array {
        if (($context['pluginId'] ?? null) !== 'LeanLib') return $entries;

        $entries[] = [
            'apiVersion' => PluginManagerRegistry::API_VERSION,
            'pluginId' => 'LeanLib',
            'render' => static fn (array $context): string => '<p>Manage shared GUI layouts, Library preferences, and integration contributions from the settings page below.</p>',
        ];
        return $entries;
    }
);
EventDispatcher::add_event_listener('leantime.domain.users.templates.editOwn.tabs', [AccountIntegrationRegistry::class, 'renderTabHeader']);
EventDispatcher::add_event_listener('leantime.domain.users.templates.editOwn.tabsContent', [AccountIntegrationRegistry::class, 'renderTabContent']);

if ($fastOnboarding) {
    EventDispatcher::add_event_listener('leantime.domain.users.templates.editOwn.tabs', [UserSchedulePanel::class, 'renderTabHeader']);
    EventDispatcher::add_event_listener('leantime.domain.users.templates.editOwn.tabsContent', [UserSchedulePanel::class, 'renderTabContent']);
}


EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showTicketModal.ticketTabsContent',
    [TodoTabRegistry::class, 'renderTabPanels']
);
EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showTicketModal.ticketTabsContent',
    [TodoWidgetRegistry::class, 'renderWidgets']
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.*.beforeEndRightColumn',
    [TodoSectionRegistry::class, 'renderSections']
);
