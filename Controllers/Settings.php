<?php

namespace Leantime\Plugins\LeanLib\Controllers;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Domain\Plugins\Services\Plugins as PluginService;
use Leantime\Plugins\LeanLib\Services\TodoSectionRegistry;
use Leantime\Plugins\LeanLib\Services\TodoFieldRegistry;
use Leantime\Plugins\LeanLib\Services\TodoSidebarSectionRegistry;
use Leantime\Plugins\LeanLib\Services\TodoTabRegistry;
use Leantime\Plugins\LeanLib\Services\GuiSurfaceRegistry;
use Leantime\Plugins\LeanLib\Services\SettingsPage;
use Leantime\Plugins\LeanLib\Services\SettingsPageBlock;

class Settings extends Controller
{
    private TodoTabRegistry $registry;
    private TodoSectionRegistry $sectionRegistry;
    private TodoFieldRegistry $fieldRegistry;
    private TodoSidebarSectionRegistry $sidebarSectionRegistry;
    private SettingService $settings;
    private GuiSurfaceRegistry $guiSurfaces;

    public function init(TodoTabRegistry $registry, TodoSectionRegistry $sectionRegistry, TodoFieldRegistry $fieldRegistry, TodoSidebarSectionRegistry $sidebarSectionRegistry, SettingService $settings, GuiSurfaceRegistry $guiSurfaces): void
    {
        $this->registry = $registry;
        $this->sectionRegistry = $sectionRegistry;
        $this->fieldRegistry = $fieldRegistry;
        $this->sidebarSectionRegistry = $sidebarSectionRegistry;
        $this->settings = $settings;
        $this->guiSurfaces = $guiSurfaces;
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function get($params)
    {
        $this->assignPageData();

        return $this->tpl->display('leanlib.settings');
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function syncPluginMetadata()
    {
        try {
            $result = app(\Leantime\Plugins\LeanLib\Services\PluginMetadataSynchronizer::class)->syncInstalledPluginMetadata();
            return response()->json($result);
        } catch (\Throwable $exception) {
            Log::error('Leantime Library plugin metadata refresh failed.', [
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            return response()->json(['error' => 'Plugin metadata could not be refreshed. Check the Leantime application log.'], 500);
        }
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function post($params)
    {
        $wantsJson = request()->expectsJson();
        $input = $this->incomingRequest->only(['tabOrder', 'tabEnabled', 'fieldLayout', 'tabWidgets', 'tabWidgetsActive', 'todoContentWidgets', 'sidebarSectionsPresent', 'sidebarSections', 'projectIntegrationOrder', 'fastOnboarding', 'resetLayout']);
        try {
            $validated = ValidationException::validate($input, [
                'tabOrder' => ['nullable', 'array'],
                'tabOrder.*' => ['required', 'string', 'max:120'],
                'tabEnabled' => ['nullable', 'array'],
                'tabEnabled.*' => ['required', 'string', 'max:120'],
                'fieldLayout' => ['nullable', 'array'],
                'fieldLayout.main' => ['nullable', 'array'],
                'fieldLayout.main.*' => ['required', 'string', 'max:120'],
                'fieldLayout.auxiliary' => ['nullable', 'array'],
                'fieldLayout.auxiliary.*' => ['required', 'string', 'max:120'],
                'fieldLayout.sidebar' => ['nullable', 'array'],
                'fieldLayout.sidebar.*' => ['required', 'string', 'max:120'],
                'fieldLayout.parked' => ['nullable', 'array'],
                'fieldLayout.parked.*' => ['required', 'string', 'max:120'],
                'fieldLayout.groups' => ['nullable', 'array'],
                'fieldLayout.groups.*' => ['nullable', 'array'],
                'fieldLayout.groups.*.*' => ['required', 'string', 'max:120'],
                'tabWidgets' => ['nullable', 'array'],
                'tabWidgets.*' => ['nullable', 'array'],
                'tabWidgets.*.*' => ['required', 'string', 'max:120'],
                'tabWidgetsActive' => ['nullable', 'string', 'max:120'],
                'todoContentWidgets' => ['nullable', 'array'],
                'todoContentWidgets.regions' => ['nullable', 'array'],
                'todoContentWidgets.regions.*' => ['nullable', 'array'],
                'todoContentWidgets.regions.*.*' => ['nullable', 'array'],
                'todoContentWidgets.regions.*.*.*' => ['required', 'string', 'max:120'],
                'todoContentWidgets.parked' => ['nullable', 'array'],
                'todoContentWidgets.parked.*' => ['required', 'string', 'max:120'],
                'todoContentWidgets.parkedTargets' => ['nullable', 'array'],
                'todoContentWidgets.parkedTargets.*' => ['nullable', 'array'],
                'todoContentWidgets.parkedTargets.*.tab' => ['required_with:todoContentWidgets.parkedTargets.*', 'string', 'max:120'],
                'todoContentWidgets.parkedTargets.*.region' => ['required_with:todoContentWidgets.parkedTargets.*', 'string', 'max:40', 'regex:/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/'],
                'sidebarSectionsPresent' => ['nullable', 'boolean'],
                'sidebarSections' => ['nullable', 'array'],
                'sidebarSections.*.label' => ['required', 'string', 'max:80'],
                'sidebarSections.*.icon' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9 _-]*$/'],
                'projectIntegrationOrder' => ['sometimes', 'array'],
                'projectIntegrationOrder.*' => ['required', 'string', 'max:80'],
                'fastOnboarding' => ['nullable', 'boolean'],
                'resetLayout' => ['nullable', 'boolean'],
            ], [
                'tabOrder.array' => 'The tab order was not submitted in the expected format.',
                'tabOrder.*.string' => 'A tab identifier must be text.',
                'tabOrder.*.max' => 'A tab identifier is too long.',
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->getErrorData();
            $first = reset($errors);
            $message = is_array($first) ? reset($first) : 'Check the submitted Library settings and try again.';
            if ($wantsJson) return response()->json(['error' => $message, 'errors' => $errors], 422);
            $this->assignPageData(is_array($first) ? reset($first) : 'Check the tab order and try again.');

            return $this->tpl->display('leanlib.settings');
        }

        if (! filter_var($validated['resetLayout'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && empty($validated['tabEnabled'])) {
            if ($wantsJson) return response()->json(['error' => 'Keep at least one To-do tab visible.'], 422);
            $this->assignPageData('Keep at least one To-do tab visible.');
            return $this->tpl->display('leanlib.settings');
        }

        if (filter_var($validated['resetLayout'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->registry->resetLayout();
            $this->sidebarSectionRegistry->resetDefinitions();
            $this->fieldRegistry->resetLayout();
            if ($wantsJson) return response()->json(['saved' => true, 'reset' => true]);
            $this->tpl->setNotification('To-do layout reset to defaults.', 'success');

            return Frontcontroller::redirect(BASE_URL.'/LeanLib/settings');
        }

        $order = $validated['tabOrder'] ?? [];
        $failureLogged = false;
        try {
            $sidebarSectionsSaved = ! filter_var($validated['sidebarSectionsPresent'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || $this->sidebarSectionRegistry->saveDefinitions($validated['sidebarSections'] ?? []);
            $tabsSaved = is_array($order) && $this->registry->saveOrder($order);
            $tabsEnabledSaved = $this->registry->saveEnabled($validated['tabEnabled'] ?? []);
            $fieldLayoutSaved = $this->fieldRegistry->saveLayout([
                'main' => $validated['fieldLayout']['main'] ?? [],
                'auxiliary' => $validated['fieldLayout']['auxiliary'] ?? [],
                'sidebar' => $validated['fieldLayout']['sidebar'] ?? [],
                'hidden' => $validated['fieldLayout']['parked'] ?? [],
                'groups' => $validated['fieldLayout']['groups'] ?? [],
            ]);
            $tabWidgets = $validated['tabWidgets'] ?? [];
            if (isset($validated['tabWidgetsActive'])) $tabWidgets['activeTab'] = $validated['tabWidgetsActive'];
            $tabWidgetsSaved = $this->registry->saveTabWidgets($tabWidgets);
            $todoContentWidgets = $validated['todoContentWidgets'] ?? [];
            $todoContentWidgetsSaved = app(\Leantime\Plugins\LeanLib\Services\TodoWidgetRegistry::class)->saveLayout(
                $todoContentWidgets['regions'] ?? [],
                $todoContentWidgets['parked'] ?? [],
                null,
                $todoContentWidgets['parkedTargets'] ?? []
            );
            $fastOnboarding = filter_var($validated['fastOnboarding'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $fastOnboardingSaved = $this->settings->saveSetting('leantimelib.ui.fastOnboarding', $fastOnboarding ? '1' : '0');
            $projectIntegrationOrderSaved = ! array_key_exists('projectIntegrationOrder', $validated)
                || app(\Leantime\Plugins\LeanLib\Services\ProjectIntegrationRegistry::class)
                    ->saveGlobalOrder($validated['projectIntegrationOrder']);
            $saved = $sidebarSectionsSaved && $tabsSaved && $tabsEnabledSaved && $fieldLayoutSaved && $tabWidgetsSaved && $todoContentWidgetsSaved && $fastOnboardingSaved && $projectIntegrationOrderSaved;
        } catch (\Throwable $exception) {
            Log::error('Leantime Library could not save the To-do layout order.', [
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            $saved = false;
            $failureLogged = true;
        }
        if (! $saved) {
            if (! $failureLogged) Log::error('Leantime Library To-do layout order could not be persisted.');
            if ($wantsJson) return response()->json(['error' => 'The Library settings could not be saved.'], 500);
            $this->assignPageData('The Library settings could not be saved.');

            return $this->tpl->display('leanlib.settings');
        }

        if ($wantsJson) return response()->json(['saved' => true]);
        $this->tpl->setNotification('Library settings saved.', 'success');

        return Frontcontroller::redirect(BASE_URL.'/LeanLib/settings');
    }

    private function assignPageData(?string $error = null): void
    {
        $tabs = $this->registry->getTabs(null, true);
        $sections = $this->sectionRegistry->getSections(null, [], true);
        $surfaces = $this->guiSurfaces->getSurfaces();
        foreach ($surfaces as &$surface) {
            $surface['editorHtml'] = $this->guiSurfaces->renderEditor($surface, ['scope' => 'instance']);
        }
        unset($surface);
        $fastOnboarding = $this->settings->getSetting('leantimelib.ui.fastOnboarding', null);
        if ($fastOnboarding === null || $fastOnboarding === false) {
            $fastOnboarding = filter_var($this->settings->getSetting('leantimelib.ui.hideOnboardingSteps', '0'), FILTER_VALIDATE_BOOLEAN)
                || filter_var($this->settings->getSetting('leantimelib.ui.disableStarterProject', '0'), FILTER_VALIDATE_BOOLEAN);
        }
        $fastOnboarding = filter_var($fastOnboarding, FILTER_VALIDATE_BOOLEAN);

        $pluginTabs = array_filter($tabs, static fn ($tab) => ! $tab['builtin']);
        $pluginSections = array_filter($sections, static fn ($section) => ! $section['builtin']);
        $guiEditorHtml = view()->file(__DIR__.'/../Templates/gui-settings-editor.blade.php', [
            'guiSurfaces' => $surfaces,
            'hasContributions' => count($pluginTabs) > 0 || count($pluginSections) > 0,
            'showContributionMessage' => true,
        ])->render();
        $settingsContent = SettingsPage::forPlugin('LeanLib')
            ->title('lean-lib')
            ->description('One place to manage how enabled Leantime plugins and native interface components fit together.')
            ->footer(true)
            ->footerAction('Check for updates', BASE_URL.'/LeanLib/plugins/check-for-updates', csrf_token())
            ->insert(
                SettingsPageBlock::section('General improvements', 'Optional changes to Leantime’s navigation and onboarding.'),
                SettingsPageBlock::checkbox('fastOnboarding', 'Fast Onboarding', 'Skip appearance and schedule steps, avoid creating a starter “My Project,” and let users adjust their schedule later in Profile settings.'),
                SettingsPageBlock::section('GUI customization', 'Arrange native interface parts and plugin contributions through one shared layout. Plugins provide their widgets; the Library controls where they appear and which ones are visible. If the editor looks cramped or squished, press Ctrl + - to zoom out.'),
                SettingsPageBlock::description('Projects use this layout unless they have a project override.'),
                SettingsPageBlock::custom(static fn (array $values = [], array $errors = []): string => $guiEditorHtml)
            )
            ->render([
            'fastOnboarding' => $fastOnboarding,
        ]);
        $this->tpl->assign('settingsContent', $settingsContent);
        $this->tpl->assign('error', $error);
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function saveCompanySettingsLayout(): mixed
    {
        $sessionToken = session()->token();
        $requestToken = request()->input('_token', request()->header('X-CSRF-TOKEN'));
        if (!is_string($sessionToken) || !is_string($requestToken) || !hash_equals($sessionToken, $requestToken)) {
            return response()->json(['error' => 'The session token is invalid. Refresh the page and try again.'], 419);
        }
        $input = request()->all();
        try {
            $validated = ValidationException::validate($input, [
                'tabs' => ['required', 'array', 'min:1', 'max:3'],
                'tabs.*' => ['required', 'string', 'max:48', 'regex:/^(details|apiKeys|integrations)$/'],
                'activeTab' => ['required', 'string', 'max:48', 'regex:/^(details|apiKeys|integrations)$/'],
                'regions' => ['nullable', 'array'],
                'regions.*' => ['nullable', 'array'],
                'regions.*.*' => ['nullable', 'array'],
                'regions.*.*.*' => ['required', 'string', 'max:120'],
                'parked' => ['nullable', 'array'],
                'parked.*' => ['required', 'string', 'max:120'],
                'parkedTargets' => ['nullable', 'array'],
                'parkedTargets.*' => ['nullable', 'array'],
                'parkedTargets.*.tab' => ['required_with:parkedTargets.*', 'string', 'max:48', 'regex:/^(details|apiKeys|integrations)$/'],
                'parkedTargets.*.region' => ['required_with:parkedTargets.*', 'string', 'max:40', 'regex:/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/'],
            ]);
            $tabs = array_values(array_unique(array_intersect($validated['tabs'], ['details', 'apiKeys', 'integrations'])));
            $activeTab = in_array($validated['activeTab'], $tabs, true) ? $validated['activeTab'] : ($tabs[0] ?? 'details');
            if ($tabs === []) $tabs = ['details'];
            $widgetDefinitions = app(\Leantime\Plugins\LeanLib\Services\CompanySettingsEditor::class)->widgetDefinitions();
            $availableWidgets = array_keys($widgetDefinitions);
            $parked = array_values(array_unique(array_intersect($validated['parked'] ?? [], $availableWidgets)));
            $parkedTargets = [];
            $regions = [];
            $placedWidgets = [];
            foreach ($parked as $widgetId) {
                $definition = $widgetDefinitions[$widgetId];
                $target = $validated['parkedTargets'][$widgetId] ?? [];
                $tab = is_string($target['tab'] ?? null) && in_array($target['tab'], $tabs, true) ? $target['tab'] : ($definition['tab'] ?? $tabs[0]);
                $allowedRegions = app(\Leantime\Plugins\LeanLib\Services\CompanySettingsEditor::class)->regionsForTab($tab);
                if (($definition['placement'] ?? 'region') !== 'any' && !in_array($definition['region'] ?? 'content', $allowedRegions, true)) {
                    $tab = in_array($definition['tab'] ?? null, $tabs, true) ? $definition['tab'] : $tabs[0];
                    $allowedRegions = app(\Leantime\Plugins\LeanLib\Services\CompanySettingsEditor::class)->regionsForTab($tab);
                }
                $region = is_string($target['region'] ?? null) && in_array($target['region'], $allowedRegions, true)
                    && (($definition['placement'] ?? 'region') === 'any' || $target['region'] === ($definition['region'] ?? 'content'))
                    ? $target['region'] : (in_array($definition['region'] ?? 'content', $allowedRegions, true) ? $definition['region'] : 'content');
                $parkedTargets[$widgetId] = ['tab' => $tab, 'region' => $region];
            }
            foreach ($tabs as $tab) {
                foreach ($validated['regions'][$tab] ?? [] as $regionName => $requested) {
                    if (!is_string($regionName) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/', $regionName) || !is_array($requested)) continue;
                    $allowedRegions = app(\Leantime\Plugins\LeanLib\Services\CompanySettingsEditor::class)->regionsForTab($tab);
                    if (!in_array($regionName, $allowedRegions, true)) continue;
                    foreach (array_values(array_unique(array_intersect($requested, $availableWidgets))) as $widgetId) {
                        if (in_array($widgetId, $parked, true) || in_array($widgetId, $placedWidgets, true)) continue;
                        $definition = $widgetDefinitions[$widgetId];
                        if (($definition['placement'] ?? 'region') !== 'any' && ($definition['region'] ?? '') !== $regionName) continue;
                        if (!isset($regions[$tab][$regionName])) $regions[$tab][$regionName] = [];
                        $regions[$tab][$regionName][] = $widgetId;
                        $placedWidgets[] = $widgetId;
                    }
                }
            }
            foreach ($availableWidgets as $id) {
                if (in_array($id, $parked, true)) continue;
                $definition = $widgetDefinitions[$id] ?? [];
                if (!in_array($id, $placedWidgets, true)) {
                    $preferredTab = $definition['tab'] ?? 'integrations';
                    $tab = in_array($preferredTab, $tabs, true) ? $preferredTab : $tabs[0];
                    $regionName = $definition['region'] ?? 'content';
                    if (!isset($regions[$tab][$regionName])) $regionName = 'content';
                    $regions[$tab][$regionName][] = $id;
                    $placedWidgets[] = $id;
                }
            }
            $ok = $this->settings->saveSetting('leantimelib.gui.companySettings', json_encode([
                'tabs' => $tabs, 'activeTab' => $activeTab, 'regions' => $regions, 'parked' => $parked, 'parkedTargets' => $parkedTargets,
            ], JSON_THROW_ON_ERROR));
            return response()->json($ok ? ['saved' => true] : ['error' => 'Company settings layout could not be saved.'], $ok ? 200 : 500);
        } catch (ValidationException $exception) {
            return response()->json(['error' => 'The company settings layout was invalid.', 'errors' => $exception->getErrorData()], 422);
        }
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function integrations(): mixed
    {
        $plugins = app(PluginService::class);
        $installed = $plugins->getAllPlugins();
        $discovered = $plugins->discoverNewPlugins();
        $layout = app(\Leantime\Plugins\LeanLib\Services\CompanySettingsEditor::class)->layout();
        $content = view()->file(__DIR__.'/../Templates/integrations.blade.php', ['layout' => $layout, 'installedPlugins' => $installed, 'newPlugins' => $discovered])->render();
        $this->tpl->assign('settingsContent', $content);
        return $this->tpl->display('leanlib.integrations-page');
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function activatePlugin(): mixed
    {
        $sessionToken = session()->token();
        $requestToken = request()->input('_token', request()->header('X-CSRF-TOKEN'));
        if (!is_string($sessionToken) || !is_string($requestToken) || !hash_equals($sessionToken, $requestToken)) {
            abort(419, 'The session token is invalid. Refresh the page and try again.');
        }
        $id = request()->input('plugin');
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id)) {
            abort(422, 'The plugin identifier is invalid.');
        }
        $plugins = app(PluginService::class);
        $discoveredIds = array_map(static fn ($plugin) => is_object($plugin) ? ($plugin->foldername ?? null) : null, $plugins->discoverNewPlugins());
        if (!in_array($id, $discoveredIds, true)) abort(404, 'The plugin is not available to install.');
        $result = $plugins->performPluginAction('install', $id);
        if (is_array($result) && isset($result[1]) && $result[1] === 'error') {
            $this->tpl->setNotification((string) ($result[0] ?? 'The plugin could not be activated.'), 'error');
        } else {
            $this->tpl->setNotification((string) ($result[0] ?? 'Plugin activated.'), 'success');
        }
        return Frontcontroller::redirect(BASE_URL.'/LeanLib/integrations');
    }
}
