<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry;

class Settings extends Controller
{
    private TodoTabRegistry $registry;

    public function init(TodoTabRegistry $registry): void
    {
        $this->registry = $registry;
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function get($params)
    {
        $this->tpl->assign('tabs', $this->registry->getTabs());
        $this->tpl->assign('error', null);

        return $this->tpl->display('leantimelib.settings');
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function post($params)
    {
        $input = $this->incomingRequest->only(['tabOrder']);
        try {
            $validated = ValidationException::validate($input, [
                'tabOrder' => ['nullable', 'array'],
                'tabOrder.*' => ['required', 'string', 'max:120'],
            ], [
                'tabOrder.array' => 'The tab order was not submitted in the expected format.',
                'tabOrder.*.string' => 'A tab identifier must be text.',
                'tabOrder.*.max' => 'A tab identifier is too long.',
            ]);
        } catch (ValidationException $exception) {
            $this->tpl->assign('tabs', $this->registry->getTabs());
            $errors = $exception->getErrorData();
            $first = reset($errors);
            $this->tpl->assign('error', is_array($first) ? reset($first) : 'Check the tab order and try again.');

            return $this->tpl->display('leantimelib.settings');
        }

        $order = $validated['tabOrder'] ?? [];
        $failureLogged = false;
        try {
            $saved = is_array($order) && $this->registry->saveOrder($order);
        } catch (\Throwable $exception) {
            Log::error('Leantime Library could not save the To-do tab order.', [
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            $saved = false;
            $failureLogged = true;
        }
        if (! $saved) {
            if (! $failureLogged) Log::error('Leantime Library tab-order setting could not be persisted.');
            $this->tpl->assign('tabs', $this->registry->getTabs());
            $this->tpl->assign('error', 'The tab order could not be saved.');

            return $this->tpl->display('leantimelib.settings');
        }

        $this->tpl->setNotification('To-do tab order saved.', 'success');

        return Frontcontroller::redirect(BASE_URL.'/LeantimeLib/settings');
    }
}
