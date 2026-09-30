<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Plugins\LeantimeLib\Services\TodoSectionRegistry;
use Leantime\Plugins\LeantimeLib\Services\TodoFieldRegistry;
use Leantime\Plugins\LeantimeLib\Services\TodoSidebarSectionRegistry;
use Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry;
use Leantime\Plugins\LeantimeLib\Services\GuiSurfaceRegistry;
use Leantime\Plugins\LeantimeLib\Services\SettingsPage;
use Leantime\Plugins\LeantimeLib\Services\SettingsPageBlock;

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

        return $this->tpl->display('leantimelib.settings');
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function syncPluginMetadata()
    {
        try {
            $result = app(\Leantime\Plugins\LeantimeLib\Services\PluginMetadataSynchronizer::class)->syncInstalledPluginMetadata();
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
        $input = $this->incomingRequest->only(['tabOrder', 'tabEnabled', 'fieldLayout', 'sidebarSectionsPresent', 'sidebarSections', 'projectIntegrationOrder', 'hideExploreApps', 'fastOnboarding', 'resetLayout']);
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
                'sidebarSectionsPresent' => ['nullable', 'boolean'],
                'sidebarSections' => ['nullable', 'array'],
                'sidebarSections.*.label' => ['required', 'string', 'max:80'],
                'sidebarSections.*.icon' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9 _-]*$/'],
                'projectIntegrationOrder' => ['sometimes', 'array'],
                'projectIntegrationOrder.*' => ['required', 'string', 'max:80'],
                'hideExploreApps' => ['nullable', 'boolean'],
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

            return $this->tpl->display('leantimelib.settings');
        }

        if (! filter_var($validated['resetLayout'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && empty($validated['tabEnabled'])) {
            if ($wantsJson) return response()->json(['error' => 'Keep at least one To-do tab visible.'], 422);
            $this->assignPageData('Keep at least one To-do tab visible.');
            return $this->tpl->display('leantimelib.settings');
        }

        if (filter_var($validated['resetLayout'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->registry->resetLayout();
            $this->sidebarSectionRegistry->resetDefinitions();
            $this->fieldRegistry->resetLayout();
            if ($wantsJson) return response()->json(['saved' => true, 'reset' => true]);
            $this->tpl->setNotification('To-do layout reset to defaults.', 'success');

            return Frontcontroller::redirect(BASE_URL.'/LeantimeLib/settings');
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
            $hideExploreApps = filter_var($validated['hideExploreApps'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $uiPreferenceSaved = $this->settings->saveSetting('leantimelib.ui.hideExploreApps', $hideExploreApps ? '1' : '0');
            $fastOnboarding = filter_var($validated['fastOnboarding'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $fastOnboardingSaved = $this->settings->saveSetting('leantimelib.ui.fastOnboarding', $fastOnboarding ? '1' : '0');
            $projectIntegrationOrderSaved = ! array_key_exists('projectIntegrationOrder', $validated)
                || app(\Leantime\Plugins\LeantimeLib\Services\ProjectIntegrationRegistry::class)
                    ->saveGlobalOrder($validated['projectIntegrationOrder']);
            $saved = $sidebarSectionsSaved && $tabsSaved && $tabsEnabledSaved && $fieldLayoutSaved && $uiPreferenceSaved && $fastOnboardingSaved && $projectIntegrationOrderSaved;
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

            return $this->tpl->display('leantimelib.settings');
        }

        if ($wantsJson) return response()->json(['saved' => true]);
        $this->tpl->setNotification('Library settings saved.', 'success');

        return Frontcontroller::redirect(BASE_URL.'/LeantimeLib/settings');
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
        $hideExploreApps = filter_var(
            $this->settings->getSetting('leantimelib.ui.hideExploreApps', '0'),
            FILTER_VALIDATE_BOOLEAN
        );
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
        $settingsContent = SettingsPage::forPlugin('LeantimeLib')
            ->title('lean-library')
            ->description('One place to manage how enabled Leantime plugins and native interface components fit together.')
            ->footerAction('Check for updates', BASE_URL.'/LeantimeLib/plugins/check-for-updates', csrf_token())
            ->insert(
                SettingsPageBlock::section('General improvements', 'Optional changes to Leantime’s navigation and onboarding.'),
                SettingsPageBlock::checkbox('hideExploreApps', 'Make My Apps the only Apps page', 'Hide Explore Apps and send the Apps menu directly to My Apps.'),
                SettingsPageBlock::checkbox('fastOnboarding', 'Fast Onboarding', 'Skip appearance and schedule steps, avoid creating a starter “My Project,” and let users adjust their schedule later in Profile settings.'),
                SettingsPageBlock::section('GUI customization', 'Arrange native interface parts and plugin contributions through one shared layout. Plugins provide their widgets; the Library controls where they appear and which ones are visible. If the editor looks cramped or squished, press Ctrl + - to zoom out.'),
                SettingsPageBlock::description('Projects use this layout unless they have a project override.'),
                SettingsPageBlock::custom(static fn (array $values = [], array $errors = []): string => $guiEditorHtml)
            )
            ->render([
            'hideExploreApps' => $hideExploreApps,
            'fastOnboarding' => $fastOnboarding,
        ]);
        $this->tpl->assign('settingsContent', $settingsContent);
        $this->tpl->assign('error', $error);
    }
}
