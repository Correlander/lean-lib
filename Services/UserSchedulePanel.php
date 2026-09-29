<?php

namespace Leantime\Plugins\LeantimeLib\Services;

use Leantime\Domain\Setting\Services\Setting as SettingService;

/** Adds a self-service work schedule panel to Leantime's profile settings tabs. */
class UserSchedulePanel
{
    public static function renderTabHeader(): void
    {
        if (! self::isEnabled()) return;
        echo '<li><a href="#workSchedule">Work schedule</a></li>';
    }

    public static function renderTabContent(): void
    {
        if (! self::isEnabled()) return;
        $userId = (int) session('userdata.id', 0);
        if ($userId < 1) return;
        $rawSchedule = app(SettingService::class)->getSetting('usersettings.'.$userId.'.daySchedule', '');
        $schedule = is_string($rawSchedule) && $rawSchedule !== '' ? safe_unserialize($rawSchedule, []) : [];
        if (! is_array($schedule)) $schedule = [];

        echo '<div id="workSchedule"><h4 class="widgettitle title-light">Work schedule</h4>';
        echo '<p>Set your usual work start, lunch, and end times. Leantime uses these preferences in schedule-aware views.</p>';
        echo '<form action="'.htmlspecialchars(rtrim(BASE_URL, '/').'/LeantimeLib/my-schedule', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" method="post">';
        echo '<input type="hidden" name="_token" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        foreach (['workStart' => 'Work starts', 'lunch' => 'Lunch', 'workEnd' => 'Work ends'] as $field => $label) {
            $selectedValue = (string) ($schedule[$field] ?? match ($field) {'workStart' => '8', 'lunch' => '12', default => '16'});
            echo '<div class="form-group"><label for="schedule-'.htmlspecialchars($field, ENT_QUOTES, 'UTF-8').'">'.$label.'</label><select class="form-control" name="'.$field.'" id="schedule-'.htmlspecialchars($field, ENT_QUOTES, 'UTF-8').'">';
            for ($hour = 0; $hour < 24; $hour += 2) {
                $value = (string) $hour;
                $selected = $value === $selectedValue ? ' selected' : '';
                $next = ($hour + 2) % 24;
                echo '<option value="'.$value.'"'.$selected.sprintf('>%02d:00–%02d:00</option>', $hour, $next);
            }
            echo '</select></div>';
        }
        echo '<button class="btn btn-primary" type="submit">Save work schedule</button></form></div>';
    }

    private static function isEnabled(): bool
    {
        $settings = app(SettingService::class);
        $value = $settings->getSetting('leantimelib.ui.fastOnboarding', null);
        if ($value === null || $value === false) {
            return filter_var($settings->getSetting('leantimelib.ui.hideOnboardingSteps', '0'), FILTER_VALIDATE_BOOLEAN)
                || filter_var($settings->getSetting('leantimelib.ui.disableStarterProject', '0'), FILTER_VALIDATE_BOOLEAN);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
