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
use Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry;

class Settings extends Controller
{
    private TodoTabRegistry $registry;
    private TodoSectionRegistry $sectionRegistry;
    private SettingService $settings;

    public function init(TodoTabRegistry $registry, TodoSectionRegistry $sectionRegistry, SettingService $settings): void
    {
        $this->registry = $registry;
        $this->sectionRegistry = $sectionRegistry;
        $this->settings = $settings;
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function get($params)
    {
        $this->assignPageData();

        return $this->tpl->display('leantimelib.settings');
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function post($params)
    {
        $input = $this->incomingRequest->only(['tabOrder', 'sectionOrder', 'tabEnabled', 'sectionEnabled', 'hideExploreApps']);
        try {
            $validated = ValidationException::validate($input, [
                'tabOrder' => ['nullable', 'array'],
                'tabOrder.*' => ['required', 'string', 'max:120'],
                'sectionOrder' => ['nullable', 'array'],
                'sectionOrder.*' => ['required', 'string', 'max:120'],
                'tabEnabled' => ['nullable', 'array'],
                'tabEnabled.*' => ['required', 'string', 'max:120'],
                'sectionEnabled' => ['nullable', 'array'],
                'sectionEnabled.*' => ['required', 'string', 'max:120'],
                'hideExploreApps' => ['nullable', 'boolean'],
            ], [
                'tabOrder.array' => 'The tab order was not submitted in the expected format.',
                'tabOrder.*.string' => 'A tab identifier must be text.',
                'tabOrder.*.max' => 'A tab identifier is too long.',
                'sectionOrder.array' => 'The To-do section order was not submitted in the expected format.',
                'sectionOrder.*.string' => 'A section identifier must be text.',
                'sectionOrder.*.max' => 'A section identifier is too long.',
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->getErrorData();
            $first = reset($errors);
            $this->assignPageData(is_array($first) ? reset($first) : 'Check the tab order and try again.');

            return $this->tpl->display('leantimelib.settings');
        }

        $order = $validated['tabOrder'] ?? [];
        $sectionOrder = $validated['sectionOrder'] ?? [];
        $failureLogged = false;
        try {
            $tabsSaved = is_array($order) && $this->registry->saveOrder($order);
            $sectionsSaved = is_array($sectionOrder) && $this->sectionRegistry->saveOrder($sectionOrder);
            $tabsEnabledSaved = $this->registry->saveEnabled($validated['tabEnabled'] ?? []);
            $sectionsEnabledSaved = $this->sectionRegistry->saveEnabled($validated['sectionEnabled'] ?? []);
            $hideExploreApps = filter_var($validated['hideExploreApps'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $uiPreferenceSaved = $this->settings->saveSetting('leantimelib.ui.hideExploreApps', $hideExploreApps ? '1' : '0');
            $saved = $tabsSaved && $sectionsSaved && $tabsEnabledSaved && $sectionsEnabledSaved && $uiPreferenceSaved;
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
            $this->assignPageData('The Library settings could not be saved.');

            return $this->tpl->display('leantimelib.settings');
        }

        $this->tpl->setNotification('Library settings saved.', 'success');

        return Frontcontroller::redirect(BASE_URL.'/LeantimeLib/settings');
    }

    private function assignPageData(?string $error = null): void
    {
        $this->tpl->assign('tabs', $this->registry->getTabs(null, true));
        $this->tpl->assign('sections', $this->sectionRegistry->getSections(null, [], true));
        $this->tpl->assign('hideExploreApps', filter_var(
            $this->settings->getSetting('leantimelib.ui.hideExploreApps', '0'),
            FILTER_VALIDATE_BOOLEAN
        ));
        $this->tpl->assign('error', $error);
    }
}
