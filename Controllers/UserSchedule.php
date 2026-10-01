<?php

namespace Leantime\Plugins\LeanLib\Controllers;

use Illuminate\Http\Request;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Setting\Services\Setting as SettingService;

class UserSchedule extends Controller
{
    private SettingService $settings;

    public function init(SettingService $settings): void
    {
        $this->settings = $settings;
    }

    public function post(Request $request)
    {
        $userId = (int) session('userdata.id', 0);
        if ($userId < 1) abort(401);

        $valid = ValidationException::validate($request->only(['workStart', 'lunch', 'workEnd']), [
            'workStart' => ['required', 'integer', 'in:0,2,4,6,8,10,12,14,16,18,20,22'],
            'lunch' => ['required', 'integer', 'in:0,2,4,6,8,10,12,14,16,18,20,22'],
            'workEnd' => ['required', 'integer', 'in:0,2,4,6,8,10,12,14,16,18,20,22'],
        ]);
        $schedule = serialize([
            'wakeup' => '',
            'workStart' => (string) $valid['workStart'],
            'lunch' => (string) $valid['lunch'],
            'workEnd' => (string) $valid['workEnd'],
            'bed' => '',
        ]);
        if ($this->settings->saveSetting('usersettings.'.$userId.'.daySchedule', $schedule)) {
            $this->tpl->setNotification('Work schedule saved.', 'success');
        } else {
            $this->tpl->setNotification('Work schedule could not be saved.', 'error');
        }

        return Frontcontroller::redirect(BASE_URL.'/users/editOwn#workSchedule');
    }
}
