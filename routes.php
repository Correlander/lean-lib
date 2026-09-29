<?php

use Illuminate\Support\Facades\Route;
use Leantime\Plugins\LeantimeLib\Controllers\ProjectIntegrations;

Route::get('/LeantimeLib/projectIntegrations/{projectId}', [ProjectIntegrations::class, 'show'])
    ->name('leantimeLib.projectIntegrations');

Route::post('/LeantimeLib/projectIntegrations/{projectId}/section-visibility', [ProjectIntegrations::class, 'saveSectionVisibility'])
    ->name('leantimeLib.projectIntegrations.sectionVisibility');
