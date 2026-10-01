<?php

use Illuminate\Support\Facades\Route;
use Leantime\Plugins\LeanLib\Controllers\ProjectIntegrations;
use Leantime\Plugins\LeanLib\Controllers\Settings;
use Leantime\Plugins\LeanLib\Controllers\UserSchedule;

Route::get('/LeanLib/projectIntegrations/{projectId}', [ProjectIntegrations::class, 'show'])
    ->name('leanLib.projectIntegrations');

Route::post('/LeanLib/projectIntegrations/{projectId}/section-visibility', [ProjectIntegrations::class, 'saveSectionVisibility'])
    ->name('leanLib.projectIntegrations.sectionVisibility');

Route::post('/LeanLib/projectIntegrations/{projectId}/panel-order', [ProjectIntegrations::class, 'savePanelOrder'])
    ->name('leanLib.projectIntegrations.panelOrder');

Route::post('/LeanLib/projectIntegrations/{projectId}/todo-layout', [ProjectIntegrations::class, 'saveTodoLayout'])
    ->name('leanLib.projectIntegrations.todoLayout');

Route::post('/LeanLib/my-schedule', [UserSchedule::class, 'post'])
    ->name('leanLib.userSchedule.save');

Route::post('/LeanLib/plugins/check-for-updates', [Settings::class, 'syncPluginMetadata'])
    ->name('leanLib.plugins.syncMetadata');

Route::get('/LeanLib/integrations', [Settings::class, 'integrations'])->name('leanLib.integrations');
Route::post('/LeanLib/gui/company-settings', [Settings::class, 'saveCompanySettingsLayout'])->name('leanLib.gui.companySettings.save');
Route::post('/LeanLib/integrations/activate', [Settings::class, 'activatePlugin'])->name('leanLib.integrations.activate'); // Backward-compatible install action; now preflighted.
Route::post('/LeanLib/integrations/action', [Settings::class, 'managePlugin'])->name('leanLib.integrations.action');

// Backward-compatible aliases for installs that still have the previous folder ID in links.
Route::get('/LeantimeLib/projectIntegrations/{projectId}', [ProjectIntegrations::class, 'show']);
Route::post('/LeantimeLib/projectIntegrations/{projectId}/section-visibility', [ProjectIntegrations::class, 'saveSectionVisibility']);
Route::post('/LeantimeLib/projectIntegrations/{projectId}/panel-order', [ProjectIntegrations::class, 'savePanelOrder']);
Route::post('/LeantimeLib/projectIntegrations/{projectId}/todo-layout', [ProjectIntegrations::class, 'saveTodoLayout']);
Route::post('/LeantimeLib/my-schedule', [UserSchedule::class, 'post']);
Route::post('/LeantimeLib/plugins/check-for-updates', [Settings::class, 'syncPluginMetadata']);
Route::get('/LeantimeLib/integrations', [Settings::class, 'integrations']);
Route::post('/LeantimeLib/gui/company-settings', [Settings::class, 'saveCompanySettingsLayout']);
Route::post('/LeantimeLib/integrations/activate', [Settings::class, 'activatePlugin']);
Route::post('/LeantimeLib/integrations/action', [Settings::class, 'managePlugin']);
Route::get('/LeantimeLib/settings', [Settings::class, 'get']);
Route::post('/LeantimeLib/settings', [Settings::class, 'post']);
