<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Services\Registration;
use Leantime\Plugins\LeantimeLib\Services\TodoSectionRegistry;
use Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry;

$registration = app()->makeWith(Registration::class, ['pluginId' => 'LeantimeLib']);
$registration->addFooterJs(['library-settings.js', 'project-integrations.js', 'todo-layout.js']);
$registration->addCss(['library-settings.css', 'project-integrations.css']);

EventDispatcher::add_event_listener(
    'leantime.*.ticketTabs',
    [TodoTabRegistry::class, 'renderTabHeaders']
);


EventDispatcher::add_event_listener(
    'leantime.*.ticketTabsContent',
    [TodoTabRegistry::class, 'renderTabPanels']
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.*.beforeEndRightColumn',
    [TodoSectionRegistry::class, 'renderSections']
);
