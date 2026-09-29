<?php

use Illuminate\Support\Facades\Route;
use Leantime\Plugins\LeantimeLib\Controllers\ProjectIntegrations;

Route::get('/LeantimeLib/projectIntegrations/{projectId}', [ProjectIntegrations::class, 'show'])
    ->name('leantimeLib.projectIntegrations');
