<?php

use Illuminate\Support\Facades\Route;
use Leantime\Plugins\LeantimeLib\Controllers\ProjectIntegrations;
use Leantime\Plugins\LeantimeLib\Controllers\Settings;
use Leantime\Plugins\LeantimeLib\Controllers\UserSchedule;

Route::get('/LeantimeLib/projectIntegrations/{projectId}', [ProjectIntegrations::class, 'show'])
    ->name('leantimeLib.projectIntegrations');

Route::post('/LeantimeLib/projectIntegrations/{projectId}/section-visibility', [ProjectIntegrations::class, 'saveSectionVisibility'])
    ->name('leantimeLib.projectIntegrations.sectionVisibility');

Route::post('/LeantimeLib/projectIntegrations/{projectId}/panel-order', [ProjectIntegrations::class, 'savePanelOrder'])
    ->name('leantimeLib.projectIntegrations.panelOrder');

Route::post('/LeantimeLib/projectIntegrations/{projectId}/todo-layout', [ProjectIntegrations::class, 'saveTodoLayout'])
    ->name('leantimeLib.projectIntegrations.todoLayout');

Route::post('/LeantimeLib/my-schedule', [UserSchedule::class, 'post'])
    ->name('leantimeLib.userSchedule.save');

Route::post('/LeantimeLib/plugins/check-for-updates', [Settings::class, 'syncPluginMetadata'])
    ->name('leantimeLib.plugins.syncMetadata');
