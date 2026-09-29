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
use Leantime\Plugins\LeantimeLib\Services\TodoLayoutEditor;

class Settings extends Controller
{
    private TodoTabRegistry $registry;
    private TodoSectionRegistry $sectionRegistry;
    private TodoFieldRegistry $fieldRegistry;
    private TodoSidebarSectionRegistry $sidebarSectionRegistry;
    private SettingService $settings;

    public function init(TodoTabRegistry $registry, TodoSectionRegistry $sectionRegistry, TodoFieldRegistry $fieldRegistry, TodoSidebarSectionRegistry $sidebarSectionRegistry, SettingService $settings): void
    {
        $this->registry = $registry;
        $this->sectionRegistry = $sectionRegistry;
        $this->fieldRegistry = $fieldRegistry;
        $this->sidebarSectionRegistry = $sidebarSectionRegistry;
        $this->settings = $settings;
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
        $input = $this->incomingRequest->only(['tabOrder', 'tabEnabled', 'fieldLayout', 'sidebarSectionsPresent', 'sidebarSections', 'hideExploreApps', 'fastOnboarding', 'resetLayout']);
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
            $saved = $sidebarSectionsSaved && $tabsSaved && $tabsEnabledSaved && $fieldLayoutSaved && $uiPreferenceSaved && $fastOnboardingSaved;
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
        $this->tpl->assign('tabs', $this->registry->getTabs(null, true));
        $this->tpl->assign('sections', $this->sectionRegistry->getSections(null, [], true));
        $this->tpl->assign('todoLayoutEditor', app(TodoLayoutEditor::class)->renderGlobalControls(
            $this->registry->getTabs(null, true),
            $this->sectionRegistry->getSections(null, [], true)
        ));
        $this->tpl->assign('hideExploreApps', filter_var(
            $this->settings->getSetting('leantimelib.ui.hideExploreApps', '0'),
            FILTER_VALIDATE_BOOLEAN
        ));
        $fastOnboarding = $this->settings->getSetting('leantimelib.ui.fastOnboarding', null);
        if ($fastOnboarding === null || $fastOnboarding === false) {
            $fastOnboarding = filter_var($this->settings->getSetting('leantimelib.ui.hideOnboardingSteps', '0'), FILTER_VALIDATE_BOOLEAN)
                || filter_var($this->settings->getSetting('leantimelib.ui.disableStarterProject', '0'), FILTER_VALIDATE_BOOLEAN);
        }
        $this->tpl->assign('fastOnboarding', filter_var($fastOnboarding, FILTER_VALIDATE_BOOLEAN));
        $this->tpl->assign('error', $error);
    }
}
